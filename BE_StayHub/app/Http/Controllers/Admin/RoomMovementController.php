<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\ExecuteScheduledRoomTransfers;
use App\Events\NotificationSent;
use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomMovement\IndexRequest;
use App\Http\Requests\Admin\RoomMovement\UpdateTransferDateRequest;
use App\Http\Resources\Admin\RoomMovementResource;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\RoomMovement;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function updateTransferDate(UpdateTransferDateRequest $request, int $roomMovement): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewRoomMovements($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền chỉnh lịch chuyển phòng', 403, null, 403);
            }

            $notifications = collect();

            $result = DB::transaction(function () use ($validated, $admin, $request, $roomMovement, &$notifications): array {
                $movement = $this->baseQuery($admin)
                    ->whereKey($roomMovement)
                    ->lockForUpdate()
                    ->first();

                if (! $movement) {
                    return ['error' => ApiResponse::responseJson(false, 'Không tìm thấy lịch chuyển phòng', 404, null, 404)];
                }

                if ((int) $movement->movement_type !== RoomMovement::MOVEMENT_TYPE_TRANSFER || blank($movement->transfer_code)) {
                    return ['error' => ApiResponse::responseJson(false, 'Chỉ được đổi ngày cho lịch chuyển phòng', 422, null, 422)];
                }

                if (! in_array((int) $movement->status, [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED], true)) {
                    return ['error' => ApiResponse::responseJson(false, 'Chỉ được đổi ngày cho lịch chuyển phòng chưa thực thi', 422, null, 422)];
                }

                $oldDate = $movement->movement_date?->copy()->startOfDay();
                $newDate = Carbon::parse($validated['movement_date'])->startOfDay();

                if ($oldDate && $oldDate->toDateString() === $newDate->toDateString()) {
                    return ['error' => ApiResponse::responseJson(false, 'Ngày chuyển mới phải khác ngày hiện tại của lịch chuyển', 422, null, 422)];
                }

                $movements = $this->baseQuery($admin)
                    ->where('transfer_code', $movement->transfer_code)
                    ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
                    ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
                    ->lockForUpdate()
                    ->get();
                $movementIds = $movements->pluck('id');

                $movements->each(function (RoomMovement $scheduledMovement) use ($validated, $newDate): void {
                    $payload = $scheduledMovement->scheduled_payload ?? [];
                    $payload['movement_date'] = $newDate->toDateString();

                    if (filled($validated['note'] ?? null)) {
                        $payload['reschedule_note'] = $validated['note'];
                    } else {
                        unset($payload['reschedule_note']);
                    }

                    $scheduledMovement->forceFill([
                        'movement_date' => $newDate->toDateTimeString(),
                        'scheduled_payload' => $payload,
                        'failure_reason' => null,
                        'status' => RoomMovement::STATUS_PENDING,
                    ])->save();
                });

                $freshMovements = $this->baseQuery($admin)
                    ->whereKey($movementIds)
                    ->orderBy('id')
                    ->get();

                AdminActivityLogger::write($admin, 'Đổi ngày chuyển phòng', RoomMovement::class, $movement->id, [
                    'transfer_code' => $movement->transfer_code,
                    'old_movement_date' => $oldDate?->toDateString(),
                ], [
                    'transfer_code' => $movement->transfer_code,
                    'new_movement_date' => $newDate->toDateString(),
                    'note' => $validated['note'] ?? null,
                ], $request);

                $notifications = $this->createTransferDateChangedNotifications($freshMovements, $oldDate, $newDate, $admin, $validated['note'] ?? null);

                return [
                    'movement' => $freshMovements->firstWhere('id', $movement->id) ?: $freshMovements->first(),
                    'movements' => $freshMovements,
                    'transfer_code' => $movement->transfer_code,
                ];
            });

            if (isset($result['error'])) {
                return $result['error'];
            }

            $this->broadcastNotifications($notifications);
            $result = $this->executeTodayTransferIfNeeded($result, $admin);

            return ApiResponse::responseJson(true, 'Đổi ngày chuyển phòng thành công', 200, [
                'transfer_code' => $result['transfer_code'],
                'movement' => new RoomMovementResource($result['movement']),
                'movements' => RoomMovementResource::collection($result['movements'])->resolve(),
                'execute_result' => $result['execute_result'] ?? null,
                'executed_immediately' => $result['executed_immediately'] ?? false,
                'blocked_immediately' => $result['blocked_immediately'] ?? false,
            ], 200);
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
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', (int) $validated['status']))
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

    private function executeTodayTransferIfNeeded(array $result, Admin $admin): array
    {
        $movement = $result['movement'] ?? null;

        if (! $movement instanceof RoomMovement || ! $movement->movement_date?->isSameDay(now('Asia/Ho_Chi_Minh'))) {
            return $result;
        }

        $executeResult = app(ExecuteScheduledRoomTransfers::class)->executeTransferCode((string) $result['transfer_code']);
        $movements = $this->baseQuery($admin)
            ->where('transfer_code', $result['transfer_code'])
            ->orderBy('id')
            ->get();

        return array_merge($result, [
            'movement' => $movements->firstWhere('id', $movement->id) ?: $movements->first(),
            'movements' => $movements,
            'execute_result' => $executeResult,
            'executed_immediately' => $executeResult['status'] === 'executed',
            'blocked_immediately' => $executeResult['status'] === 'blocked',
        ]);
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
                ->orWhere('transfer_code', 'like', "%{$keyword}%")
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

    private function createTransferDateChangedNotifications(EloquentCollection $movements, ?Carbon $oldDate, Carbon $newDate, Admin $admin, ?string $note): Collection
    {
        $firstMovement = $movements->first();
        $transferCode = (string) $firstMovement?->transfer_code;
        $fromRoom = $firstMovement?->fromRoom?->room_number ?? 'phòng cũ';
        $toRoom = $firstMovement?->toRoom?->room_number ?? 'phòng mới';
        $oldDateText = $oldDate?->format('d/m/Y') ?? 'chưa rõ';
        $newDateText = $newDate->format('d/m/Y');
        $noteText = filled($note) ? " Ghi chú: {$note}" : '';

        $tenantNotifications = $movements
            ->unique('tenant_id')
            ->map(fn (RoomMovement $movement): Notification => Notification::query()->create([
                'title' => 'Ngày chuyển phòng đã thay đổi',
                'content' => "Lịch chuyển phòng {$transferCode} từ {$fromRoom} sang {$toRoom} đã đổi từ {$oldDateText} sang {$newDateText}.{$noteText}",
                'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'building_id' => $movement->toRoom?->building_id ?? $movement->fromRoom?->building_id,
                'room_id' => $movement->to_room_id,
                'tenant_id' => $movement->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]));

        $adminNotification = Notification::query()->create([
            'title' => 'Lịch chuyển phòng đã đổi ngày',
            'content' => "Admin {$admin->full_name} đã đổi lịch chuyển phòng {$transferCode} từ {$oldDateText} sang {$newDateText}.{$noteText}",
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'building_id' => $firstMovement?->toRoom?->building_id ?? $firstMovement?->fromRoom?->building_id,
            'room_id' => $firstMovement?->to_room_id,
            'target_admin_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }

    private function broadcastNotifications(Collection $notifications): void
    {
        $notifications->each(fn (Notification $notification): mixed => event(new NotificationSent($notification)));
    }

    private function relations(): array
    {
        return [
            'tenant:id,username,full_name,phone,email',
            'contract:id,contract_code,room_id,status,payment_status',
            'sourceContract:id,contract_code,room_id,status,payment_status',
            'destinationContract:id,contract_code,room_id,status,payment_status',
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
