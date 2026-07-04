<?php

namespace App\Console\Commands;

use App\Events\NotificationSent;
use App\Helpers\DecimalMoney;
use App\Helpers\DepositRefundExpenseHelper;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExecuteScheduledRoomTransfers extends Command
{
    protected $signature = 'room-transfers:execute-scheduled {--date=} {--code=}';

    protected $description = 'Thực hiện các lịch chuyển phòng đến hạn hằng ngày.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now('Asia/Ho_Chi_Minh')->startOfDay();

        $summary = $this->executeScheduledTransfers($date, $this->option('code') ?: null);

        $this->info("Đã xử lý lịch chuyển phòng: {$summary['executed']} thành công, {$summary['blocked']} bị chặn, {$summary['failed']} lỗi.");

        foreach ($summary['codes'] as $result) {
            $this->line(($result['transfer_code'] ?? 'N/A').' - '.($result['status'] ?? 'unknown').' - '.($result['message'] ?? ''));
        }

        return self::SUCCESS;
    }

    public function executeScheduledTransfers(?Carbon $date = null, ?string $transferCode = null): array
    {
        $date = ($date ?: now('Asia/Ho_Chi_Minh'))->copy()->startOfDay();

        $codes = RoomMovement::query()
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->whereDate('movement_date', '<=', $date->toDateString())
            ->when($transferCode !== null, fn ($query) => $query->where('transfer_code', $transferCode))
            ->whereNotNull('transfer_code')
            ->pluck('transfer_code')
            ->unique()
            ->values();

        $summary = ['executed' => 0, 'blocked' => 0, 'failed' => 0, 'codes' => []];

        foreach ($codes as $code) {
            try {
                $result = $this->executeTransferCode((string) $code);
                $summary[$result['status']]++;
                $summary['codes'][] = $result;
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['codes'][] = [
                    'transfer_code' => (string) $code,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    public function executeTransferCode(string $transferCode): array
    {
        return DB::transaction(function () use ($transferCode): array {
            $movements = RoomMovement::query()
                ->where('transfer_code', $transferCode)
                ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($movements->isEmpty()) {
                return ['transfer_code' => $transferCode, 'status' => 'blocked', 'message' => 'Không tìm thấy lịch chuyển phòng cần xử lý.'];
            }

            $sourceContract = $this->sourceContract((int) ($movements->first()->source_contract_id ?: $movements->first()->contract_id));
            $toRoom = Room::query()->with('building')->lockForUpdate()->find((int) $movements->first()->to_room_id);
            $tenantIds = $movements->pluck('tenant_id')->map(fn ($tenantId): int => (int) $tenantId)->unique()->values()->all();
            $movementDate = Carbon::parse($movements->first()->movement_date)->startOfDay();
            $payload = $movements->first()->scheduled_payload ?: [];

            if (! $sourceContract) {
                return $this->blockTransfer($movements, 'Không tìm thấy hợp đồng cũ đang hiệu lực để thực hiện chuyển phòng.');
            }

            if (! $toRoom) {
                return $this->blockTransfer($movements, 'Không tìm thấy phòng đích để thực hiện chuyển phòng.');
            }

            if ($this->hasUnpaidOldDebt($sourceContract, $movementDate)) {
                return $this->blockTransfer($movements, 'Hợp đồng/phòng cũ còn hóa đơn chưa thanh toán, vui lòng thanh toán hết nợ cũ trước khi chuyển phòng.');
            }

            $activeRows = $sourceContract->contractTenants()
                ->where('is_staying', true)
                ->whereNull('leave_date')
                ->with('tenant')
                ->lockForUpdate()
                ->get();

            $movingRows = $activeRows->whereIn('tenant_id', $tenantIds)->values();
            if ($movingRows->count() !== count($tenantIds)) {
                return $this->blockTransfer($movements, 'Một hoặc nhiều khách thuê không còn đang ở trong hợp đồng cũ.');
            }

            $validationMessage = $this->destinationValidationMessage($tenantIds, $toRoom);
            if ($validationMessage !== null) {
                return $this->blockTransfer($movements, $validationMessage);
            }

            $destinationActiveContract = $this->activeDestinationContract($toRoom);
            $remainingRows = $activeRows->whereNotIn('tenant_id', $tenantIds)->values();
            $representativeTenantId = $this->representativeTenantId($sourceContract, $activeRows);
            $movingRepresentative = in_array($representativeTenantId, $tenantIds, true);
            $movingAll = $remainingRows->isEmpty();
            $usesOldDepositSettlement = $movingAll;
            $oldBillingEndDate = $movementDate->copy()->subDay();
            $createdBy = (int) $sourceContract->created_by;
            $admin = Admin::query()->find($createdBy) ?: Admin::query()->first();

            if (! $admin) {
                return $this->blockTransfer($movements, 'Không tìm thấy admin tạo hợp đồng để xử lý chuyển phòng.');
            }

            $remainingContract = null;
            if ($movingRepresentative && ! $movingAll) {
                $remainingContract = $this->createRemainingContract($sourceContract, $remainingRows, $oldBillingEndDate, $admin);
            }

            $destinationContract = $destinationActiveContract ?: $this->createDestinationContract($sourceContract, $toRoom, $movingRows, $movementDate, $payload, $admin);
            $this->attachRowsToDestinationContract($destinationContract, $movingRows, $movementDate, $admin);
            $this->moveVehicles($sourceContract, $destinationContract, $tenantIds, $movementDate, $oldBillingEndDate);

            if ($remainingContract) {
                $this->moveRemainingVehicles($sourceContract, $remainingContract, $remainingRows->pluck('tenant_id')->map(fn ($id): int => (int) $id)->all(), $movementDate, $oldBillingEndDate);
            }

            $settlement = $this->settlement($sourceContract, $destinationContract, $payload, $toRoom, $destinationActiveContract !== null, $usesOldDepositSettlement);
            $this->writeDepositSettlement($sourceContract, $destinationContract, $settlement, $movementDate, $admin);
            $this->closeSourceContractRows($sourceContract, $movingRows, $activeRows, $oldBillingEndDate, $movingRepresentative, $movingAll);

            $sourceContract->refresh();
            if ($movingAll || $movingRepresentative) {
                $sourceContract->forceFill([
                    'status' => Contract::STATUS_LIQUIDATED,
                    'actual_end_date' => $oldBillingEndDate->toDateString(),
                ])->save();
            }

            $this->refreshRoomOccupants((int) $sourceContract->room_id);
            $this->refreshRoomOccupants((int) $toRoom->id);

            $movements->each(fn (RoomMovement $movement): bool => $movement->forceFill([
                'contract_id' => $destinationContract->id,
                'destination_contract_id' => $destinationContract->id,
                'status' => RoomMovement::STATUS_EXECUTED,
                'old_room_final_amount' => $settlement['old_balance'],
                'transfer_fee' => $settlement['transfer_fee'],
                'deposit_transfer_amount' => $settlement['transfer_amount'],
                'deposit_refund_amount' => '0.00',
                'deduction_amount' => $settlement['deduction_amount'],
                'manual_refund_amount' => $settlement['manual_refund_amount'],
                'deposit_due_amount' => $settlement['deposit_due_amount'],
                'extra_charge_amount' => $settlement['extra_charge_amount'],
                'settlement_due_amount' => $settlement['settlement_due_amount'],
                'settlement_paid_amount' => '0.00',
                'settlement_payment_status' => DecimalMoney::isPositive($settlement['settlement_due_amount'])
                    ? RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING
                    : RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
                'executed_at' => now(),
                'failure_reason' => null,
            ])->save());

            $notifications = $this->createTransferExecutedNotifications($movements->fresh(), $sourceContract, $destinationContract, $toRoom, $admin);

            if ($remainingContract) {
                $remainingNotifications = $remainingRows->map(fn ($row): Notification => Notification::query()->create([
                    'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                    'target_type' => Notification::TARGET_TYPE_TENANT,
                    'status' => Notification::STATUS_SENT,
                    'building_id' => $sourceContract->room?->building_id,
                    'room_id' => $sourceContract->room_id,
                    'tenant_id' => $row->tenant_id,
                    'title' => 'Hợp đồng mới được tạo',
                    'content' => "Hợp đồng mới mã {$remainingContract->contract_code} cho phòng {$sourceContract->room?->room_number} đã được tạo và đang chờ bạn ký.",
                    'published_at' => now(),
                    'created_by' => $admin->id,
                ]));
                $notifications = $notifications->merge($remainingNotifications);
            }

            DB::afterCommit(fn (): mixed => $this->broadcastNotifications($notifications));

            return [
                'transfer_code' => $transferCode,
                'status' => 'executed',
                'message' => 'Đã thực hiện chuyển phòng.',
                'destination_contract_id' => $destinationContract->id,
                'remaining_contract_id' => $remainingContract?->id,
                'settlement' => $settlement,
            ];
        });
    }

    private function sourceContract(int $contractId): ?Contract
    {
        $contract = Contract::query()
            ->with(['room.building', 'contractTenants.tenant', 'contractVehicles.vehicle', 'depositTransactions'])
            ->lockForUpdate()
            ->find($contractId);

        return $contract && (int) $contract->status === Contract::STATUS_ACTIVE ? $contract : null;
    }

    private function destinationValidationMessage(array $tenantIds, Room $toRoom): ?string
    {
        if ((int) $toRoom->status !== Room::STATUS_ACTIVE) {
            return 'Phòng đích đang không ở trạng thái cho thuê được.';
        }

        $destinationActiveContract = $this->activeDestinationContract($toRoom);
        $currentOccupants = $destinationActiveContract
            ? $this->activeTenantCount($destinationActiveContract->id)
            : (int) $toRoom->current_occupants;

        if ((int) $toRoom->max_occupants > 0 && $currentOccupants + count($tenantIds) > (int) $toRoom->max_occupants) {
            return 'Phòng đích đã vượt sức chứa tối đa, không thể chuyển vào.';
        }

        $tenants = Tenant::query()->whereIn('id', $tenantIds)->get();
        foreach ($tenants as $tenant) {
            if (! $toRoom->building?->allowsTenantGender($tenant->gender)) {
                return 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.';
            }
        }

        return null;
    }

    private function activeDestinationContract(Room $toRoom): ?Contract
    {
        return Contract::query()
            ->with(['room.building', 'contractTenants.tenant', 'contractVehicles.vehicle', 'depositTransactions'])
            ->where('room_id', $toRoom->id)
            ->where('status', Contract::STATUS_ACTIVE)
            ->lockForUpdate()
            ->first();
    }

    private function createDestinationContract(Contract $sourceContract, Room $toRoom, EloquentCollection $movingRows, Carbon $movementDate, array $payload, Admin $admin): Contract
    {
        return Contract::query()->create([
            'contract_code' => $this->generateContractCode($toRoom),
            'room_id' => $toRoom->id,
            'start_date' => $movementDate->toDateString(),
            'end_date' => $sourceContract->end_date?->toDateString(),
            'billing_cycle_day' => $sourceContract->billing_cycle_day,
            'room_price' => $toRoom->base_price,
            'deposit_amount' => $payload['new_deposit_amount'],
            'status' => Contract::STATUS_PENDING_SIGN,
            'payment_status' => DecimalMoney::isPositive($payload['new_deposit_amount'])
                ? Contract::PAYMENT_STATUS_PENDING
                : Contract::PAYMENT_STATUS_SUCCESS,
            'note' => $payload['note'],
            'created_by' => $admin->id,
            'parent_contract_id' => $sourceContract->parent_contract_id ?: $sourceContract->id,
        ]);
    }

    private function createRemainingContract(Contract $sourceContract, EloquentCollection $remainingRows, Carbon $oldBillingEndDate, Admin $admin): Contract
    {
        $contract = Contract::query()->create([
            'contract_code' => $this->generateContractCode($sourceContract->room),
            'room_id' => $sourceContract->room_id,
            'start_date' => $oldBillingEndDate->copy()->addDay()->toDateString(),
            'end_date' => $sourceContract->end_date?->toDateString(),
            'billing_cycle_day' => $sourceContract->billing_cycle_day,
            'room_price' => $sourceContract->room_price,
            'deposit_amount' => $sourceContract->deposit_amount,
            'status' => Contract::STATUS_PENDING_SIGN,
            'payment_status' => Contract::PAYMENT_STATUS_SUCCESS,
            'note' => 'Hợp đồng mới cho người còn ở lại sau khi đại diện chuyển phòng.',
            'created_by' => $admin->id,
            'parent_contract_id' => $sourceContract->parent_contract_id ?: $sourceContract->id,
        ]);

        foreach ($remainingRows as $row) {
            $contract->contractTenants()->create([
                'tenant_id' => $row->tenant_id,
                'join_date' => $oldBillingEndDate->copy()->addDay()->toDateString(),
                'billing_start_date' => $oldBillingEndDate->copy()->addDay()->toDateString(),
                'is_staying' => true,
                'created_by' => $admin->id,
            ]);
        }

        if (DecimalMoney::isPositive($contract->deposit_amount)) {
            $contract->depositTransactions()->create([
                'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_IN,
                'amount' => $contract->deposit_amount,
                'transaction_date' => $oldBillingEndDate->copy()->addDay()->toDateString(),
                'payment_method' => null,
                'note' => "Sao chép cọc từ hợp đồng #{$sourceContract->id} cho nhóm khách còn ở lại.",
                'created_by' => $admin->id,
            ]);
        }

        return $contract;
    }

    private function attachRowsToDestinationContract(Contract $destinationContract, EloquentCollection $movingRows, Carbon $movementDate, Admin $admin): void
    {
        foreach ($movingRows as $row) {
            $exists = ContractTenant::query()
                ->where('contract_id', $destinationContract->id)
                ->where('tenant_id', $row->tenant_id)
                ->where('is_staying', true)
                ->whereNull('leave_date')
                ->exists();

            if ($exists) {
                continue;
            }

            $destinationContract->contractTenants()->create([
                'tenant_id' => $row->tenant_id,
                'join_date' => $movementDate->toDateString(),
                'billing_start_date' => $movementDate->toDateString(),
                'is_staying' => true,
                'created_by' => $admin->id,
            ]);
        }
    }

    private function moveVehicles(Contract $sourceContract, Contract $destinationContract, array $tenantIds, Carbon $movementDate, Carbon $oldBillingEndDate): void
    {
        $vehicles = ContractVehicle::query()
            ->with('vehicle')
            ->where('contract_id', $sourceContract->id)
            ->where('is_active', true)
            ->whereHas('vehicle', fn ($query) => $query->whereIn('tenant_id', $tenantIds))
            ->lockForUpdate()
            ->get();

        foreach ($vehicles as $vehicle) {
            $vehicle->forceFill([
                'ended_at' => $oldBillingEndDate->toDateString(),
                'billing_end_date' => $oldBillingEndDate->toDateString(),
                'is_active' => false,
            ])->save();

            ContractVehicle::query()->create([
                'contract_id' => $destinationContract->id,
                'vehicle_id' => $vehicle->vehicle_id,
                'started_at' => $movementDate->toDateString(),
                'billing_start_date' => $movementDate->toDateString(),
                'monthly_fee' => $vehicle->monthly_fee,
                'charge_policy' => $vehicle->charge_policy,
                'is_active' => true,
            ]);
        }
    }

    private function moveRemainingVehicles(Contract $sourceContract, Contract $remainingContract, array $tenantIds, Carbon $movementDate, Carbon $oldBillingEndDate): void
    {
        $vehicles = ContractVehicle::query()
            ->with('vehicle')
            ->where('contract_id', $sourceContract->id)
            ->where('is_active', true)
            ->whereHas('vehicle', fn ($query) => $query->whereIn('tenant_id', $tenantIds))
            ->lockForUpdate()
            ->get();

        foreach ($vehicles as $vehicle) {
            $vehicle->forceFill([
                'ended_at' => $oldBillingEndDate->toDateString(),
                'billing_end_date' => $oldBillingEndDate->toDateString(),
                'is_active' => false,
            ])->save();

            ContractVehicle::query()->create([
                'contract_id' => $remainingContract->id,
                'vehicle_id' => $vehicle->vehicle_id,
                'started_at' => $movementDate->toDateString(),
                'billing_start_date' => $movementDate->toDateString(),
                'monthly_fee' => $vehicle->monthly_fee,
                'charge_policy' => $vehicle->charge_policy,
                'is_active' => true,
            ]);
        }
    }

    private function settlement(Contract $sourceContract, Contract $destinationContract, array $payload, Room $toRoom, bool $hasDestinationActiveContract, bool $usesOldDepositSettlement): array
    {
        $oldBalance = DecimalMoney::normalize($sourceContract->deposit_balance);
        $deductionAmount = DecimalMoney::normalize($payload['deposit_deduction_amount'] ?? '0');
        $transferFee = DecimalMoney::normalize($payload['transfer_fee'] ?? '0');
        $charges = DecimalMoney::add([$deductionAmount, $transferFee]);
        $newDepositAmount = DecimalMoney::normalize($payload['new_deposit_amount'] ?? $toRoom->base_price);

        if (! $usesOldDepositSettlement) {
            $depositDueAmount = $hasDestinationActiveContract ? '0.00' : DecimalMoney::maxZero(DecimalMoney::subtract($newDepositAmount, $destinationContract->deposit_balance));
            $extraChargeAmount = DecimalMoney::add([$deductionAmount, $transferFee]);

            return [
                'old_balance' => $oldBalance,
                'deduction_amount' => $deductionAmount,
                'transfer_fee' => $transferFee,
                'transfer_amount' => '0.00',
                'manual_refund_amount' => '0.00',
                'deposit_due_amount' => $depositDueAmount,
                'extra_charge_amount' => $extraChargeAmount,
                'settlement_due_amount' => DecimalMoney::add([$depositDueAmount, $extraChargeAmount]),
                'is_partial_transfer' => true,
            ];
        }

        $deductionFromDeposit = DecimalMoney::min($charges, $oldBalance);
        $availableAfterCharges = DecimalMoney::subtract($oldBalance, $charges);
        $positiveAvailable = DecimalMoney::maxZero($availableAfterCharges);
        $extraChargeAmount = DecimalMoney::maxZero(DecimalMoney::subtract('0', $availableAfterCharges));

        if ($hasDestinationActiveContract) {
            $transferAmount = '0.00';
            $manualRefundAmount = $positiveAvailable;
            $depositDueAmount = '0.00';
        } else {
            $transferAmount = DecimalMoney::min($positiveAvailable, $newDepositAmount);
            $manualRefundAmount = DecimalMoney::maxZero(DecimalMoney::subtract($positiveAvailable, $newDepositAmount));
            $depositDueAmount = DecimalMoney::maxZero(DecimalMoney::subtract($newDepositAmount, $positiveAvailable));
        }

        return [
            'old_balance' => $oldBalance,
            'deduction_amount' => $deductionFromDeposit,
            'transfer_fee' => $transferFee,
            'transfer_amount' => $transferAmount,
            'manual_refund_amount' => $manualRefundAmount,
            'deposit_due_amount' => $depositDueAmount,
            'extra_charge_amount' => $extraChargeAmount,
            'settlement_due_amount' => DecimalMoney::add([$depositDueAmount, $extraChargeAmount]),
        ];
    }

    private function writeDepositSettlement(Contract $sourceContract, Contract $destinationContract, array $settlement, Carbon $movementDate, Admin $admin): void
    {
        if (DecimalMoney::isPositive($settlement['deduction_amount']) && empty($settlement['is_partial_transfer'])) {
            $sourceContract->depositTransactions()->create([
                'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT,
                'amount' => $settlement['deduction_amount'],
                'transaction_date' => $movementDate->toDateString(),
                'payment_method' => null,
                'note' => 'Khấu trừ cọc khi chuyển phòng, gồm hư hỏng và phí chuyển phòng.',
                'created_by' => $admin->id,
            ]);
        }

        if (DecimalMoney::isPositive($settlement['transfer_amount'])) {
            $sourceContract->depositTransactions()->create([
                'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_OUT,
                'amount' => $settlement['transfer_amount'],
                'transaction_date' => $movementDate->toDateString(),
                'payment_method' => null,
                'note' => "Chuyển cọc sang hợp đồng #{$destinationContract->id}.",
                'created_by' => $admin->id,
            ]);

            $destinationContract->depositTransactions()->create([
                'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_IN,
                'amount' => $settlement['transfer_amount'],
                'transaction_date' => $movementDate->toDateString(),
                'payment_method' => null,
                'note' => "Nhận cọc chuyển từ hợp đồng #{$sourceContract->id}.",
                'created_by' => $admin->id,
            ]);
        }

        if (DecimalMoney::isPositive($settlement['manual_refund_amount'])) {
            $sourceContract->depositTransactions()->create([
                'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
                'amount' => $settlement['manual_refund_amount'],
                'transaction_date' => $movementDate->toDateString(),
                'payment_method' => null,
                'note' => "Hoàn cọc dư khi chuyển phòng sang hợp đồng #{$destinationContract->id}.",
                'created_by' => $admin->id,
            ]);

            DepositRefundExpenseHelper::createRefundExpense(
                contract: $sourceContract,
                amount: $settlement['manual_refund_amount'],
                date: $movementDate->toDateString(),
                paymentMethod: Expense::PAYMENT_METHOD_CASH,
                reason: 'Hoàn cọc dư khi chuyển phòng',
                createdBy: $admin->id,
            );
        }

        $destinationContract->refresh()->updatePaymentStatus();
    }

    private function closeSourceContractRows(Contract $sourceContract, EloquentCollection $movingRows, EloquentCollection $activeRows, Carbon $oldBillingEndDate, bool $movingRepresentative, bool $movingAll): void
    {
        $rowsToClose = ($movingAll || $movingRepresentative) ? $activeRows : $movingRows;

        foreach ($rowsToClose as $row) {
            $row->forceFill([
                'leave_date' => $oldBillingEndDate->toDateString(),
                'billing_end_date' => $oldBillingEndDate->toDateString(),
                'is_staying' => false,
            ])->save();
        }

        if ($movingAll || $movingRepresentative) {
            $sourceContract->contractVehicles()
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->each(fn (ContractVehicle $vehicle): bool => $vehicle->forceFill([
                    'ended_at' => $oldBillingEndDate->toDateString(),
                    'billing_end_date' => $oldBillingEndDate->toDateString(),
                    'is_active' => false,
                ])->save());
        }
    }

    private function blockTransfer(Collection $movements, string $reason): array
    {
        $movements->each(fn (RoomMovement $movement): bool => $movement->forceFill([
            'status' => RoomMovement::STATUS_BLOCKED,
            'failure_reason' => $reason,
        ])->save());

        $notifications = $this->createTransferBlockedNotifications($movements, $reason);
        DB::afterCommit(fn (): mixed => $this->broadcastNotifications($notifications));

        return [
            'transfer_code' => (string) $movements->first()->transfer_code,
            'status' => 'blocked',
            'message' => $reason,
        ];
    }

    private function hasUnpaidOldDebt(Contract $contract, Carbon $movementDate): bool
    {
        return Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->where(function ($query) use ($movementDate): void {
                $query->where('billing_year', '<', $movementDate->year)
                    ->orWhere(function ($sameYearQuery) use ($movementDate): void {
                        $sameYearQuery->where('billing_year', $movementDate->year)
                            ->where('billing_month', '<', $movementDate->month);
                    });
            })
            ->exists();
    }

    private function activeTenantCount(int $contractId): int
    {
        return ContractTenant::query()
            ->where('contract_id', $contractId)
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->distinct('tenant_id')
            ->count('tenant_id');
    }

    private function representativeTenantId(Contract $contract, EloquentCollection $activeRows): ?int
    {
        if ($contract->representative_tenant_id) {
            return (int) $contract->representative_tenant_id;
        }

        return $activeRows
            ->sortBy(fn (ContractTenant $row): string => ($row->join_date?->toDateString() ?? '9999-12-31').'-'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT))
            ->first()?->tenant_id;
    }

    private function refreshRoomOccupants(int $roomId): void
    {
        $occupants = ContractTenant::query()
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', fn ($query) => $query->where('room_id', $roomId)->where('status', Contract::STATUS_ACTIVE))
            ->distinct('tenant_id')
            ->count('tenant_id');

        Room::query()->whereKey($roomId)->update(['current_occupants' => $occupants]);
    }

    private function createTransferExecutedNotifications(Collection $movements, Contract $sourceContract, Contract $destinationContract, Room $toRoom, Admin $admin): Collection
    {
        return $movements->map(fn (RoomMovement $movement): Notification => Notification::query()->create([
            'title' => 'Chuyển phòng đã được xử lý',
            'content' => "Bạn đã được chuyển sang phòng {$toRoom->room_number}. ".($destinationContract->status === Contract::STATUS_PENDING_SIGN ? 'Hợp đồng mới đang chờ ký.' : 'Bạn đã được thêm vào hợp đồng phòng mới.'),
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'building_id' => $toRoom->building_id,
            'room_id' => $toRoom->id,
            'tenant_id' => $movement->tenant_id,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]));
    }

    private function createTransferBlockedNotifications(Collection $movements, string $reason): Collection
    {
        return $movements->map(fn (RoomMovement $movement): Notification => Notification::query()->create([
            'title' => 'Lịch chuyển phòng cần xử lý',
            'content' => "Lịch chuyển phòng {$movement->transfer_code} chưa thể thực hiện. Lý do: {$reason}",
            'notification_type' => Notification::NOTIFICATION_TYPE_WARNING,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'building_id' => $movement->toRoom?->building_id,
            'room_id' => $movement->to_room_id,
            'tenant_id' => $movement->tenant_id,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $movement->created_by,
        ]));
    }

    private function broadcastNotifications(Collection $notifications): void
    {
        $notifications->each(fn (Notification $notification): mixed => event(new NotificationSent($notification)));
    }

    private function generateContractCode(Room $room): string
    {
        $room->loadMissing('building');
        $buildingSlug = Str::upper(Str::slug($room->building->name ?? 'BUILDING'));
        $roomSlug = Str::upper(Str::slug($room->room_number ?? $room->slug ?? 'ROOM'));
        $baseCode = "HD-{$buildingSlug}-{$roomSlug}";
        $code = $baseCode;
        $counter = 1;

        while (Contract::query()->where('contract_code', $code)->exists()) {
            $counter++;
            $code = "{$baseCode}-{$counter}";
        }

        return $code;
    }
}
