<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\LoginRequest;
use App\Http\Resources\Tenant\TenantAuthResource;
use App\Models\Tenant;
use App\Helpers\ImageHelper;
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
                ->with(['currentContractTenant.contract.room.building'])
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

            // Nạp phòng hiện tại thông qua hợp đồng đang ở của khách thuê.
            $tenant->loadMissing(['currentContractTenant.contract.room.building']);

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
                'phone'     => ['nullable', 'string', 'regex:/^(0[3|5|7|8|9])+([0-9]{8})$/'],
                'avatar'    => ['nullable', 'image', 'max:5120'],
                'identity_number' => ['sometimes', 'required', 'string', 'max:30'],
                'identity_type' => ['sometimes', 'required', 'integer', 'in:1,2,3'],
                'identity_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
                'identity_place' => ['sometimes', 'required', 'string', 'max:255'],
                'permanent_address' => ['sometimes', 'required', 'string', 'max:500'],
            ], [
                'full_name.required' => 'Họ và tên là bắt buộc.',
                'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam (phải gồm 10 chữ số và bắt đầu bằng 03, 05, 07, 08, 09).',
            ]);

            $avatarUrl = $tenant->avatar_url;
            if ($request->hasFile('avatar')) {
                $avatarUrl = ImageHelper::update($request->file('avatar'), $tenant->avatar_url, 'avatars');
            }

            $updateData = collect($validated)->except(['avatar'])->toArray();
            $updateData['avatar_url'] = $avatarUrl;

            $tenant->update($updateData);
            $tenant->loadMissing(['currentContractTenant.contract.room.building']);

            return ApiResponse::responseJson(true, 'Cập nhật thông tin thành công', 200, new TenantAuthResource($tenant), 200);

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

    /**
     * Lấy lịch sử đơn giá điện nước của tòa nhà
     */
    public function utilityPriceHistory(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $buildingId = $tenant->building_id;
            if (! $buildingId) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa được gắn với tòa nhà nào', 400, null, 400);
            }

            $electricService = \App\Models\Service::whereIn('slug', ['electric', 'dien-sinh-hoat', 'dien'])->first();
            $waterService = \App\Models\Service::whereIn('slug', ['water', 'nuoc-sinh-hoat', 'nuoc'])->first();

            if (! $electricService || ! $waterService) {
                return ApiResponse::responseJson(false, 'Không tìm thấy cấu hình dịch vụ điện hoặc nước', 422, null, 422);
            }

            $prices = \App\Models\ServicePrice::where('building_id', $buildingId)
                ->whereIn('service_id', [$electricService->id, $waterService->id])
                ->with(['creator', 'service'])
                ->orderBy('effective_from', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $data = $prices->map(function ($price) {
                return [
                    'id' => $price->id,
                    'service_id' => $price->service_id,
                    'service_name' => $price->service?->name ?? 'Dịch vụ',
                    'price' => (float) $price->price,
                    'effective_from' => $price->effective_from->toDateString(),
                    'effective_to' => $price->effective_to ? $price->effective_to->toDateString() : null,
                    'status' => $price->status,
                    'status_label' => \App\Models\ServicePrice::STATUS_LABELS[$price->status] ?? 'Không xác định',
                    'created_by' => $price->created_by,
                    'creator_name' => $price->creator?->full_name ?? 'Hệ thống',
                    'created_at' => $price->created_at->toDateTimeString(),
                ];
            });

            return ApiResponse::responseJson(true, 'Lịch sử thay đổi đơn giá điện nước', 200, $data, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Lấy lịch sử chỉ số chốt điện nước của phòng khách thuê
     */
    public function utilityReadings(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $roomId = $tenant->room_id;
            if (! $roomId) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa được liên kết với phòng nào', 400, null, 400);
            }

            $readings = \App\Models\MeterReading::whereHas('meterDevice', function ($query) use ($roomId) {
                $query->where('room_id', $roomId);
            })
            ->with(['meterDevice.service'])
            ->orderBy('billing_year', 'desc')
            ->orderBy('billing_month', 'desc')
            ->get();

            $data = $readings->map(function ($reading) {
                return [
                    'id' => $reading->id,
                    'meter_device_id' => $reading->meter_device_id,
                    'meter_type' => $reading->meterDevice?->meter_type, // 1: Điện, 2: Nước
                    'meter_code' => $reading->meterDevice?->meter_code,
                    'service_name' => $reading->meterDevice?->service?->name ?? 'Dịch vụ',
                    'billing_month' => $reading->billing_month,
                    'billing_year' => $reading->billing_year,
                    'previous_reading' => (float) $reading->previous_reading,
                    'current_reading' => (float) $reading->current_reading,
                    'consumption' => (float) $reading->consumption,
                    'reading_date' => $reading->reading_date ? $reading->reading_date->toDateString() : null,
                    'image_url' => $reading->image_path ? \App\Helpers\ImageHelper::load($reading->image_path) : null,
                    'note' => $reading->note,
                    'status' => $reading->status,
                    'status_label' => \App\Models\MeterReading::STATUS_LABELS[$reading->status] ?? 'Chưa xác định',
                ];
            });

            return ApiResponse::responseJson(true, 'Lịch sử chỉ số điện nước của phòng', 200, $data, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Lấy danh sách cài đặt liên quan đến tòa nhà của khách thuê
     */
    public function buildingSettings(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $buildingId = $tenant->building_id;

            $settings = \App\Models\Setting::query()
                ->where('is_public', true)
                ->where(function ($query) use ($buildingId) {
                    $query->whereNull('building_id');
                    if ($buildingId) {
                        $query->orWhere('building_id', $buildingId);
                    }
                })
                ->with(['building:id,name'])
                ->orderBy('building_id', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $data = $settings->map(function ($setting) {
                return [
                    'id' => $setting->id,
                    'building_id' => $setting->building_id,
                    'building_name' => $setting->building?->name,
                    'setting_label' => $setting->setting_label,
                    'setting_value' => $setting->setting_value,
                    'description' => $setting->description,
                ];
            });

            return ApiResponse::responseJson(true, 'Danh sách cài đặt tòa nhà', 200, $data, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Đổi mật khẩu khách thuê hiện tại.
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $validated = $request->validate([
                'current_password'          => ['required', 'string', 'min:6', 'max:255'],
                'new_password'              => ['required', 'string', 'min:6', 'max:255', 'confirmed', 'different:current_password'],
                'new_password_confirmation' => ['required', 'string', 'min:6', 'max:255'],
            ], [
                'current_password.required'          => 'Vui lòng nhập mật khẩu hiện tại.',
                'current_password.min'               => 'Mật khẩu hiện tại tối thiểu 6 ký tự.',
                'new_password.required'              => 'Vui lòng nhập mật khẩu mới.',
                'new_password.min'                   => 'Mật khẩu mới tối thiểu 6 ký tự.',
                'new_password.confirmed'             => 'Xác nhận mật khẩu mới không khớp.',
                'new_password.different'             => 'Mật khẩu mới không được trùng mật khẩu hiện tại.',
                'new_password_confirmation.required' => 'Vui lòng xác nhận mật khẩu mới.',
                'new_password_confirmation.min'      => 'Xác nhận mật khẩu mới tối thiểu 6 ký tự.',
            ]);

            if (! Hash::check($validated['current_password'], $tenant->password)) {
                return ApiResponse::responseJson(false, 'Mật khẩu hiện tại không chính xác', 422, null, 422);
            }

            $tenant->forceFill([
                'password' => $validated['new_password'],
            ])->save();

            return ApiResponse::responseJson(true, 'Đổi mật khẩu thành công', 200, [
                'tenant' => new TenantAuthResource($tenant->fresh()),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::responseJson(false, $e->validator->errors()->first(), 422, $e->validator->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
