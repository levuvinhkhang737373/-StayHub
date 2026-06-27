<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('target_admin_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('admins')
                ->nullOnDelete();
            $table->index(['target_type', 'target_admin_id', 'status'], 'notifications_target_admin_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_target_admin_index');
            $table->dropConstrainedForeignId('target_admin_id');
        });
    }
};
