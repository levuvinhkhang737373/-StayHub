<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class UseTenantSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->usesTenantLifetime($request)) {
            return $next($request);
        }

        $defaultLifetime = Config::get('session.lifetime');
        $tenantLifetime = (int) Config::get('session.tenant_lifetime', $defaultLifetime);

        Config::set('session.lifetime', $tenantLifetime);
        app(SessionManager::class)->forgetDrivers();

        try {
            return $next($request);
        } finally {
            Config::set('session.lifetime', $defaultLifetime);
            app(SessionManager::class)->forgetDrivers();
        }
    }

    private function usesTenantLifetime(Request $request): bool
    {
        return $request->is('api/v1/tenant/*')
            || ($request->is('broadcasting/auth') && $request->header('X-StayHub-Tenant-Session') === '1');
    }
}
