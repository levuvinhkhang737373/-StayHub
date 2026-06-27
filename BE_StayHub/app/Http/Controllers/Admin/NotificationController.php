<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\ApiResponse;
use App\Helpers\AdminScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\StoreRequest;
use App\Http\Requests\Admin\Notification\UpdateRequest;
use App\Http\Resources\Admin\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Danh sách thông báo hệ thống (Dành cho Admin)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập', 403, null, 403);
            }

            $notifications = $this->accessibleQuery($admin)
                ->with(['building', 'room', 'tenant', 'creator'])
                ->when($request->filled('status'), function ($q) use ($request) {
                    $q->where('status', $request->integer('status'));
                })
                ->when($request->filled('target_type'), function ($q) use ($request) {
                    $q->where('target_type', $request->integer('target_type'));
                })
                ->when($request->filled('building_id'), function ($q) use ($request) {
                    $q->where('building_id', $request->integer('building_id'));
                })
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
     * Tạo mới một thông báo
     */
    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập', 403, null, 403);
            }

            $status = $request->integer('status');
            $publishedAt = ($status === Notification::STATUS_SENT) ? now() : null;

            $notification = DB::transaction(function () use ($request, $admin, $status, $publishedAt) {
                $created = Notification::query()->create([
                    'title' => $request->input('title'),
                    'content' => $request->input('content'),
                    'notification_type' => $request->integer('notification_type'),
                    'target_type' => $request->integer('target_type'),
                    'building_id' => $request->input('building_id'),
                    'room_id' => $request->input('room_id'),
                    'tenant_id' => $request->input('tenant_id'),
                    'published_at' => $publishedAt,
                    'status' => $status,
                    'created_by' => $admin->id,
                ]);

                // Ghi log hành động admin
                AdminActivityLogger::write($admin, 'create_notification', Notification::class, $created->id, null, $created->toArray(), $request);

                return $created;
            });

            $notification->load(['building', 'room', 'tenant', 'creator']);

            if ($status === Notification::STATUS_SENT) {
                broadcast(new \App\Events\NotificationSent($notification));
            }

            return ApiResponse::responseJson(true, 'Tạo thông báo thành công', 201, new NotificationResource($notification), 201);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Chi tiết một thông báo
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập', 403, null, 403);
            }

            $notification = $this->accessibleQuery($admin)
                ->with(['building', 'room', 'tenant', 'creator'])
                ->find($id);

            if (! $notification) {
                return ApiResponse::responseJson(false, 'Không tìm thấy thông báo', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết thông báo', 200, new NotificationResource($notification), 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Cập nhật thông báo
     */
    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập', 403, null, 403);
            }

            $notification = $this->accessibleQuery($admin)->find($id);
            if (! $notification) {
                return ApiResponse::responseJson(false, 'Không tìm thấy thông báo', 404, null, 404);
            }

            $oldData = $notification->toArray();
            $status = $request->integer('status');

            $publishedAt = $notification->published_at;
            if ($status === Notification::STATUS_SENT && is_null($publishedAt)) {
                $publishedAt = now();
            }

            DB::transaction(function () use ($request, $admin, $notification, $status, $publishedAt, $oldData) {
                $notification->update([
                    'title' => $request->input('title'),
                    'content' => $request->input('content'),
                    'notification_type' => $request->integer('notification_type'),
                    'target_type' => $request->integer('target_type'),
                    'building_id' => $request->input('building_id'),
                    'room_id' => $request->input('room_id'),
                    'tenant_id' => $request->input('tenant_id'),
                    'published_at' => $publishedAt,
                    'status' => $status,
                ]);

                // Ghi log hành động admin
                AdminActivityLogger::write($admin, 'update_notification', Notification::class, $notification->id, $oldData, $notification->fresh()->toArray(), $request);
            });

            $notification->load(['building', 'room', 'tenant', 'creator']);

            if ($status === Notification::STATUS_SENT) {
                broadcast(new \App\Events\NotificationSent($notification));
            }

            return ApiResponse::responseJson(true, 'Cập nhật thông báo thành công', 200, new NotificationResource($notification), 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Xóa thông báo
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập', 403, null, 403);
            }

            $notification = $this->accessibleQuery($admin)->find($id);
            if (! $notification) {
                return ApiResponse::responseJson(false, 'Không tìm thấy thông báo', 404, null, 404);
            }

            $oldData = $notification->toArray();

            DB::transaction(function () use ($request, $admin, $notification, $oldData) {
                $notification->delete();

                // Ghi log hành động admin
                AdminActivityLogger::write($admin, 'delete_notification', Notification::class, $notification->id, $oldData, null, $request);
            });

            return ApiResponse::responseJson(true, 'Xóa thông báo thành công', 200, null, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    private function accessibleQuery($admin)
    {
        $query = Notification::query()
            ->where(function ($q) {
                $q->whereNotNull('created_by')
                  ->orWhereIn('title', [
                      'Yêu cầu sửa chữa mới', 
                      'Phản hồi mới từ khách thuê', 
                      'Hợp đồng hết hạn', 
                      'Thanh toán đặt cọc thành công',
                      'Hợp đồng đã được ký',
                      'Hóa đơn đã được thanh toán',
                      'Thanh toán hóa đơn thành công',
                      'Tin nhắn mới từ khách thuê'
                  ]);
            });

        if (! AdminScope::isSuperAdmin($admin)) {
            $query->where(function ($q) use ($admin) {
                $q->whereIn('building_id', function ($db) use ($admin) {
                    $db->select('id')
                       ->from('buildings')
                       ->where('manager_admin_id', $admin->id);
                })
                ->orWhere('target_admin_id', $admin->id)
                ->orWhere('created_by', $admin->id);
            });
        }

        return $query;
    }
}
