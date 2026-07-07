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
        Schema::table('notification_reads', function (Blueprint $table) {
            // Create temporary index to satisfy any foreign keys on notification_id
            $table->index('notification_id', 'temp_notification_id_idx');
            
            // Drop existing unique constraint
            $table->dropUnique(['notification_id', 'tenant_id']);
            
            // Make tenant_id nullable
            $table->foreignId('tenant_id')->nullable()->change();
            
            // Add admin_id
            $table->foreignId('admin_id')->nullable()->after('tenant_id')->constrained('admins')->cascadeOnDelete();
            
            // Re-add unique constraints
            $table->unique(['notification_id', 'tenant_id']);
            $table->unique(['notification_id', 'admin_id']);
            
            // Drop temporary index
            $table->dropIndex('temp_notification_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_reads', function (Blueprint $table) {
            $table->index('notification_id', 'temp_notification_id_idx');
            
            $table->dropUnique(['notification_id', 'admin_id']);
            $table->dropUnique(['notification_id', 'tenant_id']);
            
            $table->dropConstrainedForeignId('admin_id');
            
            $table->foreignId('tenant_id')->nullable(false)->change();
            
            $table->unique(['notification_id', 'tenant_id']);
            
            $table->dropIndex('temp_notification_id_idx');
        });
    }
};
