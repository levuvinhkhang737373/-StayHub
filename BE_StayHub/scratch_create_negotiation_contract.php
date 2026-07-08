<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
try {
    $tenantId = 46; // Khiêm
    $tenant = Tenant::findOrFail($tenantId);
    
    $contract = new Contract();
    $contract->contract_code = 'HD-TL-TEST-' . time();
    $contract->room_id = 69; // Room ID 69 from building 9
    $contract->start_date = now()->format('Y-m-d');
    $contract->end_date = now()->addYear()->format('Y-m-d');
    $contract->billing_cycle_day = 1;
    $contract->room_price = 3000000.00;
    $contract->deposit_amount = 3000000.00;
    $contract->status = Contract::STATUS_PENDING_SIGN; // 0
    $contract->negotiation_status = Contract::NEGOTIATION_STATUS_PENDING; // 1
    $contract->proposed_room_price = 2800000.00;
    $contract->proposed_services = [
        ['service_id' => 1, 'price' => 1000],
        ['service_id' => 2, 'price' => 1000],
    ];
    $contract->representative_tenant_id = $tenantId;
    $contract->created_by = 11; // admin
    $contract->payment_status = Contract::PAYMENT_STATUS_PENDING;
    $contract->save();
    
    // Attach tenant to contract
    $contract->tenants()->attach($tenantId, [
        'join_date' => now()->format('Y-m-d'),
        'billing_start_date' => now()->format('Y-m-d'),
        'is_staying' => true,
        'created_by' => 11,
    ]);
    
    DB::commit();
    echo "Contract created successfully! ID: " . $contract->id . " Code: " . $contract->contract_code . "\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Failed to create contract: " . $e->getMessage() . "\n";
}
