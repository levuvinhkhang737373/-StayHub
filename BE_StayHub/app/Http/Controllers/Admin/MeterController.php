<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MeterResource;
use App\Models\MeterDevice;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:1000',
                'room_id' => 'nullable|integer|exists:rooms,id',
                'service_id' => 'nullable|integer|exists:services,id',
                'meter_type' => 'nullable|integer|in:1,2',
                'status' => 'nullable|integer|in:1,2,3,4',
                'keyword' => 'nullable|string|max:100',
            ]);

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
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'room_id' => 'nullable|integer|exists:rooms,id',
                'room_number' => 'nullable|string|max:50',
                'service_id' => 'required|integer|exists:services,id',
                'meter_code' => 'nullable|string|max:100|unique:meter_devices,meter_code',
                'meter_type' => 'required|integer|in:1,2',
                'initial_reading' => 'required|numeric|min:0',
                'installed_at' => 'nullable|date',
                'final_reading' => 'nullable|numeric|min:0',
                'status' => 'nullable|integer|in:1,2,3,4',
                'replaced_by_meter_id' => 'nullable|integer|exists:meter_devices,id',
                'note' => 'nullable|string|max:500',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            ]);

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

            if (isset($validated['final_reading']) && $validated['final_reading'] <= $validated['initial_reading']) {
                return ApiResponse::responseJson(false, 'Chỉ số cuối phải lớn hơn chỉ số khởi tạo', 422, null, 422);
            }

            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo đồng hồ', 403, null, 403);
            }

            if (AdminScope::isBuildingManager($admin) && ! $this->roomBelongsToAdmin($validated['room_id'], $admin->id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo đồng hồ cho phòng này', 403, null, 403);
            }

            if ($validated['status'] === MeterDevice::STATUS_REPLACED && empty($validated['replaced_by_meter_id'])) {
                return ApiResponse::responseJson(false, 'Khi trạng thái là đã thay thế phải chọn đồng hồ thay thế', 422, null, 422);
            }

            if (MeterDevice::query()
                ->where('room_id', $validated['room_id'])
                ->where('service_id', $validated['service_id'])
                ->where('status', '!=', MeterDevice::STATUS_REPLACED)
                ->exists()) {
                return ApiResponse::responseJson(false, 'Phòng này đã có đồng hồ cho dịch vụ này', 422, null, 422);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $meterDevice = MeterDevice::query()->create([
                    'room_id' => $validated['room_id'],
                    'service_id' => $validated['service_id'],
                    'meter_code' => $validated['meter_code'] ?? null,
                    'meter_type' => $validated['meter_type'],
                    'initial_reading' => $validated['initial_reading'],
                    'installed_at' => $validated['installed_at'] ?? now(),
                    'final_reading' => $validated['final_reading'] ?? null,
                    'status' => $validated['status'] ?? MeterDevice::STATUS_ACTIVE,
                    'replaced_by_meter_id' => $validated['replaced_by_meter_id'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'image_path' => $request->file('image') ? ImageHelper::create($request->file('image'), 'meter-device') : null,
                ]);

                AdminActivityLogger::write($admin, 'create_meter_device', MeterDevice::class, $meterDevice->id, null, $meterDevice->toArray(), $request);

                return ApiResponse::responseJson(true, 'Tạo đồng hồ thành công', 201, new MeterResource($meterDevice->load(['room.building', 'service', 'replacementMeter'])), 201);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

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
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(Request $request, int $meterDevice): JsonResponse
    {
        try {
            $validated = $request->validate([
                'room_id' => 'nullable|integer|exists:rooms,id',
                'room_number' => 'nullable|string|max:50',
                'service_id' => 'nullable|integer|exists:services,id',
                'meter_code' => 'nullable|string|max:100|unique:meter_devices,meter_code,'.$meterDevice,
                'meter_type' => 'nullable|integer|in:1,2',
                'initial_reading' => 'nullable|numeric|min:0',
                'installed_at' => 'nullable|date',
                'final_reading' => 'nullable|numeric|min:0',
                'status' => 'nullable|integer|in:1,2,3,4',
                'replaced_by_meter_id' => 'nullable|integer|exists:meter_devices,id',
                'note' => 'nullable|string|max:500',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
                'delete_image' => 'nullable|boolean',
            ]);

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

                $targetInitial = array_key_exists('initial_reading', $validated) ? $validated['initial_reading'] : $meterDeviceModel->initial_reading;
                $targetFinal = array_key_exists('final_reading', $validated) ? $validated['final_reading'] : $meterDeviceModel->final_reading;

                if ($targetFinal !== null && $targetInitial !== null && $targetFinal <= $targetInitial) {
                    return ApiResponse::responseJson(false, 'Chỉ số cuối phải lớn hơn chỉ số khởi tạo', 422, null, 422);
                }

                if (AdminScope::isBuildingManager($admin) && isset($validated['room_id']) && ! $this->roomBelongsToAdmin($validated['room_id'], $admin->id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền gán đồng hồ cho phòng này', 403, null, 403);
                }

                if (isset($validated['replaced_by_meter_id']) && $validated['replaced_by_meter_id'] === $meterDeviceModel->id) {
                    return ApiResponse::responseJson(false, 'Không thể chọn chính đồng hồ này làm đồng hồ thay thế', 422, null, 422);
                }

                if (isset($validated['status']) && $validated['status'] === MeterDevice::STATUS_REPLACED && empty($validated['replaced_by_meter_id'])) {
                    return ApiResponse::responseJson(false, 'Khi trạng thái là đã thay thế phải chọn đồng hồ thay thế', 422, null, 422);
                }

                $targetStatus = $validated['status'] ?? $meterDeviceModel->status;
                if ($targetStatus !== MeterDevice::STATUS_REPLACED) {
                    $roomId = $validated['room_id'] ?? $meterDeviceModel->room_id;
                    $serviceId = $validated['service_id'] ?? $meterDeviceModel->service_id;

                    if (MeterDevice::query()
                        ->where('room_id', $roomId)
                        ->where('service_id', $serviceId)
                        ->where('id', '!=', $meterDeviceModel->id)
                        ->where('status', '!=', MeterDevice::STATUS_REPLACED)
                        ->exists()) {
                        return ApiResponse::responseJson(false, 'Phòng này đã có đồng hồ cho dịch vụ này', 422, null, 422);
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

                foreach (['room_id', 'service_id', 'meter_code', 'meter_type', 'initial_reading', 'installed_at', 'final_reading', 'status', 'replaced_by_meter_id', 'note'] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $meterDeviceModel->{$field} = $validated[$field];
                    }
                }

                $meterDeviceModel->save();

                AdminActivityLogger::write($admin, 'update_meter_device', MeterDevice::class, $meterDeviceModel->id, $oldData, $meterDeviceModel->fresh()->toArray(), $request);

                return ApiResponse::responseJson(true, 'Cập nhật đồng hồ thành công', 200, new MeterResource($meterDeviceModel->load(['room.building', 'service', 'replacementMeter'])), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function updateStatus(Request $request, int $meterDevice): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|integer|in:1,2,3,4',
                'final_reading' => 'nullable|numeric|min:0',
                'replaced_by_meter_id' => 'nullable|integer|exists:meter_devices,id',
            ]);

            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật trạng thái đồng hồ', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $meterDevice, $admin, $request): JsonResponse {
                $meterDeviceModel = $this->accessibleQuery($admin)->lockForUpdate()->find($meterDevice);

                if (! $meterDeviceModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy đồng hồ', 404, null, 404);
                }

                $targetFinal = array_key_exists('final_reading', $validated) ? $validated['final_reading'] : $meterDeviceModel->final_reading;
                if ($targetFinal !== null && $meterDeviceModel->initial_reading !== null && $targetFinal <= $meterDeviceModel->initial_reading) {
                    return ApiResponse::responseJson(false, 'Chỉ số cuối phải lớn hơn chỉ số khởi tạo', 422, null, 422);
                }

                if ($validated['status'] === MeterDevice::STATUS_REPLACED && empty($validated['replaced_by_meter_id'])) {
                    return ApiResponse::responseJson(false, 'Khi đổi trạng thái sang đã thay thế phải có đồng hồ thay thế', 422, null, 422);
                }

                if (isset($validated['replaced_by_meter_id']) && $validated['replaced_by_meter_id'] === $meterDeviceModel->id) {
                    return ApiResponse::responseJson(false, 'Không thể chọn chính đồng hồ này làm đồng hồ thay thế', 422, null, 422);
                }

                if ($validated['status'] !== MeterDevice::STATUS_REPLACED) {
                    if (MeterDevice::query()
                        ->where('room_id', $meterDeviceModel->room_id)
                        ->where('service_id', $meterDeviceModel->service_id)
                        ->where('id', '!=', $meterDeviceModel->id)
                        ->where('status', '!=', MeterDevice::STATUS_REPLACED)
                        ->exists()) {
                        return ApiResponse::responseJson(false, 'Phòng này đã có đồng hồ cho dịch vụ này', 422, null, 422);
                    }
                }

                $oldData = $meterDeviceModel->toArray();
                $meterDeviceModel->status = $validated['status'];
                $meterDeviceModel->final_reading = $validated['final_reading'] ?? $meterDeviceModel->final_reading;
                $meterDeviceModel->replaced_by_meter_id = $validated['replaced_by_meter_id'] ?? $meterDeviceModel->replaced_by_meter_id;
                $meterDeviceModel->save();

                AdminActivityLogger::write($admin, 'update_meter_device_status', MeterDevice::class, $meterDeviceModel->id, $oldData, $meterDeviceModel->fresh()->toArray(), $request);

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái đồng hồ thành công', 200, new MeterResource($meterDeviceModel->load(['room.building', 'service', 'replacementMeter'])), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

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

                AdminActivityLogger::write($admin, 'delete_meter_device', MeterDevice::class, $meterDeviceModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa đồng hồ thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function accessibleQuery($admin): Builder
    {
        $query = MeterDevice::query();

        if (AdminScope::isBuildingManager($admin)) {
            $query->whereHas('room.building', fn (Builder $query) => $query->where('manager_admin_id', $admin->id));
        }

        return $query;
    }

    private function roomBelongsToAdmin(int $roomId, int $adminId): bool
    {
        return Room::query()
            ->whereKey($roomId)
            ->whereHas('building', fn (Builder $query) => $query->where('manager_admin_id', $adminId))
            ->exists();
    }

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
