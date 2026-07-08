<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// 1. Get the test tenant
$tenant = Tenant::find(46);
if (!$tenant) {
    echo "Tenant 46 not found!\n";
    exit(1);
}

echo "Testing broadcasting auth for Tenant: " . $tenant->full_name . " (ID: " . $tenant->id . ")\n";

// 2. Set the authenticated user on the guard
Auth::guard('tenant')->setUser($tenant);

// 3. Construct a dummy request to /broadcasting/auth
$request = Request::create('/broadcasting/auth', 'POST', [
    'socket_id' => '1234.5678',
    'channel_name' => 'private-tenant.46',
]);

// Set the request on the app container
$app->instance('request', $request);

try {
    // Authenticate the channel using the Broadcast manager
    $response = Broadcast::auth($request);
    echo "Auth success! Response:\n";
    print_r($response);
} catch (\Exception $e) {
    echo "Auth failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
