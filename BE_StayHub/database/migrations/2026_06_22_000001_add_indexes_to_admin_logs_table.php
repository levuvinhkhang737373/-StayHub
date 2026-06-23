<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_logs', function (Blueprint $table): void {
            $table->index(['admin_id', 'created_at'], 'admin_logs_admin_id_created_at_index');
            $table->index('action', 'admin_logs_action_index');
            $table->index('created_at', 'admin_logs_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('admin_logs', function (Blueprint $table): void {
            $table->dropIndex('admin_logs_admin_id_created_at_index');
            $table->dropIndex('admin_logs_action_index');
            $table->dropIndex('admin_logs_created_at_index');
        });
    }
};
