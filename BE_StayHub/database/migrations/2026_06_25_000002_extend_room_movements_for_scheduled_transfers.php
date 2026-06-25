<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('room_movements', 'transfer_code')) {
                $table->string('transfer_code', 100)->nullable()->after('id')->index();
            }

            if (! Schema::hasColumn('room_movements', 'status')) {
                $table->unsignedTinyInteger('status')->default(2)->after('movement_type')->index();
            }

            if (! Schema::hasColumn('room_movements', 'source_contract_id')) {
                $table->foreignId('source_contract_id')->nullable()->after('contract_id')->constrained('contracts')->nullOnDelete();
            }

            if (! Schema::hasColumn('room_movements', 'destination_contract_id')) {
                $table->foreignId('destination_contract_id')->nullable()->after('source_contract_id')->constrained('contracts')->nullOnDelete();
            }

            if (! Schema::hasColumn('room_movements', 'scheduled_payload')) {
                $table->json('scheduled_payload')->nullable()->after('note');
            }

            if (! Schema::hasColumn('room_movements', 'manual_refund_amount')) {
                $table->decimal('manual_refund_amount', 15, 2)->default(0)->after('deduction_amount');
            }

            if (! Schema::hasColumn('room_movements', 'deposit_due_amount')) {
                $table->decimal('deposit_due_amount', 15, 2)->default(0)->after('manual_refund_amount');
            }

            if (! Schema::hasColumn('room_movements', 'extra_charge_amount')) {
                $table->decimal('extra_charge_amount', 15, 2)->default(0)->after('deposit_due_amount');
            }

            if (! Schema::hasColumn('room_movements', 'settlement_due_amount')) {
                $table->decimal('settlement_due_amount', 15, 2)->default(0)->after('extra_charge_amount');
            }

            if (! Schema::hasColumn('room_movements', 'settlement_paid_amount')) {
                $table->decimal('settlement_paid_amount', 15, 2)->default(0)->after('settlement_due_amount');
            }

            if (! Schema::hasColumn('room_movements', 'settlement_payment_status')) {
                $table->unsignedTinyInteger('settlement_payment_status')->default(2)->after('settlement_paid_amount')->index();
            }

            if (! Schema::hasColumn('room_movements', 'settlement_payment_references')) {
                $table->json('settlement_payment_references')->nullable()->after('settlement_payment_status');
            }

            if (! Schema::hasColumn('room_movements', 'executed_at')) {
                $table->timestamp('executed_at')->nullable()->after('settlement_payment_references');
            }

            if (! Schema::hasColumn('room_movements', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('executed_at');
            }
        });

        DB::table('room_movements')
            ->whereNull('source_contract_id')
            ->update([
                'source_contract_id' => DB::raw('contract_id'),
                'destination_contract_id' => DB::raw('contract_id'),
                'settlement_payment_status' => 2,
            ]);
    }

    public function down(): void
    {
        Schema::table('room_movements', function (Blueprint $table): void {
            foreach (['destination_contract_id', 'source_contract_id'] as $column) {
                if (Schema::hasColumn('room_movements', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'transfer_code',
                'status',
                'scheduled_payload',
                'manual_refund_amount',
                'deposit_due_amount',
                'extra_charge_amount',
                'settlement_due_amount',
                'settlement_paid_amount',
                'settlement_payment_status',
                'settlement_payment_references',
                'executed_at',
                'failure_reason',
            ] as $column) {
                if (Schema::hasColumn('room_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
