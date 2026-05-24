<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomType\IndexRequest;
use App\Http\Requests\Admin\RoomType\RegisterRequest;
use App\Http\Requests\Admin\RoomType\StatusRequest;
use App\Http\Requests\Admin\RoomType\UpdateRequest;
use App\Http\Resources\Admin\RoomTypeDetailResource;
use App\Http\Resources\Admin\RoomTypeResource;
use App\Models\Admin;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomTypeController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseRoomTypes($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem loại phòng', 403, null, 403);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem loại phòng của tòa nhà này', 403, null, 403);
            }

            $roomTypes = $this->queryRoomTypes($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách loại phòng', 200, RoomTypeResource::collection($roomTypes), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseRoomTypes($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo loại phòng', 403, null, 403);
            }

            if (! $this->canWriteRoomTypeForBuilding($admin, $validated['building_id'] ?? null)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo loại phòng cho tòa nhà này', 403, null, 403);
            }

            if ($this->roomTypeNameExists($validated['name'], $validated['building_id'] ?? null)) {
                return ApiResponse::responseJson(false, 'Tên loại phòng đã tồn tại trong phạm vi tòa nhà này', 422, null, 422);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $roomType = RoomType::query()->create($this->payload($validated, $admin->id));

                AdminActivityLogger::write($admin, 'create_room_type', RoomType::class, $roomType->id, null, $roomType->toArray(), $request);

                $roomType->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Tạo loại phòng thành công', 201, new RoomTypeDetailResource($roomType), 201);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $roomType): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseRoomTypes($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem loại phòng', 403, null, 403);
            }

            $roomTypeModel = $this->accessibleQuery($admin)
                ->select($this->columns())
                ->with($this->detailRelations())
                ->withCount($this->counts())
                ->find($roomType);

            if (! $roomTypeModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy loại phòng', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết loại phòng', 200, new RoomTypeDetailResource($roomTypeModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $roomType): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseRoomTypes($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật loại phòng', 403, null, 403);
            }

            if (array_key_exists('building_id', $validated) && ! $this->canWriteRoomTypeForBuilding($admin, $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền chuyển loại phòng sang tòa nhà này', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $roomType, $admin, $request): JsonResponse {
                $roomTypeModel = $this->accessibleQuery($admin)->lockForUpdate()->find($roomType);

                if (! $roomTypeModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy loại phòng', 404, null, 404);
                }

                if (! $this->canWriteRoomTypeForBuilding($admin, $roomTypeModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật loại phòng này', 403, null, 403);
                }

                $targetBuildingId = array_key_exists('building_id', $validated) ? $validated['building_id'] : $roomTypeModel->building_id;
                $targetName = $validated['name'] ?? $roomTypeModel->name;

                if ($this->roomTypeNameExists($targetName, $targetBuildingId, $roomTypeModel->id)) {
                    return ApiResponse::responseJson(false, 'Tên loại phòng đã tồn tại trong phạm vi tòa nhà này', 422, null, 422);
                }

                $oldData = $roomTypeModel->toArray();
                $roomTypeModel->fill($this->payload($validated, null, true))->save();

                AdminActivityLogger::write($admin, 'update_room_type', RoomType::class, $roomTypeModel->id, $oldData, $roomTypeModel->fresh()->toArray(), $request);

                $roomTypeModel->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật loại phòng thành công', 200, new RoomTypeDetailResource($roomTypeModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function updateStatus(StatusRequest $request, int $roomType): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseRoomTypes($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái loại phòng', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $roomType, $admin, $request): JsonResponse {
                $roomTypeModel = $this->accessibleQuery($admin)->lockForUpdate()->find($roomType);

                if (! $roomTypeModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy loại phòng', 404, null, 404);
                }

                if (! $this->canWriteRoomTypeForBuilding($admin, $roomTypeModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái loại phòng này', 403, null, 403);
                }

                $oldData = $roomTypeModel->toArray();
                $roomTypeModel->forceFill(['status' => $validated['status']])->save();

                AdminActivityLogger::write($admin, 'update_room_type_status', RoomType::class, $roomTypeModel->id, $oldData, $roomTypeModel->fresh()->toArray(), $request);

                $roomTypeModel->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái loại phòng thành công', 200, new RoomTypeDetailResource($roomTypeModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function destroy(Request $request, int $roomType): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseRoomTypes($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa loại phòng', 403, null, 403);
            }

            $response = DB::transaction(function () use ($roomType, $admin, $request): JsonResponse {
                $roomTypeModel = $this->accessibleQuery($admin)->withCount($this->counts())->lockForUpdate()->find($roomType);

                if (! $roomTypeModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy loại phòng', 404, null, 404);
                }

                if (! $this->canWriteRoomTypeForBuilding($admin, $roomTypeModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền xóa loại phòng này', 403, null, 403);
                }

                if ((int) $roomTypeModel->rooms_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể xóa loại phòng đang được gán cho phòng', 422, null, 422);
                }

                $oldData = $roomTypeModel->toArray();
                $roomTypeModel->delete();

                AdminActivityLogger::write($admin, 'delete_room_type', RoomType::class, $roomTypeModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa loại phòng thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function payload(array $validated, ?int $createdBy = null, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['name', 'building_id', 'default_price', 'description', 'status'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! $isUpdate) {
            $payload['created_by'] = $createdBy;
            $payload['status'] = $payload['status'] ?? RoomType::STATUS_ACTIVE;
        }

        return $payload;
    }

    private function columns(): array
    {
        return ['id', 'name', 'slug', 'building_id', 'default_price', 'description', 'status', 'created_by', 'created_at', 'updated_at'];
    }

    private function listRelations(): array
    {
        return ['building:id,name,slug,status', 'creator:id,full_name'];
    }

    private function storeRelations(): array
    {
        return ['building:id,name,slug,status', 'creator:id,full_name'];
    }

    private function detailRelations(): array
    {
        return ['building:id,name,slug,status', 'creator:id,full_name', 'rooms:id,room_type_id,building_id,room_number,slug,base_price,max_occupants,current_occupants,status', 'rooms.building:id,name,slug'];
    }

    private function counts(): array
    {
        return ['rooms'];
    }

    private function queryRoomTypes(array $validated, Admin $admin): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return $this->accessibleQuery($admin)
            ->select($this->columns())
            ->with($this->listRelations())
            ->withCount($this->counts())
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            }))
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $query->where('building_id', $validated['building_id']))
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', $validated['status']))
            ->when(isset($validated['min_price']), fn (Builder $query): Builder => $query->where('default_price', '>=', $validated['min_price']))
            ->when(isset($validated['max_price']), fn (Builder $query): Builder => $query->where('default_price', '<=', $validated['max_price']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function accessibleQuery(Admin $admin): Builder
    {
        $query = RoomType::query();

        if (AdminScope::isSuperAdmin($admin)) {
            return $query;
        }

        if (AdminScope::isBuildingManager($admin)) {
            return $query->where(function (Builder $scopeQuery) use ($admin): void {
                $scopeQuery->whereNull('room_types.building_id')
                    ->orWhereHas('building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function canUseRoomTypes(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    private function roomTypeNameExists(string $name, mixed $buildingId, ?int $ignoreId = null): bool
    {
        return RoomType::query()
            ->where('name', $name)
            ->where('building_id', $buildingId)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function canWriteRoomTypeForBuilding(Admin $admin, mixed $buildingId): bool
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return true;
        }

        if (! AdminScope::isBuildingManager($admin) || empty($buildingId)) {
            return false;
        }

        return AdminScope::ensureBuildingAccess($admin, (int) $buildingId);
    }
}
