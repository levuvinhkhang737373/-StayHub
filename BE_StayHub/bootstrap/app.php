<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureTenantGuard;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/v1/admin/login',
            'broadcasting/auth',
        ]);

        $middleware->alias([
            // Middleware admin tổng hợp: xác thực + trạng thái + role (khi truyền role).
            'auth.admin' => EnsureAdminRole::class,
            // Middleware xác thực riêng cho tenant API.
            'auth.tenant' => EnsureTenantGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
