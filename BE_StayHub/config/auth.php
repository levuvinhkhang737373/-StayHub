<?php

use App\Models\Admin;
use App\Models\Tenant;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Cấu hình mặc định ưu tiên auth quản trị viên. Phần tenant dùng guard riêng
    | để đăng nhập app/mobile/API mà không phụ thuộc bảng users mặc định.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'admin'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Tách rõ guard session cho admin và tenant để Sanctum SPA cookie xác thực
    | đúng provider theo từng nhóm tài khoản.
    |
    */

    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
        'tenant' => [
            'driver' => 'session',
            'provider' => 'tenants',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Loại bỏ hoàn toàn provider users mặc định, thay bằng 2 nguồn xác thực thực
    | tế của hệ thống là admins và tenants.
    |
    */

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],
        'tenants' => [
            'driver' => 'eloquent',
            'model' => Tenant::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Tách broker riêng cho admins và tenants để sau này reset mật khẩu không
    | bị lẫn luồng giữa quản trị viên và khách thuê.
    |
    */

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        'tenants' => [
            'provider' => 'tenants',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
