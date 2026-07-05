<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_debt_rollovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('target_invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('settled_amount', 15, 2)->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['source_invoice_id', 'target_invoice_id'], 'idr_source_target_unique');
            $table->index(['source_invoice_id', 'status'], 'idr_source_status_idx');
            $table->index(['target_invoice_id', 'status'], 'idr_target_status_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('allocated_from_payment_id')->nullable()->after('invoice_id')->constrained('payments')->nullOnDelete();
            $table->foreignId('invoice_debt_rollover_id')->nullable()->after('allocated_from_payment_id')->constrained('invoice_debt_rollovers')->nullOnDelete();
            $table->boolean('is_internal_allocation')->default(false)->after('invoice_debt_rollover_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'invoice_debt_rollover_id')) {
                $table->dropConstrainedForeignId('invoice_debt_rollover_id');
            }

            if (Schema::hasColumn('payments', 'allocated_from_payment_id')) {
                $table->dropConstrainedForeignId('allocated_from_payment_id');
            }

            if (Schema::hasColumn('payments', 'is_internal_allocation')) {
                $table->dropColumn('is_internal_allocation');
            }
        });

        Schema::dropIfExists('invoice_debt_rollovers');
    }
};
