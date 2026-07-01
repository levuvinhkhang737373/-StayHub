<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Create a plain index on contract_id to satisfy the foreign key dependency requirement
            $table->index('contract_id', 'invoices_contract_id_index');
            
            $table->dropUnique('invoices_contract_id_billing_year_billing_month_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['contract_id', 'billing_year', 'billing_month'], 'invoices_contract_id_billing_year_billing_month_unique');
            
            $table->dropIndex('invoices_contract_id_index');
        });
    }
};
