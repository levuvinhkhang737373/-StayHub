<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenant = App\Models\Tenant::find(1);
$request = Illuminate\Http\Request::create('/tenant/contracts', 'GET');
$request->setUserResolver(fn() => $tenant);

$controller = new App\Http\Controllers\Tenant\ContractController();
try {
    $response = $controller->index($request);
    echo "Response JSON:\n";
    echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
