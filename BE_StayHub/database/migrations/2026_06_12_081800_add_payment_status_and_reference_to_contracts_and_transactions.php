<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_status')->default(1)->after('status');
        });

        Schema::table('contract_deposit_transactions', function (Blueprint $table) {
            $table->string('transaction_reference', 150)->nullable()->unique()->after('payment_method');
        });

        // Update existing contract status
        // PAYMENT_STATUS_SUCCESS = 2, PAYMENT_STATUS_PENDING = 1
        // Contracts with deposit_amount <= 0 are automatically PAID (SUCCESS)
        DB::table('contracts')->where('deposit_amount', '<=', 0)->update(['payment_status' => 2]);

        // Contracts with deposit_amount > 0 need to calculate balance
        $contracts = DB::table('contracts')->where('deposit_amount', '>', 0)->get();
        foreach ($contracts as $contract) {
            $balance = DB::table('contract_deposit_transactions')
                ->where('contract_id', $contract->id)
                ->get()
                ->reduce(function ($carry, $tx) {
                    $amount = (float)$tx->amount;
                    if (in_array((int)$tx->transaction_type, [1, 3], true)) { // COLLECT = 1, TRANSFER = 3
                        return $carry + $amount;
                    }
                    return $carry - $amount; // REFUND = 2, DEDUCT = 4
                }, 0.0);

            if ($balance >= (float)$contract->deposit_amount) {
                DB::table('contracts')->where('id', $contract->id)->update(['payment_status' => 2]);
            } else {
                DB::table('contracts')->where('id', $contract->id)->update(['payment_status' => 1]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });

        Schema::table('contract_deposit_transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_reference');
        });
    }
};
