<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\ExecuteScheduledRoomTransfers;
use App\Events\NotificationSent;
use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomMovement\CancelTransferRequest;
use App\Http\Requests\Admin\RoomMovement\IndexRequest;
use App\Http\Requests\Admin\RoomMovement\SettlementCashPaymentRequest;
use App\Http\Requests\Admin\RoomMovement\UpdateTransferDateRequest;
use App\Http\Resources\Admin\RoomMovementResource;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
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
    // Danh sách các giao dịch chuyển phòng
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
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết giao dịch chuyển phòng
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
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật ngày thực hiện chuyển phòng
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

                $today = now('Asia/Ho_Chi_Minh')->startOfDay();
                if ($newDate->lt($today->copy()->addDay())) {
                    return ['error' => ApiResponse::responseJson(false, 'Ngày chuyển phòng phải từ ngày tiếp theo trở đi. Nếu muốn chuyển sang phòng khác ngay lập tức, vui lòng thực hiện thanh lý hợp đồng.', 422, null, 422)];
                }

                if ($movement->sourceContract && $movement->sourceContract->start_date) {
                    $minAllowedDate = $movement->sourceContract->start_date->copy()->startOfDay()->addDay();
                    if ($newDate->lt($minAllowedDate)) {
                        return ['error' => ApiResponse::responseJson(false, 'Ngày chuyển phòng phải sau ngày bắt đầu hợp đồng hiện tại ít nhất 1 ngày. Nếu muốn chuyển sang phòng khác ngay lập tức, vui lòng thực hiện thanh lý hợp đồng.', 422, null, 422)];
                    }
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
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Hủy lịch chuyển phòng chưa thực thi
    public function cancelTransfer(CancelTransferRequest $request, int $roomMovement): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewRoomMovements($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền hủy lịch chuyển phòng', 403, null, 403);
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
                    return ['error' => ApiResponse::responseJson(false, 'Chỉ được hủy lịch chuyển phòng', 422, null, 422)];
                }

                if ((int) $movement->status === RoomMovement::STATUS_CANCELLED) {
                    return ['error' => ApiResponse::responseJson(false, 'Lịch chuyển phòng đã được hủy trước đó', 422, null, 422)];
                }

                if (! in_array((int) $movement->status, [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED], true)) {
                    return ['error' => ApiResponse::responseJson(false, 'Chỉ được hủy lịch chuyển phòng chưa thực thi', 422, null, 422)];
                }

                $movements = $this->baseQuery($admin)
                    ->where('transfer_code', $movement->transfer_code)
                    ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
                    ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
                    ->lockForUpdate()
                    ->get();

                if ($movements->isEmpty()) {
                    return ['error' => ApiResponse::responseJson(false, 'Không tìm thấy lịch chuyển phòng có thể hủy', 404, null, 404)];
                }

                $movementIds = $movements->pluck('id');
                $note = trim((string) ($validated['note'] ?? ''));
                $cancelledAt = now('Asia/Ho_Chi_Minh')->toDateTimeString();
                $adminName = $admin->full_name ?: $admin->username;
                $reason = filled($note)
                    ? "Đã hủy bởi admin {$adminName}. Lý do: {$note}"
                    : "Đã hủy bởi admin {$adminName}.";

                $oldData = $movements->map(fn (RoomMovement $scheduledMovement): array => [
                    'id' => $scheduledMovement->id,
                    'status' => (int) $scheduledMovement->status,
                    'failure_reason' => $scheduledMovement->failure_reason,
                    'scheduled_payload' => $scheduledMovement->scheduled_payload,
                ])->values()->all();

                $movements->each(function (RoomMovement $scheduledMovement) use ($admin, $note, $cancelledAt, $reason): void {
                    $payload = $scheduledMovement->scheduled_payload ?? [];
                    $payload['cancelled_at'] = $cancelledAt;
                    $payload['cancelled_by'] = $admin->id;

                    if (filled($note)) {
                        $payload['cancel_note'] = $note;
                    } else {
                        unset($payload['cancel_note']);
                    }

                    $scheduledMovement->forceFill([
                        'status' => RoomMovement::STATUS_CANCELLED,
                        'failure_reason' => $reason,
                        'scheduled_payload' => $payload,
                    ])->save();
                });

                $freshMovements = $this->baseQuery($admin)
                    ->whereKey($movementIds)
                    ->orderBy('id')
                    ->get();

                AdminActivityLogger::write($admin, 'Hủy lịch chuyển phòng', RoomMovement::class, $movement->id, [
                    'transfer_code' => $movement->transfer_code,
                    'movements' => $oldData,
                ], [
                    'transfer_code' => $movement->transfer_code,
                    'status' => RoomMovement::STATUS_CANCELLED,
                    'note' => $note ?: null,
                    'cancelled_at' => $cancelledAt,
                    'movement_ids' => $movementIds->values()->all(),
                ], $request);

                $notifications = $this->createTransferCancelledNotifications($freshMovements, $admin, $note ?: null);

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

            return ApiResponse::responseJson(true, 'Hủy lịch chuyển phòng thành công', 200, [
                'transfer_code' => $result['transfer_code'],
                'movement' => new RoomMovementResource($result['movement']),
                'movements' => RoomMovementResource::collection($result['movements'])->resolve(),
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Ghi nhận thanh toán quyết toán bằng tiền mặt
    public function recordSettlementCashPayment(SettlementCashPaymentRequest $request, int $roomMovement): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewRoomMovements($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền thu tiền chuyển phòng', 403, null, 403);
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

                $validationError = $this->validateSettlementCashPayment($movement);
                if ($validationError) {
                    return ['error' => $validationError];
                }

                if (! $this->canCollectSettlementCashPayment($admin, $movement)) {
                    return ['error' => ApiResponse::responseJson(false, 'Chỉ admin của tòa nhà phòng đến hoặc super admin mới được thu tiền mặt chuyển phòng', 403, null, 403)];
                }

                $references = collect($movement->settlement_payment_references ?? []);
                $remainingAmount = $this->settlementRemainingAmount($movement);
                $destinationContract = $this->destinationContractForSettlement($movement);

                if (DecimalMoney::isPositive($this->settlementDepositRemainingAmount($movement, $references)) && ! $destinationContract) {
                    return ['error' => ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng đích để ghi nhận cọc chuyển phòng', 422, null, 422)];
                }

                $allocation = $this->allocateSettlementPayment($movement, $references, $remainingAmount, $destinationContract);
                $reference = $this->makeCashSettlementReference($movement, $remainingAmount, $allocation, $admin, $validated['note'] ?? null);

                $this->recordSettlementDepositCashPayment($destinationContract, $allocation['deposit_amount'], $reference['reference'], $validated['note'] ?? null, $admin);

                $updatedReferences = $references->push($reference)->values()->all();
                $settlementDueAmount = DecimalMoney::normalize($movement->settlement_due_amount);
                $transferMovements = $this->baseQuery($admin)
                    ->where('transfer_code', $movement->transfer_code)
                    ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
                    ->where('status', RoomMovement::STATUS_EXECUTED)
                    ->lockForUpdate()
                    ->get();

                $transferMovements->each(fn (RoomMovement $transferMovement): bool => $transferMovement->forceFill([
                    'settlement_paid_amount' => $settlementDueAmount,
                    'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
                    'settlement_payment_references' => $updatedReferences,
                ])->save());

                $freshMovements = $this->baseQuery($admin)
                    ->whereKey($transferMovements->pluck('id'))
                    ->orderBy('id')
                    ->get();

                AdminActivityLogger::write($admin, 'Thu tiền mặt chuyển phòng', RoomMovement::class, $movement->id, [
                    'transfer_code' => $movement->transfer_code,
                    'settlement_paid_amount' => (string) $movement->settlement_paid_amount,
                    'settlement_payment_status' => (int) $movement->settlement_payment_status,
                ], [
                    'transfer_code' => $movement->transfer_code,
                    'amount' => $remainingAmount,
                    'deposit_amount' => $allocation['deposit_amount'],
                    'extra_amount' => $allocation['extra_amount'],
                    'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_CASH,
                    'note' => $validated['note'] ?? null,
                ], $request);

                $notifications = $this->createSettlementCashPaymentNotifications($freshMovements, $remainingAmount, $admin);

                return [
                    'movement' => $freshMovements->firstWhere('id', $movement->id) ?: $freshMovements->first(),
                    'movements' => $freshMovements,
                    'transfer_code' => $movement->transfer_code,
                    'reference' => $reference,
                ];
            });

            if (isset($result['error'])) {
                return $result['error'];
            }

            $this->broadcastNotifications($notifications);

            return ApiResponse::responseJson(true, 'Thu tiền mặt chuyển phòng thành công', 200, [
                'transfer_code' => $result['transfer_code'],
                'movement' => new RoomMovementResource($result['movement']),
                'movements' => RoomMovementResource::collection($result['movements'])->resolve(),
                'reference' => $result['reference'],
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo câu lệnh truy vấn giao dịch chuyển phòng
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

    // Tự động thực hiện các giao dịch chuyển phòng đến hạn hôm nay
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

    // Truy vấn gốc cho giao dịch chuyển phòng
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

    // Áp dụng bộ lọc từ khóa tìm kiếm
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

    // Bộ lọc từ khóa theo tên phòng
    private function applyRoomKeyword(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $roomQuery) use ($keyword): void {
            $roomQuery->where('room_number', 'like', "%{$keyword}%")
                ->orWhere('slug', 'like', "%{$keyword}%")
                ->orWhereHas('building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('name', 'like', "%{$keyword}%"));
        });
    }

    // Áp dụng bộ lọc theo tòa nhà
    private function applyBuildingFilter(Builder $query, int $buildingId): Builder
    {
        return $query->where(function (Builder $buildingQuery) use ($buildingId): void {
            $buildingQuery->whereHas('fromRoom', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $buildingId))
                ->orWhereHas('toRoom', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $buildingId));
        });
    }

    // Kiểm tra quyền xem giao dịch chuyển phòng của admin
    private function canViewRoomMovements(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    // Xác thực dữ liệu thanh toán quyết toán tiền mặt
    private function validateSettlementCashPayment(RoomMovement $movement): ?JsonResponse
    {
        if ((int) $movement->movement_type !== RoomMovement::MOVEMENT_TYPE_TRANSFER || blank($movement->transfer_code)) {
            return ApiResponse::responseJson(false, 'Chỉ được thu tiền cho lịch chuyển phòng', 422, null, 422);
        }

        if ((int) $movement->status !== RoomMovement::STATUS_EXECUTED) {
            return ApiResponse::responseJson(false, 'Chỉ được thu tiền cho lịch chuyển phòng đã thực thi', 422, null, 422);
        }

        if (! in_array((int) $movement->settlement_payment_status, [
            RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
            RoomMovement::SETTLEMENT_PAYMENT_STATUS_PARTIAL,
        ], true)) {
            return ApiResponse::responseJson(false, 'Khoản chuyển phòng đã được thanh toán đủ', 422, null, 422);
        }

        if (! DecimalMoney::isPositive($this->settlementRemainingAmount($movement))) {
            return ApiResponse::responseJson(false, 'Khoản chuyển phòng đã được thanh toán đủ', 422, null, 422);
        }

        return null;
    }

    // Kiểm tra quyền thu tiền quyết toán của admin
    private function canCollectSettlementCashPayment(Admin $admin, RoomMovement $movement): bool
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return true;
        }

        $destinationBuildingId = $movement->toRoom?->building_id;

        return $destinationBuildingId !== null
            && AdminScope::ensureBuildingAccess($admin, (int) $destinationBuildingId);
    }

    // Tính số tiền quyết toán còn lại cần thu
    private function settlementRemainingAmount(RoomMovement $movement): string
    {
        return DecimalMoney::maxZero(DecimalMoney::subtract($movement->settlement_due_amount ?? '0', $movement->settlement_paid_amount ?? '0'));
    }

    // Tìm hợp đồng đích phục vụ quyết toán chuyển phòng
    private function destinationContractForSettlement(RoomMovement $movement): ?Contract
    {
        if (! $movement->destination_contract_id) {
            return null;
        }

        return Contract::query()->lockForUpdate()->find($movement->destination_contract_id);
    }

    // Phân bổ tiền thanh toán quyết toán cho các khoản nợ chuyển phòng
    private function allocateSettlementPayment(RoomMovement $movement, Collection $references, string $appliedAmount, ?Contract $destinationContract): array
    {
        $depositRemaining = $this->settlementDepositRemainingAmount($movement, $references);
        $depositAmount = $destinationContract
            ? DecimalMoney::min($appliedAmount, $depositRemaining)
            : '0.00';

        return [
            'deposit_amount' => $depositAmount,
            'extra_amount' => DecimalMoney::maxZero(DecimalMoney::subtract($appliedAmount, $depositAmount)),
        ];
    }

    // Tính số tiền cọc quyết toán còn lại
    private function settlementDepositRemainingAmount(RoomMovement $movement, Collection $references): string
    {
        $depositPaidBySettlement = DecimalMoney::add($references->pluck('deposit_amount')->all());

        return DecimalMoney::maxZero(DecimalMoney::subtract($movement->deposit_due_amount ?? '0', $depositPaidBySettlement));
    }

    // Tạo mã tham chiếu thanh toán quyết toán tiền mặt
    private function makeCashSettlementReference(RoomMovement $movement, string $amount, array $allocation, Admin $admin, ?string $note): array
    {
        $paidAt = now();
        $safeTransferCode = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $movement->transfer_code) ?: (string) $movement->id;

        return [
            'reference' => 'CASH-'.$safeTransferCode.'-'.$paidAt->format('YmdHis'),
            'amount' => DecimalMoney::normalize($amount),
            'deposit_amount' => DecimalMoney::normalize($allocation['deposit_amount']),
            'extra_amount' => DecimalMoney::normalize($allocation['extra_amount']),
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_CASH,
            'paid_at' => $paidAt->toDateTimeString(),
            'collected_by' => $admin->id,
            'collector_name' => $admin->full_name ?: $admin->username,
            'note' => $note,
        ];
    }

    // Ghi nhận thanh toán cọc quyết toán bằng tiền mặt
    private function recordSettlementDepositCashPayment(?Contract $destinationContract, string $depositAmount, string $reference, ?string $note, Admin $admin): void
    {
        if (! $destinationContract || ! DecimalMoney::isPositive($depositAmount)) {
            return;
        }

        $destinationContract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => $depositAmount,
            'transaction_date' => now()->toDateString(),
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_CASH,
            'transaction_reference' => $reference,
            'note' => filled($note)
                ? 'Thu cọc chuyển phòng bằng tiền mặt. Ghi chú: '.$note
                : 'Thu cọc chuyển phòng bằng tiền mặt.',
            'created_by' => $admin->id,
        ]);
    }

    // Tạo thông báo xác nhận thanh toán quyết toán tiền mặt
    private function createSettlementCashPaymentNotifications(EloquentCollection $movements, string $amount, Admin $admin): Collection
    {
        $amountText = number_format(DecimalMoney::toIntegerAmount($amount), 0, ',', '.').' VND';
        $firstMovement = $movements->first();
        $room = $firstMovement?->toRoom;
        $building = $room?->building;

        $tenantNotifications = $movements
            ->unique('tenant_id')
            ->map(fn (RoomMovement $movement): Notification => Notification::query()->create([
                'title' => 'Thanh toán chuyển phòng thành công',
                'content' => "Admin {$admin->full_name} đã ghi nhận tiền mặt {$amountText} cho mã chuyển phòng {$movement->transfer_code}. Trạng thái: Đã thanh toán.",
                'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'action_url' => '/admin/room-movements?movement_id=' . $movement->id,
                'building_id' => $movement->toRoom?->building_id,
                'room_id' => $movement->to_room_id,
                'tenant_id' => $movement->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]));

        $adminNotification = Notification::query()->create([
            'title' => 'Thu tiền mặt chuyển phòng',
            'content' => 'Mã chuyển phòng '.($firstMovement?->transfer_code ?? 'không rõ').' tại phòng '.($room?->room_number ?? 'chưa rõ').' - tòa nhà '.($building?->name ?? 'chưa rõ')." đã thu tiền mặt {$amountText}.",
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'action_url' => $firstMovement ? '/admin/room-movements?movement_id=' . $firstMovement->id : '/admin/room-movements',
            'building_id' => $room?->building_id,
            'room_id' => $room?->id,
            'target_admin_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }

    // Tạo thông báo realtime khi hủy lịch chuyển phòng
    private function createTransferCancelledNotifications(EloquentCollection $movements, Admin $admin, ?string $note): Collection
    {
        $firstMovement = $movements->first();
        $transferCode = (string) $firstMovement?->transfer_code;
        $fromRoom = $firstMovement?->fromRoom?->room_number ?? 'phòng cũ';
        $toRoom = $firstMovement?->toRoom?->room_number ?? 'phòng mới';
        $movementDateText = $firstMovement?->movement_date?->format('d/m/Y') ?? 'chưa rõ';
        $noteText = filled($note) ? " Lý do: {$note}" : '';
        $adminName = $admin->full_name ?: $admin->username;

        $tenantNotifications = $movements
            ->unique('tenant_id')
            ->map(fn (RoomMovement $movement): Notification => Notification::query()->create([
                'title' => 'Lịch chuyển phòng đã bị hủy',
                'content' => "Lịch chuyển phòng {$transferCode} từ {$fromRoom} sang {$toRoom} ngày {$movementDateText} đã bị hủy.{$noteText}",
                'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'action_url' => '/admin/room-movements?movement_id=' . $movement->id,
                'building_id' => $movement->toRoom?->building_id ?? $movement->fromRoom?->building_id,
                'room_id' => $movement->to_room_id ?? $movement->from_room_id,
                'tenant_id' => $movement->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]));

        $adminNotification = Notification::query()->create([
            'title' => 'Admin đã hủy lịch chuyển phòng',
            'content' => "Admin {$adminName} đã hủy lịch chuyển phòng {$transferCode} từ {$fromRoom} sang {$toRoom} ngày {$movementDateText}.{$noteText}",
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'action_url' => $firstMovement ? '/admin/room-movements?movement_id=' . $firstMovement->id : '/admin/room-movements',
            'building_id' => $firstMovement?->toRoom?->building_id ?? $firstMovement?->fromRoom?->building_id,
            'room_id' => $firstMovement?->to_room_id ?? $firstMovement?->from_room_id,
            'target_admin_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }

    // Tạo thông báo khi thay đổi ngày chuyển phòng
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
                'action_url' => '/admin/room-movements?movement_id=' . $movement->id,
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
            'action_url' => $firstMovement ? '/admin/room-movements?movement_id=' . $firstMovement->id : '/admin/room-movements',
            'building_id' => $firstMovement?->toRoom?->building_id ?? $firstMovement?->fromRoom?->building_id,
            'room_id' => $firstMovement?->to_room_id,
            'target_admin_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }

    // Phát các thông báo realtime
    private function broadcastNotifications(Collection $notifications): void
    {
        $notifications->each(fn (Notification $notification): mixed => event(new NotificationSent($notification)));
    }

    // Các quan hệ liên kết của giao dịch chuyển phòng
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

    // Định dạng dữ liệu giao dịch chuyển phòng phân trang
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
