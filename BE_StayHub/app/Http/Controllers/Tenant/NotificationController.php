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

            $currentRoomRelation = $tenant->contractTenants()
                ->where('is_staying', true)
                ->whereNull('leave_date')
                ->whereHas('contract', fn ($query) => $query->where('status', Contract::STATUS_ACTIVE))
                ->with('contract.room')
                ->latest('id')
                ->first();
            $currentRoom = $currentRoomRelation?->contract?->room;
            $buildingId = $currentRoom?->building_id ?? $tenant->building_id;
            $roomId = $currentRoom?->id ?? $tenant->room_id;
            $joinDate = $currentRoomRelation?->join_date ? \Illuminate\Support\Carbon::parse($currentRoomRelation->join_date)->startOfDay() : null;

            // Truy vấn các thông báo phù hợp với phạm vi của khách thuê
            $notifications = Notification::query()
                ->where('status', Notification::STATUS_SENT)
                ->where(function ($q) use ($tenant, $buildingId, $roomId, $joinDate) {
                    $q->where(function ($sub) use ($joinDate) {
                          $sub->where('target_type', Notification::TARGET_TYPE_ALL);
                          if ($joinDate) {
                              $sub->where('published_at', '>=', $joinDate);
                          }
                      })
                      ->orWhere(function ($sub) use ($buildingId, $joinDate) {
                          $sub->where('target_type', Notification::TARGET_TYPE_BUILDING)
                              ->where('building_id', $buildingId);
                          if ($joinDate) {
                              $sub->where('published_at', '>=', $joinDate);
                          }
                      })
                      ->orWhere(function ($sub) use ($roomId, $joinDate) {
                          $sub->where('target_type', Notification::TARGET_TYPE_ROOM)
                              ->where('room_id', $roomId);
                          if ($joinDate) {
                              $sub->where('published_at', '>=', $joinDate);
                          }
                      })
                      ->orWhere(function ($sub) use ($tenant) {
                          $sub->where('target_type', Notification::TARGET_TYPE_TENANT)
                              ->where('tenant_id', $tenant->id);
                      });
                })
                ->with(['reads' => function ($query) use ($tenant) {
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

            $currentRoomRelation = $tenant->contractTenants()
                ->where('is_staying', true)
                ->whereNull('leave_date')
                ->whereHas('contract', fn ($query) => $query->where('status', Contract::STATUS_ACTIVE))
                ->with('contract.room')
                ->latest('id')
                ->first();
            $currentRoom = $currentRoomRelation?->contract?->room;
            $buildingId = $currentRoom?->building_id ?? $tenant->building_id;
            $roomId = $currentRoom?->id ?? $tenant->room_id;
            $joinDate = $currentRoomRelation?->join_date ? \Illuminate\Support\Carbon::parse($currentRoomRelation->join_date)->startOfDay() : null;

            // Tìm toàn bộ danh sách thông báo chưa đọc của tenant
            $notificationIds = Notification::query()
                ->where('status', Notification::STATUS_SENT)
                ->where(function ($q) use ($tenant, $buildingId, $roomId, $joinDate) {
                    $q->where(function ($sub) use ($joinDate) {
                          $sub->where('target_type', Notification::TARGET_TYPE_ALL);
                          if ($joinDate) {
                              $sub->where('published_at', '>=', $joinDate);
                          }
                      })
                      ->orWhere(function ($sub) use ($buildingId, $joinDate) {
                          $sub->where('target_type', Notification::TARGET_TYPE_BUILDING)
                              ->where('building_id', $buildingId);
                          if ($joinDate) {
                              $sub->where('published_at', '>=', $joinDate);
                          }
                      })
                      ->orWhere(function ($sub) use ($roomId, $joinDate) {
                          $sub->where('target_type', Notification::TARGET_TYPE_ROOM)
                              ->where('room_id', $roomId);
                          if ($joinDate) {
                              $sub->where('published_at', '>=', $joinDate);
                          }
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
