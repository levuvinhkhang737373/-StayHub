<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\ForgotPasswordRequest;
use App\Http\Requests\Tenant\Auth\ResetPasswordRequest;
use App\Mail\TenantForgotPasswordOtpMail;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    /**
     * Gửi OTP quên mật khẩu tới Email của Tenant
     */
    public function sendResetCodeEmail(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $email = $request->input('email');
            
            // Tìm Tenant hoạt động
            $tenant = Tenant::where('email', $email)->first();
            
            if (!$tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê không tồn tại.', 404, null, 404);
            }

            if ($tenant->status !== Tenant::STATUS_RENTING) {
                return ApiResponse::responseJson(false, 'Tài khoản của bạn đã bị khóa hoặc ngừng thuê.', 403, null, 403);
            }

            // Tạo OTP ngẫu nhiên 6 chữ số
            $otp = (string) rand(100000, 999999);

            // Lưu/Cập nhật vào bảng password_reset_tokens
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => $otp, // Lưu mã OTP trực tiếp dạng chuỗi
                    'created_at' => Carbon::now()
                ]
            );

            // Gửi mail OTP
            Mail::to($email)->send(new TenantForgotPasswordOtpMail($tenant, $otp));

            return ApiResponse::responseJson(true, 'Mã OTP đặt lại mật khẩu đã được gửi đến email của bạn.', 200, null, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Lỗi Server: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Xác minh OTP và đặt lại mật khẩu mới
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $email = $request->input('email');
            $otp = $request->input('otp');
            $password = $request->input('password');

            // Tìm bản ghi OTP
            $resetToken = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$resetToken) {
                return ApiResponse::responseJson(false, 'Mã OTP không hợp lệ hoặc đã hết hạn.', 400, null, 400);
            }

            // Kiểm tra OTP trùng khớp
            if ($resetToken->token !== $otp) {
                return ApiResponse::responseJson(false, 'Mã OTP không chính xác.', 400, null, 400);
            }

            // Kiểm tra hết hạn (15 phút)
            $createdAt = Carbon::parse($resetToken->created_at);
            if ($createdAt->addMinutes(15)->isPast()) {
                // Xóa OTP đã hết hạn
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                return ApiResponse::responseJson(false, 'Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.', 400, null, 400);
            }

            // Cập nhật mật khẩu cho Tenant
            $tenant = Tenant::where('email', $email)->first();
            if (!$tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê không tồn tại.', 404, null, 404);
            }

            $tenant->update([
                'password' => Hash::make($password)
            ]);

            // Xóa mã OTP đã sử dụng thành công
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return ApiResponse::responseJson(true, 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.', 200, null, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Lỗi Server: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
