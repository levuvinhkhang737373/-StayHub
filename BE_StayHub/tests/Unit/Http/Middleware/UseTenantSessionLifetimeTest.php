<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\UseTenantSessionLifetime;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UseTenantSessionLifetimeTest extends TestCase
{
    public function test_applies_tenant_lifetime_to_tenant_api_requests_before_next_middleware(): void
    {
        Config::set('session.lifetime', 120);
        Config::set('session.tenant_lifetime', 43200);

        $manager = $this->app->make(SessionManager::class);
        $manager->driver();

        $middleware = new UseTenantSessionLifetime;
        $request = Request::create('/api/v1/tenant/me', 'GET');

        $middleware->handle($request, function () use ($manager) {
            $this->assertSame(43200, Config::get('session.lifetime'));
            $this->assertSame([], $manager->getDrivers());

            return response('ok');
        });
    }

    public function test_keeps_default_lifetime_for_admin_api_requests(): void
    {
        Config::set('session.lifetime', 120);
        Config::set('session.tenant_lifetime', 43200);

        $manager = $this->app->make(SessionManager::class);
        $manager->driver();

        $middleware = new UseTenantSessionLifetime;
        $request = Request::create('/api/v1/admin/me', 'GET');

        $middleware->handle($request, function () use ($manager) {
            $this->assertSame(120, Config::get('session.lifetime'));
            $this->assertNotSame([], $manager->getDrivers());

            return response('ok');
        });
    }

    public function test_applies_tenant_lifetime_to_tenant_broadcast_auth_requests(): void
    {
        Config::set('session.lifetime', 120);
        Config::set('session.tenant_lifetime', 43200);

        $manager = $this->app->make(SessionManager::class);
        $manager->driver();

        $middleware = new UseTenantSessionLifetime;
        $request = Request::create('/broadcasting/auth', 'POST', [], [], [], [
            'HTTP_X_STAYHUB_TENANT_SESSION' => '1',
        ]);

        $middleware->handle($request, function () use ($manager) {
            $this->assertSame(43200, Config::get('session.lifetime'));
            $this->assertSame([], $manager->getDrivers());

            return response('ok');
        });
    }

    public function test_keeps_default_lifetime_for_admin_broadcast_auth_requests(): void
    {
        Config::set('session.lifetime', 120);
        Config::set('session.tenant_lifetime', 43200);

        $manager = $this->app->make(SessionManager::class);
        $manager->driver();

        $middleware = new UseTenantSessionLifetime;
        $request = Request::create('/broadcasting/auth', 'POST');

        $middleware->handle($request, function () use ($manager) {
            $this->assertSame(120, Config::get('session.lifetime'));
            $this->assertNotSame([], $manager->getDrivers());

            return response('ok');
        });
    }
}
