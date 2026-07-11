<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Service\IndexRequest;
use App\Http\Requests\Admin\Service\RegisterRequest;
use App\Http\Requests\Admin\Service\StatusRequest;
use App\Http\Requests\Admin\Service\UpdateRequest;
use App\Http\Resources\Admin\ServiceDetailResource;
use App\Http\Resources\Admin\ServiceResource;
use App\Models\Admin;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    // Danh sách các dịch vụ của tòa nhà
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewServices($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh mục dịch vụ', 403, null, 403);
            }

            $services = $this->queryServices($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách dịch vụ', 200, ServiceResource::collection($services), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Tạo mới dịch vụ
    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateServices($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo dịch vụ', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $service = Service::query()->create($this->payload($validated, $admin->id));

                AdminActivityLogger::write($admin, 'Tạo dịch vụ', Service::class, $service->id, null, $service->toArray(), $request);

                $service->load($this->detailRelations($admin))->loadCount($this->counts($admin));

                return ApiResponse::responseJson(true, 'Tạo dịch vụ thành công', 201, new ServiceDetailResource($service), 201);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết dịch vụ
    public function show(Request $request, int $service): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewServices($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh mục dịch vụ', 403, null, 403);
            }

            $serviceModel = Service::query()
                ->select($this->columns())
                ->with($this->detailRelations($admin))
                ->withCount($this->counts($admin))
                ->find($service);

            if (! $serviceModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy dịch vụ', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết dịch vụ', 200, new ServiceDetailResource($serviceModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật thông tin dịch vụ
    public function update(UpdateRequest $request, int $service): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateServices($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật dịch vụ', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $service, $admin, $request): JsonResponse {
                $serviceModel = Service::query()->lockForUpdate()->find($service);

                if (! $serviceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy dịch vụ', 404, null, 404);
                }

                $oldData = $serviceModel->toArray();
                $serviceModel->fill($this->payload($validated, null, true))->save();

                AdminActivityLogger::write($admin, 'Cập nhật dịch vụ', Service::class, $serviceModel->id, $oldData, $serviceModel->fresh()->toArray(), $request);

                $serviceModel->load($this->detailRelations($admin))->loadCount($this->counts($admin));

                return ApiResponse::responseJson(true, 'Cập nhật dịch vụ thành công', 200, new ServiceDetailResource($serviceModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật trạng thái hoạt động của dịch vụ
    public function updateStatus(StatusRequest $request, int $service): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateServices($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái dịch vụ', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $service, $admin, $request): JsonResponse {
                $serviceModel = Service::query()->lockForUpdate()->find($service);

                if (! $serviceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy dịch vụ', 404, null, 404);
                }

                $oldData = $serviceModel->toArray();
                $serviceModel->forceFill(['is_active' => (bool) $validated['status']])->save();

                AdminActivityLogger::write($admin, 'Cập nhật trạng thái dịch vụ', Service::class, $serviceModel->id, $oldData, $serviceModel->fresh()->toArray(), $request);

                $serviceModel->load($this->detailRelations($admin))->loadCount($this->counts($admin));

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái dịch vụ thành công', 200, new ServiceDetailResource($serviceModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Xóa dịch vụ khỏi hệ thống
    public function destroy(Request $request, int $service): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateServices($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa dịch vụ', 403, null, 403);
            }

            $response = DB::transaction(function () use ($service, $admin, $request): JsonResponse {
                $serviceModel = Service::query()->withCount($this->counts($admin))->lockForUpdate()->find($service);

                if (! $serviceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy dịch vụ', 404, null, 404);
                }

                if ((int) $serviceModel->prices_count > 0 || (int) $serviceModel->meter_devices_count > 0 || (int) $serviceModel->invoice_items_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể xóa dịch vụ đã phát sinh bảng giá, thiết bị đo hoặc hóa đơn. Vui lòng chuyển sang ngừng hoạt động.', 422, null, 422);
                }

                $oldData = $serviceModel->toArray();
                $serviceModel->delete();

                AdminActivityLogger::write($admin, 'Xóa dịch vụ', Service::class, $serviceModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa dịch vụ thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Tạo cấu trúc dữ liệu lưu dịch vụ
    private function payload(array $validated, ?int $createdBy = null, bool $isUpdate = false): array
    {
        $payload = [];
        $fields  = ['name', 'charge_method', 'unit_name', 'is_required', 'is_active'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! $isUpdate) {
            $payload['created_by']  = $createdBy;
            $payload['is_required'] = $payload['is_required'] ?? Service::REQUIRED_NO;
            $payload['is_active']   = $payload['is_active'] ?? Service::ACTIVE;
        }

        return $payload;
    }

    // Danh sách cột cần lấy của dịch vụ
    private function columns(): array
    {
        return ['id', 'name', 'slug', 'charge_method', 'unit_name', 'is_required', 'is_active', 'created_by', 'created_at', 'updated_at'];
    }

    // Các quan hệ liên kết của dịch vụ trong danh sách
    private function listRelations(): array
    {
        return ['creator:id,full_name'];
    }

    // Các quan hệ liên kết chi tiết dịch vụ
    private function detailRelations(Admin $admin): array
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return ['creator:id,full_name', 'prices:id,service_id,building_id,price,effective_from,effective_to,status', 'prices.building:id,name,slug'];
        }

        return [
            'creator:id,full_name',
            'prices' => fn($query) => AdminScope::applyBuildingScope($query->select('id', 'service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status'), $admin),
            'prices.building:id,name,slug',
        ];
    }

    // Các quan hệ cần đếm số lượng liên kết
    private function counts(Admin $admin): array
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return ['prices', 'meterDevices', 'invoiceItems'];
        }

        return [
            'prices'       => fn(Builder $query): Builder       => AdminScope::applyBuildingScope($query, $admin),
            'meterDevices' => fn(Builder $query): Builder => $query->whereHas('room', fn(Builder $roomQuery): Builder => AdminScope::applyBuildingScope($roomQuery, $admin)),
            'invoiceItems' => fn(Builder $query): Builder => $query->whereHas('invoice.room', fn(Builder $roomQuery): Builder => AdminScope::applyBuildingScope($roomQuery, $admin)),
        ];
    }

    // Tạo truy vấn danh sách dịch vụ
    private function queryServices(array $validated, Admin $admin): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return Service::query()
            ->select($this->columns())
            ->with($this->listRelations())
            ->withCount($this->counts($admin))
            ->when($keyword !== '', fn(Builder $query): Builder => $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('unit_name', 'like', "%{$keyword}%");
            }))
            ->when(isset($validated['charge_method']), fn(Builder $query): Builder => $query->where('charge_method', $validated['charge_method']))
            ->when(array_key_exists('is_required', $validated), fn(Builder $query): Builder => $query->where('is_required', (bool) $validated['is_required']))
            ->when(array_key_exists('is_active', $validated), fn(Builder $query): Builder => $query->where('is_active', (bool) $validated['is_active']))
            ->when(isset($validated['created_by_role']), fn(Builder $query): Builder => $query->whereHas('creator', fn(Builder $creatorQuery): Builder => $creatorQuery->where('role', $validated['created_by_role'])))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    // Kiểm tra quyền xem dịch vụ của admin
    private function canViewServices(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    // Kiểm tra quyền chỉnh sửa/thao tác dịch vụ
    private function canMutateServices(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin);
    }
}
