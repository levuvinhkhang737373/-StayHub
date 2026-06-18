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
        Schema::table('tenants', function (Blueprint $table) {
            $table->date('identity_date')->nullable()->after('identity_type');
            $table->string('identity_place')->nullable()->after('identity_date');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('tenant_signed_at')->nullable()->after('status');
            $table->string('tenant_signature_url')->default('signatures/placeholder.png')->after('tenant_signed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['tenant_signed_at', 'tenant_signature_url']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['identity_date', 'identity_place']);
        });
    }
};
