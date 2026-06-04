<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    */

    'stateful' => explode(',', (function() {
        $domains = env('SANCTUM_STATEFUL_DOMAINS', sprintf(
            '%s%s',
            'localhost,localhost:5173,127.0.0.1,127.0.0.1:5173,localhost:8080,127.0.0.1:8080,stayhub.id.vn,api.stayhub.id.vn,::1',
            Sanctum::currentApplicationUrlWithPort()
        ));
        
        // Dynamically include request origin/referer to support dynamic development ports (e.g. Flutter Web, Emulators)
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $originHost = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
            $originPort = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_PORT);
            if (in_array($originHost, ['localhost', '127.0.0.1', '10.0.2.2'], true)) {
                $domains .= ',' . $originHost . ($originPort ? ':' . $originPort : '');
            }
        }
        if (isset($_SERVER['HTTP_REFERER'])) {
            $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            $refererPort = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PORT);
            if (in_array($refererHost, ['localhost', '127.0.0.1', '10.0.2.2'], true)) {
                $domains .= ',' . $refererHost . ($refererPort ? ':' . $refererPort : '');
            }
        }
        
        return $domains;
    })()),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    |
    */

    'guard' => ['admin', 'tenant'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
