<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\NotificationResource;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Danh sách thông báo được gửi tới khách thuê hiện tại
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            // Tìm hợp đồng hoạt động của tenant (hoặc đại diện ký, hoặc cư trú cùng)
            $activeContract = \App\Models\Contract::query()
                ->where('status', \App\Models\Contract::STATUS_ACTIVE)
                ->where(function ($q) use ($tenant) {
                    $q->where('representative_tenant_id', $tenant->id)
                      ->orWhereHas('tenants', function ($tq) use ($tenant) {
                          $tq->where('tenants.id', $tenant->id)
                             ->where('contract_tenants.is_staying', true)
                             ->whereNull('contract_tenants.leave_date');
                      });
                })
                ->with('room')
                ->latest('id')
                ->first();

            $currentRoom = $activeContract?->room;
            $buildingId = $currentRoom?->building_id ?? $tenant->building_id;
            $roomId = $currentRoom?->id ?? $tenant->room_id;

            $pivotRelation = $tenant->contractTenants()
                ->where('is_staying', true)
                ->whereNull('leave_date')
                ->whereHas('contract', fn ($query) => $query->where('status', \App\Models\Contract::STATUS_ACTIVE))
                ->latest('id')
                ->first();
            $joinDate = $pivotRelation?->join_date ?? $activeContract?->start_date ?? null;
            $joinDate = $joinDate ? \Illuminate\Support\Carbon::parse($joinDate)->startOfDay() : null;

            $tenantCreatedAt = \Illuminate\Support\Carbon::parse($tenant->created_at)->startOfDay();
            $dateLimit = $joinDate && $joinDate->greaterThan($tenantCreatedAt) ? $joinDate : $tenantCreatedAt;

            \Illuminate\Support\Facades\Log::info('DEBUG NOTIFICATIONS', [
                'tenant_id' => $tenant->id,
                'tenant_username' => $tenant->username,
                'resolved_building_id' => $buildingId,
                'resolved_room_id' => $roomId,
                'date_limit' => $dateLimit?->toDateTimeString(),
                'tenant_building_id_in_db' => $tenant->building_id,
            ]);

            // Truy vấn các thông báo phù hợp với phạm vi của khách thuê
            $notifications = Notification::query()
                ->where('status', Notification::STATUS_SENT)
                ->where(function ($q) use ($tenant, $buildingId, $roomId) {
                    $q->where(function ($sub) {
                          $sub->where('target_type', Notification::TARGET_TYPE_ALL);
                      })
                      ->orWhere(function ($sub) use ($buildingId) {
                          $sub->where('target_type', Notification::TARGET_TYPE_BUILDING)
                              ->where('building_id', $buildingId);
                      })
                      ->orWhere(function ($sub) use ($roomId) {
                          $sub->where('target_type', Notification::TARGET_TYPE_ROOM)
                              ->where('room_id', $roomId);
                      })
                      ->orWhere(function ($sub) use ($tenant) {
                          $sub->where('target_type', Notification::TARGET_TYPE_TENANT)
                              ->where('tenant_id', $tenant->id);
                      });
                })
                ->with(['creator', 'reads' => function ($query) use ($tenant) {
                    $query->where('tenant_id', $tenant->id);
                }])
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 20));

            return ApiResponse::responseJson(true, 'Danh sách thông báo', 200, [
                'data' => NotificationResource::collection($notifications->items())->resolve(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Đánh dấu một thông báo đã đọc
     */
    public function read(Request $request, int $id): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $notification = Notification::query()
                ->where('status', Notification::STATUS_SENT)
                ->find($id);

            if (! $notification) {
                return ApiResponse::responseJson(false, 'Không tìm thấy thông báo', 404, null, 404);
            }

            // Đánh dấu đã đọc bằng firstOrCreate
            $readRecord = NotificationRead::query()->firstOrCreate([
                'notification_id' => $notification->id,
                'tenant_id' => $tenant->id,
            ], [
                'read_at' => now(),
            ]);

            return ApiResponse::responseJson(true, 'Đã đánh dấu đọc thông báo', 200, $readRecord, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Đánh dấu đọc toàn bộ thông báo của khách thuê
     */
    public function readAll(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            // Tìm hợp đồng hoạt động của tenant (hoặc đại diện ký, hoặc cư trú cùng)
            $activeContract = \App\Models\Contract::query()
                ->where('status', \App\Models\Contract::STATUS_ACTIVE)
                ->where(function ($q) use ($tenant) {
                    $q->where('representative_tenant_id', $tenant->id)
                      ->orWhereHas('tenants', function ($tq) use ($tenant) {
                          $tq->where('tenants.id', $tenant->id)
                             ->where('contract_tenants.is_staying', true)
                             ->whereNull('contract_tenants.leave_date');
                      });
                })
                ->with('room')
                ->latest('id')
                ->first();

            $currentRoom = $activeContract?->room;
            $buildingId = $currentRoom?->building_id ?? $tenant->building_id;
            $roomId = $currentRoom?->id ?? $tenant->room_id;

            $pivotRelation = $tenant->contractTenants()
                ->where('is_staying', true)
                ->whereNull('leave_date')
                ->whereHas('contract', fn ($query) => $query->where('status', \App\Models\Contract::STATUS_ACTIVE))
                ->latest('id')
                ->first();
            $joinDate = $pivotRelation?->join_date ?? $activeContract?->start_date ?? null;
            $joinDate = $joinDate ? \Illuminate\Support\Carbon::parse($joinDate)->startOfDay() : null;

            $tenantCreatedAt = \Illuminate\Support\Carbon::parse($tenant->created_at)->startOfDay();
            $dateLimit = $joinDate && $joinDate->greaterThan($tenantCreatedAt) ? $joinDate : $tenantCreatedAt;

            // Tìm toàn bộ danh sách thông báo chưa đọc của tenant
            $notificationIds = Notification::query()
                ->where('status', Notification::STATUS_SENT)
                ->where(function ($q) use ($tenant, $buildingId, $roomId) {
                    $q->where(function ($sub) {
                          $sub->where('target_type', Notification::TARGET_TYPE_ALL);
                      })
                      ->orWhere(function ($sub) use ($buildingId) {
                          $sub->where('target_type', Notification::TARGET_TYPE_BUILDING)
                              ->where('building_id', $buildingId);
                      })
                      ->orWhere(function ($sub) use ($roomId) {
                          $sub->where('target_type', Notification::TARGET_TYPE_ROOM)
                              ->where('room_id', $roomId);
                      })
                      ->orWhere(function ($sub) use ($tenant) {
                          $sub->where('target_type', Notification::TARGET_TYPE_TENANT)
                              ->where('tenant_id', $tenant->id);
                      });
                })
                ->whereDoesntHave('reads', function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id);
                })
                ->pluck('id');

            foreach ($notificationIds as $id) {
                NotificationRead::query()->firstOrCreate([
                    'notification_id' => $id,
                    'tenant_id' => $tenant->id,
                ], [
                    'read_at' => now(),
                ]);
            }

            return ApiResponse::responseJson(true, 'Đã đánh dấu đọc toàn bộ thông báo', 200, null, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
