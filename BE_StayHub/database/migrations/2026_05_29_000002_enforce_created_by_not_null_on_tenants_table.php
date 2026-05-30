<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE tenants
            SET created_by = (
                SELECT buildings.manager_admin_id
                FROM contract_tenants
                INNER JOIN contracts ON contracts.id = contract_tenants.contract_id
                INNER JOIN rooms ON rooms.id = contracts.room_id
                INNER JOIN buildings ON buildings.id = rooms.building_id
                WHERE contract_tenants.tenant_id = tenants.id
                    AND buildings.manager_admin_id IS NOT NULL
                ORDER BY contract_tenants.is_staying DESC,
                    contract_tenants.leave_date IS NULL DESC,
                    contract_tenants.id DESC
                LIMIT 1
            )
            WHERE created_by IS NULL
                AND EXISTS (
                    SELECT 1
                    FROM contract_tenants
                    INNER JOIN contracts ON contracts.id = contract_tenants.contract_id
                    INNER JOIN rooms ON rooms.id = contracts.room_id
                    INNER JOIN buildings ON buildings.id = rooms.building_id
                    WHERE contract_tenants.tenant_id = tenants.id
                        AND buildings.manager_admin_id IS NOT NULL
                )
        SQL);

        $defaultAdminId = DB::table('admins')
            ->orderByRaw('role = 2 DESC')
            ->orderBy('id')
            ->value('id');

        if ($defaultAdminId !== null) {
            DB::table('tenants')
                ->whereNull('created_by')
                ->update(['created_by' => $defaultAdminId]);
        }

        if (DB::table('tenants')->whereNull('created_by')->exists()) {
            throw new RuntimeException('Không thể ép tenants.created_by NOT NULL vì vẫn còn tenant chưa có admin tạo.');
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropForeign(['created_by']);
            });

            DB::statement('ALTER TABLE tenants MODIFY created_by BIGINT UNSIGNED NOT NULL');

            Schema::table('tenants', function (Blueprint $table): void {
                $table->foreign('created_by')->references('id')->on('admins')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropForeign(['created_by']);
            });

            DB::statement('ALTER TABLE tenants MODIFY created_by BIGINT UNSIGNED NULL');

            Schema::table('tenants', function (Blueprint $table): void {
                $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();
            });
        }
    }
};
