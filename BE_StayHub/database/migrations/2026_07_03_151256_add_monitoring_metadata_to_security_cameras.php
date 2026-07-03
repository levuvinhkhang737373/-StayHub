<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_cameras', function (Blueprint $table): void {
            $table->string('monitoring_token', 64)->nullable()->after('status');
            $table->timestamp('monitoring_started_at')->nullable()->after('monitoring_token');
            $table->timestamp('monitoring_stopped_at')->nullable()->after('monitoring_started_at');
            $table->timestamp('last_scanned_at')->nullable()->after('monitoring_stopped_at');
            $table->timestamp('next_scan_at')->nullable()->after('last_scanned_at');
            $table->string('last_scan_status', 20)->nullable()->after('next_scan_at');
            $table->text('last_scan_message')->nullable()->after('last_scan_status');
            $table->unsignedInteger('monitoring_error_count')->default(0)->after('last_scan_message');

            $table->index(['is_ai_enabled', 'status', 'next_scan_at'], 'security_cameras_monitoring_due_index');
            $table->index('monitoring_token', 'security_cameras_monitoring_token_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE security_cameras MODIFY is_ai_enabled TINYINT(1) NOT NULL DEFAULT 0');
        }

        DB::table('security_cameras')->update([
            'is_ai_enabled' => false,
            'monitoring_token' => null,
            'monitoring_started_at' => null,
            'monitoring_stopped_at' => now(),
            'last_scanned_at' => null,
            'next_scan_at' => null,
            'last_scan_status' => null,
            'last_scan_message' => null,
            'monitoring_error_count' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('security_cameras', function (Blueprint $table): void {
            $table->dropIndex('security_cameras_monitoring_due_index');
            $table->dropIndex('security_cameras_monitoring_token_index');
            $table->dropColumn([
                'monitoring_token',
                'monitoring_started_at',
                'monitoring_stopped_at',
                'last_scanned_at',
                'next_scan_at',
                'last_scan_status',
                'last_scan_message',
                'monitoring_error_count',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE security_cameras MODIFY is_ai_enabled TINYINT(1) NOT NULL DEFAULT 1');
        }
    }
};
