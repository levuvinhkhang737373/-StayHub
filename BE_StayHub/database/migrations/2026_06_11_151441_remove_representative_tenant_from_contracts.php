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
            $table->dropForeign(['representative_tenant_id']);
            $table->dropColumn('representative_tenant_id');
        });

        Schema::table('contract_tenants', function (Blueprint $table) {
            $table->dropColumn('is_representative');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('representative_tenant_id')->after('room_id')->constrained('tenants')->restrictOnDelete();
        });

        Schema::table('contract_tenants', function (Blueprint $table) {
            $table->boolean('is_representative')->default(false)->after('billing_end_date');
        });
    }
};
