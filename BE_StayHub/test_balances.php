<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contract;

foreach(Contract::all() as $c) {
    if ((float)$c->deposit_balance > 0) {
        echo $c->contract_code . ': ' . $c->deposit_balance . "\n";
    }
}
