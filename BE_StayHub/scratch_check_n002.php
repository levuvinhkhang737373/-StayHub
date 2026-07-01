<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contract;

$c = Contract::where('contract_code', 'HD-TEST-26-6-N002-3')->first();
if (!$c) {
    echo "Contract not found!\n";
    exit;
}

echo "Contract Code: " . $c->contract_code . "\n";
echo "Tenants:\n";
foreach ($c->contractTenants as $ct) {
    echo "  - " . ($ct->tenant->full_name ?? 'N/A') . " (is_staying: " . ($ct->is_staying ? 'true' : 'false') . ", ID: " . $ct->tenant_id . ")\n";
}
