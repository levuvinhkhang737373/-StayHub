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
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('parent_contract_id')->nullable()->after('created_by')->constrained('contracts')->nullOnDelete();
            $table->foreignId('renew_from_contract_id')->nullable()->after('parent_contract_id')->constrained('contracts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['parent_contract_id']);
            $table->dropForeign(['renew_from_contract_id']);
            $table->dropColumn(['parent_contract_id', 'renew_from_contract_id']);
        });
    }
};
