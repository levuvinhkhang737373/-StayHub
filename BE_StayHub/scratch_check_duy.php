<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\ContractTenant;

$tenants = Tenant::where('full_name', 'like', '%duy%')->get();
foreach ($tenants as $tenant) {
    echo "Tenant Name: " . $tenant->full_name . " (ID: " . $tenant->id . ")\n";
    $cts = ContractTenant::where('tenant_id', $tenant->id)->get();
    echo "Contract Tenant Records:\n";
    foreach ($cts as $ct) {
        $c = $ct->contract;
        echo "  - Contract ID: " . $ct->contract_id . " | Code: " . ($c->contract_code ?? 'N/A') . " | Status: " . ($c->status ?? 'N/A') . " | is_staying: " . ($ct->is_staying ? 'true' : 'false') . " | leave_date: " . $ct->leave_date . "\n";
    }
}

