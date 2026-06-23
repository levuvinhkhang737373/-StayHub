<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomMovement\IndexRequest;
use App\Http\Resources\Admin\RoomMovementResource;
use App\Models\Admin;
use App\Models\RoomMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomMovementController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewRoomMovements($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem lịch sử phòng và cọc', 403, null, 403);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem lịch sử của tòa nhà này', 403, null, 403);
            }

            $movements = $this->movementQuery($admin, $validated)
                ->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách lịch sử phòng và cọc', 200, $this->paginatedResource($movements), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $roomMovement): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewRoomMovements($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem lịch sử phòng và cọc', 403, null, 403);
            }

            $movement = $this->baseQuery($admin)->find($roomMovement);

            if (! $movement) {
                return ApiResponse::responseJson(false, 'Không tìm thấy lịch sử phòng và cọc', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết lịch sử phòng và cọc', 200, new RoomMovementResource($movement), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function movementQuery(Admin $admin, array $validated): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return $this->baseQuery($admin)
            ->when($keyword !== '', fn (Builder $query): Builder => $this->applyKeywordFilter($query, $keyword))
            ->when(isset($validated['movement_type']), fn (Builder $query): Builder => $query->where('movement_type', (int) $validated['movement_type']))
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $this->applyBuildingFilter($query, (int) $validated['building_id']))
            ->when(isset($validated['room_id']), fn (Builder $query): Builder => $query->where(function (Builder $roomQuery) use ($validated): void {
                $roomQuery->where('from_room_id', (int) $validated['room_id'])
                    ->orWhere('to_room_id', (int) $validated['room_id']);
            }))
            ->when(isset($validated['tenant_id']), fn (Builder $query): Builder => $query->where('tenant_id', (int) $validated['tenant_id']))
            ->when(isset($validated['contract_id']), fn (Builder $query): Builder => $query->where('contract_id', (int) $validated['contract_id']))
            ->when(isset($validated['date_from']), fn (Builder $query): Builder => $query->whereDate('movement_date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn (Builder $query): Builder => $query->whereDate('movement_date', '<=', $validated['date_to']))
            ->orderByDesc('movement_date')
            ->orderByDesc('id');
    }

    private function baseQuery(Admin $admin): Builder
    {
        $query = RoomMovement::query()
            ->with($this->relations());

        if (AdminScope::isSuperAdmin($admin)) {
            return $query;
        }

        return $query->where(function (Builder $scopeQuery) use ($admin): void {
            $scopeQuery->whereHas('fromRoom.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id))
                ->orWhereHas('toRoom.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
        });
    }

    private function applyKeywordFilter(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $keywordQuery) use ($keyword): void {
            $keywordQuery->where('note', 'like', "%{$keyword}%")
                ->orWhereHas('tenant', function (Builder $tenantQuery) use ($keyword): void {
                    $tenantQuery->where('full_name', 'like', "%{$keyword}%")
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                })
                ->orWhereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery->where('contract_code', 'like', "%{$keyword}%"))
                ->orWhereHas('fromRoom', fn (Builder $roomQuery): Builder => $this->applyRoomKeyword($roomQuery, $keyword))
                ->orWhereHas('toRoom', fn (Builder $roomQuery): Builder => $this->applyRoomKeyword($roomQuery, $keyword));
        });
    }

    private function applyRoomKeyword(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $roomQuery) use ($keyword): void {
            $roomQuery->where('room_number', 'like', "%{$keyword}%")
                ->orWhere('room_code', 'like', "%{$keyword}%")
                ->orWhereHas('building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('name', 'like', "%{$keyword}%"));
        });
    }

    private function applyBuildingFilter(Builder $query, int $buildingId): Builder
    {
        return $query->where(function (Builder $buildingQuery) use ($buildingId): void {
            $buildingQuery->whereHas('fromRoom', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $buildingId))
                ->orWhereHas('toRoom', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $buildingId));
        });
    }

    private function canViewRoomMovements(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    private function relations(): array
    {
        return [
            'tenant:id,username,full_name,phone,email',
            'contract:id,contract_code,room_id,status,payment_status',
            'fromRoom:id,building_id,room_number,floor,status',
            'fromRoom.building:id,name,slug,manager_admin_id,status',
            'toRoom:id,building_id,room_number,floor,status',
            'toRoom.building:id,name,slug,manager_admin_id,status',
            'creator:id,username,full_name,email,role,status',
        ];
    }

    private function paginatedResource(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => RoomMovementResource::collection($paginator->items())->resolve(),
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
