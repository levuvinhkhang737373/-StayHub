<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Vehicle\IndexRequest;
use App\Http\Requests\Admin\Vehicle\RegisterRequest;
use App\Http\Requests\Admin\Vehicle\StatusRequest;
use App\Http\Requests\Admin\Vehicle\UpdateRequest;
use App\Http\Resources\Admin\VehicleDetailResource;
use App\Http\Resources\Admin\VehicleResource;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewVehicles($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh sách phương tiện', 403, null, 403);
            }

            if (isset($validated['tenant_id']) && ! $this->ensureTenantAccess($admin, (int) $validated['tenant_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem phương tiện của khách thuê này', 403, null, 403);
            }

            $vehicles = $this->queryVehicles($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách phương tiện', 200, VehicleResource::collection($vehicles), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateVehicles($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo phương tiện', 403, null, 403);
            }

            if (! $this->ensureTenantAccess($admin, (int) $validated['tenant_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền thêm phương tiện cho khách thuê này', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $vehicle = Vehicle::query()->create($this->payload($validated));

                AdminActivityLogger::write($admin, 'create_vehicle', Vehicle::class, $vehicle->id, null, $vehicle->toArray(), $request);

                $vehicle->load($this->detailRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Tạo phương tiện thành công', 201, new VehicleDetailResource($vehicle), 201);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $vehicle): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewVehicles($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem phương tiện', 403, null, 403);
            }

            $vehicleModel = $this->accessibleQuery($admin)
                ->select($this->columns())
                ->with($this->detailRelations())
                ->withCount($this->counts())
                ->find($vehicle);

            if (! $vehicleModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phương tiện', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết phương tiện', 200, new VehicleDetailResource($vehicleModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $vehicle): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateVehicles($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật phương tiện', 403, null, 403);
            }

            if (isset($validated['tenant_id']) && ! $this->ensureTenantAccess($admin, (int) $validated['tenant_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền chuyển phương tiện sang khách thuê này', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $vehicle, $admin, $request): JsonResponse {
                $vehicleModel = $this->accessibleQuery($admin)->lockForUpdate()->find($vehicle);

                if (! $vehicleModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy phương tiện', 404, null, 404);
                }

                $oldData = $vehicleModel->toArray();
                $vehicleModel->fill($this->payload($validated, true))->save();

                AdminActivityLogger::write($admin, 'update_vehicle', Vehicle::class, $vehicleModel->id, $oldData, $vehicleModel->fresh()->toArray(), $request);

                $vehicleModel->load($this->detailRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật phương tiện thành công', 200, new VehicleDetailResource($vehicleModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function updateStatus(StatusRequest $request, int $vehicle): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateVehicles($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái phương tiện', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $vehicle, $admin, $request): JsonResponse {
                $vehicleModel = $this->accessibleQuery($admin)->lockForUpdate()->find($vehicle);

                if (! $vehicleModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy phương tiện', 404, null, 404);
                }

                $oldData = $vehicleModel->toArray();
                $vehicleModel->forceFill(['is_active' => (bool) $validated['status']])->save();

                AdminActivityLogger::write($admin, 'update_vehicle_status', Vehicle::class, $vehicleModel->id, $oldData, $vehicleModel->fresh()->toArray(), $request);

                $vehicleModel->load($this->detailRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái phương tiện thành công', 200, new VehicleDetailResource($vehicleModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function destroy(Request $request, int $vehicle): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateVehicles($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa phương tiện', 403, null, 403);
            }

            $response = DB::transaction(function () use ($vehicle, $admin, $request): JsonResponse {
                $vehicleModel = $this->accessibleQuery($admin)->withCount($this->counts())->lockForUpdate()->find($vehicle);

                if (! $vehicleModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy phương tiện', 404, null, 404);
                }

                if ((int) $vehicleModel->contract_vehicles_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể xóa phương tiện đã liên kết với hợp đồng thuê.', 422, null, 422);
                }

                $oldData = $vehicleModel->toArray();
                $vehicleModel->delete();

                AdminActivityLogger::write($admin, 'delete_vehicle', Vehicle::class, $vehicleModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa phương tiện thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function payload(array $validated, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['tenant_id', 'vehicle_type', 'license_plate', 'brand', 'color', 'is_active'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! $isUpdate) {
            $payload['is_active'] = $payload['is_active'] ?? Vehicle::ACTIVE;
        }

        return $payload;
    }

    private function columns(): array
    {
        return ['id', 'tenant_id', 'vehicle_type', 'license_plate', 'brand', 'color', 'is_active', 'created_at', 'updated_at'];
    }

    private function listRelations(): array
    {
        return ['tenant:id,full_name,phone,email'];
    }

    private function detailRelations(): array
    {
        return ['tenant:id,full_name,phone,email'];
    }

    private function counts(): array
    {
        return ['contractVehicles'];
    }

    private function queryVehicles(array $validated, Admin $admin): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return $this->accessibleQuery($admin)
            ->select($this->columns())
            ->with($this->listRelations())
            ->withCount($this->counts())
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery->where('license_plate', 'like', "%{$keyword}%")
                    ->orWhere('brand', 'like', "%{$keyword}%")
                    ->orWhere('color', 'like', "%{$keyword}%")
                    ->orWhereHas('tenant', fn (Builder $tenantQuery) => $tenantQuery->where('full_name', 'like', "%{$keyword}%"));
            }))
            ->when(isset($validated['tenant_id']), fn (Builder $query): Builder => $query->where('tenant_id', $validated['tenant_id']))
            ->when(isset($validated['vehicle_type']), fn (Builder $query): Builder => $query->where('vehicle_type', $validated['vehicle_type']))
            ->when(array_key_exists('is_active', $validated), fn (Builder $query): Builder => $query->where('is_active', (bool) $validated['is_active']))
            ->when(isset($validated['without_active_contract']) && filter_var($validated['without_active_contract'], FILTER_VALIDATE_BOOLEAN), function (Builder $query): Builder {
                return $query->where('is_active', true)
                    ->whereDoesntHave('contractVehicles', function (Builder $q): void {
                        $q->where('is_active', true)
                            ->whereHas('contract', function (Builder $cq): void {
                                $cq->where('status', Contract::STATUS_ACTIVE);
                            });
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function accessibleQuery(Admin $admin): Builder
    {
        $query = Vehicle::query();

        if (AdminScope::isSuperAdmin($admin)) {
            return $query;
        }

        if (AdminScope::isBuildingManager($admin)) {
            return $query->where(function (Builder $query) use ($admin) {
                $query->whereHas('contracts.room', function (Builder $roomQuery) use ($admin) {
                    AdminScope::applyBuildingScope($roomQuery, $admin, 'building_id');
                })
                ->orWhereHas('tenant.contracts.room', function (Builder $roomQuery) use ($admin) {
                    AdminScope::applyBuildingScope($roomQuery, $admin, 'building_id');
                });
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function ensureTenantAccess(Admin $admin, int $tenantId): bool
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return true;
        }

        if (AdminScope::isBuildingManager($admin)) {
            return Tenant::query()
                ->whereKey($tenantId)
                ->where(function (Builder $q) use ($admin) {
                    $q->whereHas('contracts.room', function (Builder $roomQuery) use ($admin) {
                        AdminScope::applyBuildingScope($roomQuery, $admin, 'building_id');
                    });
                })
                ->exists();
        }

        return false;
    }

    private function canViewVehicles(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    private function canMutateVehicles(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }
}
