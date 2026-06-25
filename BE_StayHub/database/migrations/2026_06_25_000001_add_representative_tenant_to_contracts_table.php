<?php

use App\Models\ContractTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            if (! Schema::hasColumn('contracts', 'representative_tenant_id')) {
                $table->foreignId('representative_tenant_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('tenants')
                    ->nullOnDelete();
            }
        });

        DB::table('contracts')
            ->whereNull('representative_tenant_id')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(200, function ($contracts): void {
                foreach ($contracts as $contract) {
                    $representativeTenantId = ContractTenant::query()
                        ->where('contract_id', $contract->id)
                        ->where('is_staying', true)
                        ->whereNull('leave_date')
                        ->orderBy('join_date')
                        ->orderBy('id')
                        ->value('tenant_id');

                    if ($representativeTenantId) {
                        DB::table('contracts')
                            ->where('id', $contract->id)
                            ->update(['representative_tenant_id' => $representativeTenantId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            if (Schema::hasColumn('contracts', 'representative_tenant_id')) {
                $table->dropConstrainedForeignId('representative_tenant_id');
            }
        });
    }
};
