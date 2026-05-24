<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\Admin;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response|JsonResponse
    {
        $admin = $request->user('admin');

        if (! $admin) {
            $adminId = $request->session()->get(Auth::guard('admin')->getName()) ?? $request->session()->get('admin_id');
            $admin = $adminId ? Admin::query()->find($adminId) : null;
        }

        if (! $admin || ! ($admin instanceof Admin)) {
            return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập với tài khoản admin', 401, null, 401);
        }

        if ($admin->status !== Admin::STATUS_ACTIVE) {
            return ApiResponse::responseJson(false, 'Tài khoản admin không được phép truy cập', 403, null, 403);
        }

        $allowedRoles = array_map('intval', $roles);

        if ($allowedRoles !== [] && ! in_array($admin->role, $allowedRoles, true)) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập chức năng này', 403, null, 403);
        }

        return $next($request);
    }
}
