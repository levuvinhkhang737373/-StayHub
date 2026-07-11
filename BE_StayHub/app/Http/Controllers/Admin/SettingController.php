<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\IndexRequest;
use App\Http\Requests\Admin\Setting\RegisterRequest;
use App\Http\Requests\Admin\Setting\UpdateRequest;
use App\Http\Resources\Admin\SettingDetailResource;
use App\Http\Resources\Admin\SettingResource;
use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    // Danh sách cấu hình hệ thống/tòa nhà
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewSettings($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem cài đặt tòa nhà', 403, null, 403);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem cài đặt của tòa nhà này', 403, null, 403);
            }

            $settings = $this->querySettings($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách cài đặt tòa nhà', 200, $this->paginatedResource($settings), 200);
        } catch (\Exception $e) {
            logger()->error('SettingController index error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo cấu hình mới
    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewSettings($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo cài đặt tòa nhà', 403, null, 403);
            }

            if (! $this->canMutateSettingForBuilding($admin, $validated['building_id'] ?? null)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo cài đặt cho tòa nhà này', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $setting = Setting::query()->create($this->payload($validated, $admin->id));

                AdminActivityLogger::write($admin, 'Tạo cài đặt', Setting::class, $setting->id, null, $setting->toArray(), $request);

                $setting->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Tạo cài đặt thành công', 201, new SettingDetailResource($setting), 201);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết cấu hình
    public function show(Request $request, int $setting): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewSettings($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem cài đặt tòa nhà', 403, null, 403);
            }

            $settingModel = $this->accessibleQuery($admin)
                ->select($this->columns())
                ->with($this->detailRelations())
                ->find($setting);

            if (! $settingModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy cài đặt', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết cài đặt', 200, new SettingDetailResource($settingModel), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật cấu hình
    public function update(UpdateRequest $request, int $setting): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewSettings($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật cài đặt tòa nhà', 403, null, 403);
            }

            if (array_key_exists('building_id', $validated) && ! $this->canMutateSettingForBuilding($admin, $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền chuyển cài đặt sang tòa nhà này', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $setting, $admin, $request): JsonResponse {
                $settingModel = $this->findAccessibleSetting($admin, $setting, true);

                if (! $settingModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy cài đặt', 404, null, 404);
                }

                if (! $this->canMutateSettingForBuilding($admin, $settingModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật cài đặt này', 403, null, 403);
                }

                $oldData = $settingModel->toArray();
                $settingModel->fill($this->payload($validated, null, true))->save();

                AdminActivityLogger::write($admin, 'Cập nhật cài đặt', Setting::class, $settingModel->id, $oldData, $settingModel->fresh()->toArray(), $request);

                $settingModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Cập nhật cài đặt thành công', 200, new SettingDetailResource($settingModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Bật/tắt trạng thái công khai của cấu hình
    public function togglePublic(Request $request, int $setting): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewSettings($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật cài đặt tòa nhà', 403, null, 403);
            }

            $response = DB::transaction(function () use ($setting, $admin, $request): JsonResponse {
                $settingModel = $this->findAccessibleSetting($admin, $setting, true);

                if (! $settingModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy cài đặt', 404, null, 404);
                }

                if (! $this->canMutateSettingForBuilding($admin, $settingModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật cài đặt này', 403, null, 403);
                }

                $oldData = $settingModel->toArray();
                $settingModel->is_public = !$settingModel->is_public;
                $settingModel->save();

                AdminActivityLogger::write($admin, 'Cập nhật hiển thị cài đặt', Setting::class, $settingModel->id, $oldData, $settingModel->fresh()->toArray(), $request);

                $settingModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái hiển thị thành công', 200, new SettingDetailResource($settingModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            logger()->error('SettingController togglePublic error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xóa cấu hình
    public function destroy(Request $request, int $setting): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewSettings($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa cài đặt tòa nhà', 403, null, 403);
            }

            $response = DB::transaction(function () use ($setting, $admin, $request): JsonResponse {
                $settingModel = $this->findAccessibleSetting($admin, $setting, true);

                if (! $settingModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy cài đặt', 404, null, 404);
                }

                if (! $this->canMutateSettingForBuilding($admin, $settingModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền xóa cài đặt này', 403, null, 403);
                }

                $oldData = $settingModel->toArray();
                $settingModel->delete();

                AdminActivityLogger::write($admin, 'Xóa cài đặt', Setting::class, $settingModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa cài đặt thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo cấu trúc dữ liệu cấu hình
    private function payload(array $validated, ?int $createdBy = null, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['building_id', 'setting_label', 'setting_value', 'description', 'is_public'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! $isUpdate) {
            $payload['created_by'] = $createdBy;
            $payload['is_public'] = $payload['is_public'] ?? Setting::PUBLIC;
        }

        return $payload;
    }

    // Danh sách cột dữ liệu cần lấy của cấu hình
    private function columns(): array
    {
        return ['id', 'building_id', 'setting_label', 'setting_value', 'description', 'is_public', 'created_by', 'created_at', 'updated_at'];
    }

    // Các quan hệ liên kết của cấu hình trong danh sách
    private function listRelations(): array
    {
        return ['building:id,name,slug,status,manager_admin_id', 'creator:id,full_name'];
    }

    // Các quan hệ liên kết chi tiết cấu hình
    private function detailRelations(): array
    {
        return ['building:id,name,slug,address,status,manager_admin_id', 'creator:id,full_name'];
    }

    // Tạo câu lệnh truy vấn cấu hình
    private function querySettings(array $validated, Admin $admin): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return $this->accessibleQuery($admin)
            ->select($this->columns())
            ->with($this->listRelations())
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery->where('setting_label', 'like', "%{$keyword}%")
                    ->orWhere('setting_value', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            }))
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $query->where('building_id', $validated['building_id']))
            ->when((bool) ($validated['only_global'] ?? false), fn (Builder $query): Builder => $query->whereNull('building_id'))
            ->when(array_key_exists('is_public', $validated), fn (Builder $query): Builder => $query->where('is_public', (bool) $validated['is_public']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    // Truy vấn cấu hình trong phạm vi quản lý của admin
    private function accessibleQuery(Admin $admin): Builder
    {
        $query = Setting::query();

        if (AdminScope::isSuperAdmin($admin)) {
            return $query;
        }

        if (AdminScope::isBuildingManager($admin)) {
            return $query->whereHas('building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
        }

        return $query->whereRaw('1 = 0');
    }

    // Tìm kiếm cấu hình cụ thể được phép truy cập
    private function findAccessibleSetting(Admin $admin, int $setting, bool $lock = false): ?Setting
    {
        $query = $this->accessibleQuery($admin);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->find($setting);
    }

    // Kiểm tra quyền xem cấu hình của admin
    private function canViewSettings(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    // Kiểm tra quyền thay đổi cấu hình tòa nhà của admin
    private function canMutateSettingForBuilding(Admin $admin, mixed $buildingId): bool
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return true;
        }

        if (! AdminScope::isBuildingManager($admin) || empty($buildingId)) {
            return false;
        }

        return AdminScope::ensureBuildingAccess($admin, (int) $buildingId);
    }

    // Định dạng dữ liệu cấu hình phân trang
    private function paginatedResource(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'data' => SettingResource::collection($paginator->items())->resolve(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

}
