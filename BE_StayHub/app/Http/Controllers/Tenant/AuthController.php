<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\LoginRequest;
use App\Http\Resources\Tenant\TenantAuthResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Đăng nhập khách thuê (Tenant)
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Bước 1: Lấy dữ liệu đã validate
            $validated = $request->validated();
            
            \Illuminate\Support\Facades\Log::info('Tenant Login Attempt:', [
                'username' => $validated['username'],
                'password_length' => strlen($validated['password']),
            ]);

            // Bước 2: Tương tác Database, dùng with để nạp quan hệ tránh N+1
            $tenant = Tenant::query()
                ->where('username', $validated['username'])
                ->with(['room.building'])
                ->first();

            if (! $tenant) {
                \Illuminate\Support\Facades\Log::warning('Tenant Login: Tenant not found', ['username' => $validated['username']]);
                return ApiResponse::responseJson(false, 'Tên đăng nhập hoặc mật khẩu không chính xác', 401, null, 401);
            }

            if ($tenant->status !== Tenant::STATUS_RENTING) {
                \Illuminate\Support\Facades\Log::warning('Tenant Login: Tenant not active', ['username' => $validated['username'], 'status' => $tenant->status]);
                return ApiResponse::responseJson(false, 'Tài khoản của bạn đã bị khóa hoặc ngừng thuê', 403, null, 403);
            }

            $passwordMatch = Hash::check($validated['password'], $tenant->password);
            \Illuminate\Support\Facades\Log::info('Tenant Login: Password match result', [
                'username' => $validated['username'],
                'match' => $passwordMatch,
            ]);

            if (! $passwordMatch) {
                return ApiResponse::responseJson(false, 'Tên đăng nhập hoặc mật khẩu không chính xác', 401, null, 401);
            }

            // Bước 3: Đăng nhập session và regenerate token
            Auth::guard('tenant')->login($tenant);
            $request->session()->regenerate();
            $request->session()->save();

            // Trả về dữ liệu theo format chuẩn
            return ApiResponse::responseJson(true, 'Đăng nhập thành công', 200, [
                'tenant' => new TenantAuthResource($tenant),
            ], 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Lấy thông tin khách thuê đang đăng nhập
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');

            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            // Nạp quan hệ phòng và tòa nhà
            $tenant->loadMissing(['room.building']);

            return ApiResponse::responseJson(true, 'Lấy thông tin khách thuê thành công', 200, new TenantAuthResource($tenant), 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Cập nhật thông tin định danh khách thuê
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');

            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $validated = $request->validate([
                'full_name' => ['required', 'string', 'max:150'],
                'identity_number' => ['required', 'string', 'max:30'],
                'identity_type' => ['required', 'integer', 'in:1,2,3'],
                'identity_date' => ['required', 'date_format:Y-m-d'],
                'identity_place' => ['required', 'string', 'max:255'],
                'permanent_address' => ['required', 'string', 'max:500'],
            ], [
                'full_name.required' => 'Họ và tên là bắt buộc.',
                'identity_number.required' => 'Số CMND/CCCD là bắt buộc.',
                'identity_date.required' => 'Ngày cấp là bắt buộc.',
                'identity_place.required' => 'Nơi cấp là bắt buộc.',
                'permanent_address.required' => 'Địa chỉ thường trú là bắt buộc.',
            ]);

            $tenant->update($validated);
            $tenant->loadMissing(['room.building']);

            return ApiResponse::responseJson(true, 'Cập nhật thông tin thành công', 200, new TenantAuthResource($tenant->fresh()), 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::responseJson(false, $e->validator->errors()->first(), 422, $e->validator->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Đăng xuất khách thuê
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            Auth::guard('tenant')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ApiResponse::responseJson(true, 'Đăng xuất thành công', 200, null, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
