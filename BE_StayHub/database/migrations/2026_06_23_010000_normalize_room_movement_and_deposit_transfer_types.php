<?php

use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\RoomMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('room_movements')
            ->whereNotNull('to_room_id')
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_CHECKOUT)
            ->update(['movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER]);

        DB::table('contract_deposit_transactions')
            ->where('note', 'Trừ cọc khi chuyển phòng.')
            ->update(['transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT]);

        DB::table('contract_deposit_transactions')
            ->where('note', 'like', 'Chuyển cọc sang hợp đồng #%')
            ->update(['transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_OUT]);

        DB::table('contract_deposit_transactions')
            ->where('note', 'like', 'Nhận cọc chuyển từ hợp đồng #%')
            ->update(['transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_IN]);

        DB::table('contract_deposit_transactions')
            ->where('note', 'like', 'Kết chuyển cọc sang hợp đồng gia hạn ID %')
            ->update(['transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_OUT]);

        $this->recalculatePaymentStatus([
            ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_IN,
        ]);
    }

    public function down(): void
    {
        DB::table('room_movements')
            ->whereNotNull('to_room_id')
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->update(['movement_type' => RoomMovement::MOVEMENT_TYPE_CHECKOUT]);

        DB::table('contract_deposit_transactions')
            ->where('note', 'Trừ cọc khi chuyển phòng.')
            ->update(['transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_IN]);

        DB::table('contract_deposit_transactions')
            ->where('note', 'like', 'Chuyển cọc sang hợp đồng #%')
            ->update(['transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT]);

        DB::table('contract_deposit_transactions')
            ->where('note', 'like', 'Nhận cọc chuyển từ hợp đồng #%')
            ->update(['transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_OUT]);

        DB::table('contract_deposit_transactions')
            ->where('note', 'like', 'Kết chuyển cọc sang hợp đồng gia hạn ID %')
            ->update(['transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT]);

        $this->recalculatePaymentStatus([
            ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_IN,
        ]);
    }

    private function recalculatePaymentStatus(array $positiveTypes): void
    {
        DB::table('contracts')
            ->select(['id', 'deposit_amount'])
            ->whereIn('payment_status', [Contract::PAYMENT_STATUS_PENDING, Contract::PAYMENT_STATUS_SUCCESS])
            ->orderBy('id')
            ->chunkById(200, function ($contracts) use ($positiveTypes): void {
                foreach ($contracts as $contract) {
                    $required = (float) $contract->deposit_amount;
                    $balance = DB::table('contract_deposit_transactions')
                        ->where('contract_id', $contract->id)
                        ->get(['transaction_type', 'amount'])
                        ->reduce(function (float $carry, object $transaction) use ($positiveTypes): float {
                            $amount = (float) $transaction->amount;

                            return in_array((int) $transaction->transaction_type, $positiveTypes, true)
                                ? $carry + $amount
                                : $carry - $amount;
                        }, 0.0);

                    DB::table('contracts')
                        ->where('id', $contract->id)
                        ->update([
                            'payment_status' => $required <= 0 || $balance >= $required
                                ? Contract::PAYMENT_STATUS_SUCCESS
                                : Contract::PAYMENT_STATUS_PENDING,
                        ]);
                }
            });
    }
};
