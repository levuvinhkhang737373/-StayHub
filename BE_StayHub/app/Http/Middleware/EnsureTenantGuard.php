<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantGuard
{
   
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $tenant = $request->user('tenant');

        if (! $tenant || ! ($tenant instanceof Tenant)) {
            return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập với tài khoản tenant', 401, null, 401);
        }

        return $next($request);
    }
}
