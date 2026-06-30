<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;

$tenant = Tenant::find(48); // nguyen duy
Sanctum::actingAs($tenant, ['tenant'], 'tenant');

$response = $app->handle(
    Illuminate\Http\Request::create('/api/v1/tenant/contracts', 'GET')
);

echo "Response Code: " . $response->getStatusCode() . "\n";
echo "Response Body: \n";
$data = json_decode($response->getContent(), true);
if (isset($data['result'])) {
    foreach ($data['result'] as $resource) {
        echo "Contract Code: " . $resource['contract_code'] . " | is_staying: " . (isset($resource['is_staying']) ? ($resource['is_staying'] ? 'true' : 'false') : 'NOT_FOUND') . "\n";
    }
} else {
    echo $response->getContent() . "\n";
}
