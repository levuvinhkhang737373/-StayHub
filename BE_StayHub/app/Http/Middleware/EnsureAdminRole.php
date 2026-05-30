<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\Admin;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response|JsonResponse
    {
        // Chỉ tin Laravel admin guard chuẩn, không dùng legacy admin_id để tránh khôi phục nhầm tài khoản.
        $admin = $request->user('admin');

        if (! $admin || ! ($admin instanceof Admin)) {
            return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập với tài khoản admin', 401, null, 401);
        }

        if ($admin->status !== Admin::STATUS_ACTIVE) {
            return ApiResponse::responseJson(false, 'Tài khoản của bạn đã bị khóa', 403, null, 403);
        }

        $allowedRoles = array_map('intval', $roles);

        if ($allowedRoles !== [] && ! in_array($admin->role, $allowedRoles, true)) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập chức năng này', 403, null, 403);
        }

        return $next($request);
    }
}
