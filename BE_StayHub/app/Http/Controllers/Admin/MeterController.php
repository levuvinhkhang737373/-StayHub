<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Meter\IndexRequest;
use App\Http\Requests\Admin\Meter\StatusRequest;
use App\Http\Requests\Admin\Meter\StoreRequest;
use App\Http\Requests\Admin\Meter\UpdateRequest;
use App\Http\Resources\Admin\MeterResource;
use App\Models\MeterDevice;
use App\Models\Room;
use App\Models\Service;
use App\Support\BusinessRules\OperationalStateGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeterController extends Controller
{
    // Danh sách công tơ điện nước của tòa nhà
    public function index(IndexRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || (! AdminScope::isSuperAdmin($admin) && ! AdminScope::isBuildingManager($admin))) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh sách đồng hồ', 403, null, 403);
            }

            $query = $this->accessibleQuery($admin)
                ->with(['room.building', 'service', 'replacementMeter'])
                ->when($validated['room_id'] ?? null, fn (Builder $query, $roomId) => $query->where('room_id', $roomId))
                ->when($validated['service_id'] ?? null, fn (Builder $query, $serviceId) => $query->where('service_id', $serviceId))
                ->when($validated['meter_type'] ?? null, fn (Builder $query, $meterType) => $query->where('meter_type', $meterType))
                ->when($validated['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
                ->when(isset($validated['keyword']) && trim($validated['keyword']) !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($validated): void {
                    $keyword = trim($validated['keyword']);
                    $query->where('note', 'like', "%{$keyword}%")
                        ->orWhereHas('room', fn (Builder $q) => $q->where('room_number', 'like', "%{$keyword}%"));
                }));

            $meterDevices = $query->orderByDesc('id')->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách đồng hồ', 200, $this->paginatedResource($meterDevices), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo mới công tơ điện nước
    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            if (empty($validated['room_id']) && !empty($validated['room_number'])) {
                $room = Room::where('room_number', $validated['room_number'])->first();
                if (!$room) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy phòng với mã số này', 422, null, 422);
                }
                $validated['room_id'] = $room->id;
            }

            if (empty($validated['room_id'])) {
                return ApiResponse::responseJson(false, 'Phải nhập ID phòng hoặc Số phòng', 422, null, 422);
            }



            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo đồng hồ', 403, null, 403);
            }

            if (AdminScope::isBuildingManager($admin) && ! $this->roomBelongsToAdmin($validated['room_id'], $admin->id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo đồng hồ cho phòng này', 403, null, 403);
            }

            $status = (int) ($validated['status'] ?? MeterDevice::STATUS_ACTIVE);

            if ($status === MeterDevice::STATUS_REPLACED && empty($validated['replaced_by_meter_id'])) {
                return ApiResponse::responseJson(false, 'Khi trạng thái là đã bị thay thế phải chọn đồng hồ thay thế', 422, null, 422);
            }

            $room = Room::query()->with('building:id,status')->find((int) $validated['room_id']);
            $service = Service::query()->find((int) $validated['service_id']);

            if (! $room || ! $service) {
                return ApiResponse::responseJson(false, 'Phòng hoặc dịch vụ không tồn tại', 422, null, 422);
            }

            $stateError = OperationalStateGuard::meterCreationBlockReason($room, $service);
            if ($stateError !== null) {
                return ApiResponse::responseJson(false, $stateError, 422, null, 422);
            }

            $oldMeterId = $validated['replaced_by_meter_id'] ?? null;

            $existingActiveMeterQuery = MeterDevice::query()
                ->where('room_id', $validated['room_id'])
                ->where('service_id', $validated['service_id'])
                ->where('status', MeterDevice::STATUS_ACTIVE);

            if ($oldMeterId) {
                $existingActiveMeterQuery->where('id', '!=', $oldMeterId);
            }

            if ($status === MeterDevice::STATUS_ACTIVE && $existingActiveMeterQuery->exists()) {
                return ApiResponse::responseJson(false, 'Phòng này đã có đồng hồ đang sử dụng cho dịch vụ này', 422, null, 422);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request, $oldMeterId, $status): JsonResponse {
                $meterDevice = MeterDevice::query()->create([
                    'room_id' => $validated['room_id'],
                    'service_id' => $validated['service_id'],
                    'meter_code' => $validated['meter_code'] ?? null,
                    'meter_type' => $validated['meter_type'],
                    'initial_reading' => $validated['initial_reading'],
                    'installed_at' => $validated['installed_at'] ?? now(),
                    'status' => $status,
                    'replaced_by_meter_id' => null, // The new meter is not replaced
                    'note' => $validated['note'] ?? null,
                    'image_path' => $request->file('image') ? ImageHelper::create($request->file('image'), 'meter-device') : null,
                ]);

                if ($oldMeterId) {
                    $oldMeter = MeterDevice::query()->find($oldMeterId);
                    if ($oldMeter) {
                        $oldMeter->status = MeterDevice::STATUS_REPLACED; // 3
                        $oldMeter->replaced_by_meter_id = $meterDevice->id; // Linked to new meter
                        $oldMeter->save();
                    }
                }

                AdminActivityLogger::write($admin, 'Tạo đồng hồ điện nước', MeterDevice::class, $meterDevice->id, null, $meterDevice->toArray(), $request);

                return ApiResponse::responseJson(true, 'Tạo đồng hồ thành công', 201, new MeterResource($meterDevice->load(['room.building', 'service', 'replacementMeter'])), 201);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết công tơ điện nước
    public function show(Request $request, int $meterDevice): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || (! AdminScope::isSuperAdmin($admin) && ! AdminScope::isBuildingManager($admin))) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem đồng hồ', 403, null, 403);
            }

            $meterDeviceModel = $this->accessibleQuery($admin)
                ->with(['room.building', 'service', 'replacementMeter'])
                ->find($meterDevice);

            if (! $meterDeviceModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy đồng hồ', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết đồng hồ', 200, new MeterResource($meterDeviceModel), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật thông tin công tơ điện nước
    public function update(UpdateRequest $request, int $meterDevice): JsonResponse
    {
        try {
            $validated = $request->validated();

            if (empty($validated['room_id']) && !empty($validated['room_number'])) {
                $room = Room::where('room_number', $validated['room_number'])->first();
                if (!$room) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy phòng với mã số này', 422, null, 422);
                }
                $validated['room_id'] = $room->id;
            }

            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật đồng hồ', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $meterDevice, $admin, $request): JsonResponse {
                $meterDeviceModel = $this->accessibleQuery($admin)->lockForUpdate()->find($meterDevice);

                if (! $meterDeviceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy đồng hồ', 404, null, 404);
                }



                if (AdminScope::isBuildingManager($admin) && isset($validated['room_id']) && ! $this->roomBelongsToAdmin($validated['room_id'], $admin->id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền gán đồng hồ cho phòng này', 403, null, 403);
                }

                if (isset($validated['replaced_by_meter_id']) && $validated['replaced_by_meter_id'] === $meterDeviceModel->id) {
                    return ApiResponse::responseJson(false, 'Không thể chọn chính đồng hồ này làm đồng hồ thay thế', 422, null, 422);
                }

                if (isset($validated['status']) && $validated['status'] === MeterDevice::STATUS_REPLACED && empty($validated['replaced_by_meter_id'])) {
                    return ApiResponse::responseJson(false, 'Khi trạng thái là đã bị thay thế phải chọn đồng hồ thay thế', 422, null, 422);
                }

                $targetStatus = (int) ($validated['status'] ?? $meterDeviceModel->status);
                $oldMeterId = $validated['replaced_by_meter_id'] ?? null;

                if ($targetStatus === MeterDevice::STATUS_ACTIVE) {
                    $roomId = $validated['room_id'] ?? $meterDeviceModel->room_id;
                    $serviceId = $validated['service_id'] ?? $meterDeviceModel->service_id;
                    $room = Room::query()->with('building:id,status')->find((int) $roomId);
                    $service = Service::query()->find((int) $serviceId);

                    if (! $room || ! $service) {
                        return ApiResponse::responseJson(false, 'Phòng hoặc dịch vụ không tồn tại', 422, null, 422);
                    }

                    $stateError = OperationalStateGuard::meterCreationBlockReason($room, $service);

                    if ($stateError !== null) {
                        return ApiResponse::responseJson(false, $stateError, 422, null, 422);
                    }

                    $existingActiveMeterQuery = MeterDevice::query()
                        ->where('room_id', $roomId)
                        ->where('service_id', $serviceId)
                        ->where('id', '!=', $meterDeviceModel->id)
                        ->where('status', MeterDevice::STATUS_ACTIVE);

                    if ($oldMeterId) {
                        $existingActiveMeterQuery->where('id', '!=', $oldMeterId);
                    }

                    if ($existingActiveMeterQuery->exists()) {
                        return ApiResponse::responseJson(false, 'Phòng này đã có đồng hồ đang sử dụng cho dịch vụ này', 422, null, 422);
                    }
                }

                $oldData = $meterDeviceModel->toArray();

                if (($validated['delete_image'] ?? false) === true) {
                    ImageHelper::delete($meterDeviceModel->image_path);
                    $meterDeviceModel->image_path = null;
                }

                if ($request->file('image')) {
                    $meterDeviceModel->image_path = ImageHelper::update($request->file('image'), $meterDeviceModel->image_path, 'meter-device');
                }

                foreach (['room_id', 'service_id', 'meter_code', 'meter_type', 'initial_reading', 'installed_at', 'status', 'note'] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $meterDeviceModel->{$field} = $validated[$field];
                    }
                }

                if ($oldMeterId) {
                    $meterDeviceModel->replaced_by_meter_id = null; // New/Updated meter is not replaced
                    
                    $oldMeter = MeterDevice::query()->find($oldMeterId);
                    if ($oldMeter) {
                        $oldMeter->status = MeterDevice::STATUS_REPLACED; // 3
                        $oldMeter->replaced_by_meter_id = $meterDeviceModel->id; // Linked to new/updated meter
                        $oldMeter->save();
                    }
                }

                $meterDeviceModel->save();

                AdminActivityLogger::write($admin, 'Cập nhật đồng hồ điện nước', MeterDevice::class, $meterDeviceModel->id, $oldData, $meterDeviceModel->fresh()->toArray(), $request);

                return ApiResponse::responseJson(true, 'Cập nhật đồng hồ thành công', 200, new MeterResource($meterDeviceModel->load(['room.building', 'service', 'replacementMeter'])), 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật trạng thái hoạt động của công tơ
    public function updateStatus(StatusRequest $request, int $meterDevice): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật trạng thái đồng hồ', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $meterDevice, $admin, $request): JsonResponse {
                $meterDeviceModel = $this->accessibleQuery($admin)->lockForUpdate()->find($meterDevice);

                if (! $meterDeviceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy đồng hồ', 404, null, 404);
                }



                if ($validated['status'] === MeterDevice::STATUS_REPLACED && empty($validated['replaced_by_meter_id'])) {
                    return ApiResponse::responseJson(false, 'Khi đổi trạng thái sang đã bị thay thế phải có đồng hồ thay thế', 422, null, 422);
                }

                if (isset($validated['replaced_by_meter_id']) && $validated['replaced_by_meter_id'] === $meterDeviceModel->id) {
                    return ApiResponse::responseJson(false, 'Không thể chọn chính đồng hồ này làm đồng hồ thay thế', 422, null, 422);
                }

                if ((int) $validated['status'] === MeterDevice::STATUS_ACTIVE) {
                    $meterDeviceModel->loadMissing(['room.building', 'service']);
                    $stateError = OperationalStateGuard::meterCreationBlockReason($meterDeviceModel->room, $meterDeviceModel->service);

                    if ($stateError !== null) {
                        return ApiResponse::responseJson(false, $stateError, 422, null, 422);
                    }

                    if (MeterDevice::query()
                        ->where('room_id', $meterDeviceModel->room_id)
                        ->where('service_id', $meterDeviceModel->service_id)
                        ->where('id', '!=', $meterDeviceModel->id)
                        ->where('status', MeterDevice::STATUS_ACTIVE)
                        ->exists()) {
                        return ApiResponse::responseJson(false, 'Phòng này đã có đồng hồ đang sử dụng cho dịch vụ này', 422, null, 422);
                    }
                }

                $oldData = $meterDeviceModel->toArray();
                $meterDeviceModel->status = $validated['status'];
                $meterDeviceModel->replaced_by_meter_id = $validated['replaced_by_meter_id'] ?? $meterDeviceModel->replaced_by_meter_id;
                $meterDeviceModel->save();

                AdminActivityLogger::write($admin, 'Cập nhật trạng thái đồng hồ điện nước', MeterDevice::class, $meterDeviceModel->id, $oldData, $meterDeviceModel->fresh()->toArray(), $request);

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái đồng hồ thành công', 200, new MeterResource($meterDeviceModel->load(['room.building', 'service', 'replacementMeter'])), 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xóa công tơ điện nước
    public function destroy(Request $request, int $meterDevice): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa đồng hồ', 403, null, 403);
            }

            $response = DB::transaction(function () use ($meterDevice, $admin, $request): JsonResponse {
                $meterDeviceModel = $this->accessibleQuery($admin)
                    ->withCount(['readings', 'replacedMeters'])
                    ->lockForUpdate()
                    ->find($meterDevice);

                if (! $meterDeviceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy đồng hồ', 404, null, 404);
                }

                if ($meterDeviceModel->readings_count > 0 || $meterDeviceModel->replaced_meters_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể xóa đồng hồ đang có dữ liệu liên quan', 422, null, 422);
                }

                $oldData = $meterDeviceModel->toArray();

                ImageHelper::delete($meterDeviceModel->image_path);
                $meterDeviceModel->delete();

                AdminActivityLogger::write($admin, 'Xóa đồng hồ điện nước', MeterDevice::class, $meterDeviceModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa đồng hồ thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Truy vấn danh sách công tơ trong phạm vi quản lý
    private function accessibleQuery($admin): Builder
    {
        $query = MeterDevice::query();

        if (AdminScope::isBuildingManager($admin)) {
            $query->whereHas('room.building', fn (Builder $query) => $query->where('manager_admin_id', $admin->id));
        }

        return $query;
    }

    // Kiểm tra phòng thuộc phạm vi quản lý của admin không
    private function roomBelongsToAdmin(int $roomId, int $adminId): bool
    {
        return Room::query()
            ->whereKey($roomId)
            ->whereHas('building', fn (Builder $query) => $query->where('manager_admin_id', $adminId))
            ->exists();
    }

    // Định dạng dữ liệu công tơ điện nước phân trang
    private function paginatedResource(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'data' => MeterResource::collection($paginator->items())->resolve(),
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
