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
use App\Models\NotificationRead;
use Illuminate\Database\Eloquent\Builder;
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

            $statsQuery = $this->accessibleQuery($admin);

            $statsData = $statsQuery->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = 1 THEN 1 END) as draft,
                COUNT(CASE WHEN status = 2 THEN 1 END) as sent,
                COUNT(CASE WHEN status = 3 THEN 1 END) as cancelled
            ')->first();

            $unreadCount = $this->accessibleQuery($admin)
                ->where('status', Notification::STATUS_SENT)
                ->where(function ($q) use ($admin) {
                    $q->whereNull('created_by')
                      ->orWhere('created_by', '!=', $admin->id);
                })
                ->whereDoesntHave('reads', function ($q) use ($admin) {
                    $q->where('admin_id', $admin->id);
                })
                ->count();

            $stats = [
                'total' => $statsData ? (int) $statsData->total : 0,
                'draft' => $statsData ? (int) $statsData->draft : 0,
                'sent' => $statsData ? (int) $statsData->sent : 0,
                'cancelled' => $statsData ? (int) $statsData->cancelled : 0,
                'unread' => $unreadCount,
            ];

            return ApiResponse::responseJson(true, 'Danh sách thông báo', 200, [
                'data' => NotificationResource::collection($notifications->items())->resolve(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ],
                'stats' => $stats,
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
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
                AdminActivityLogger::write($admin, 'Tạo thông báo', Notification::class, $created->id, null, $created->toArray(), $request);

                return $created;
            });

            $notification->load(['building', 'room', 'tenant', 'creator']);

            if ($status === Notification::STATUS_SENT) {
                broadcast(new \App\Events\NotificationSent($notification));
            }

            return ApiResponse::responseJson(true, 'Tạo thông báo thành công', 201, new NotificationResource($notification), 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
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
            \Illuminate\Support\Facades\Log::error($e);
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
                AdminActivityLogger::write($admin, 'Cập nhật thông báo', Notification::class, $notification->id, $oldData, $notification->fresh()->toArray(), $request);
            });

            $notification->load(['building', 'room', 'tenant', 'creator']);

            if ($status === Notification::STATUS_SENT) {
                broadcast(new \App\Events\NotificationSent($notification));
            }

            return ApiResponse::responseJson(true, 'Cập nhật thông báo thành công', 200, new NotificationResource($notification), 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
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
                AdminActivityLogger::write($admin, 'Xóa thông báo', Notification::class, $notification->id, $oldData, null, $request);
            });

            return ApiResponse::responseJson(true, 'Xóa thông báo thành công', 200, null, 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Đánh dấu một thông báo đã đọc
     */
    public function read(Request $request, int $id): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $notification = $this->accessibleQuery($admin)
                ->where('status', Notification::STATUS_SENT)
                ->find($id);

            if (! $notification) {
                return ApiResponse::responseJson(false, 'Không tìm thấy thông báo', 404, null, 404);
            }

            // Đánh dấu đã đọc bằng firstOrCreate
            $readRecord = NotificationRead::query()->firstOrCreate([
                'notification_id' => $notification->id,
                'admin_id' => $admin->id,
            ], [
                'read_at' => now(),
            ]);

            return ApiResponse::responseJson(true, 'Đã đánh dấu đọc thông báo', 200, $readRecord, 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Đánh dấu đọc toàn bộ thông báo của admin
     */
    public function readAll(Request $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            // Tìm toàn bộ danh sách thông báo chưa đọc của admin
            $notificationIds = $this->accessibleQuery($admin)
                ->where('status', Notification::STATUS_SENT)
                ->whereDoesntHave('reads', function ($q) use ($admin) {
                    $q->where('admin_id', $admin->id);
                })
                ->pluck('id');

            foreach ($notificationIds as $id) {
                NotificationRead::query()->firstOrCreate([
                    'notification_id' => $id,
                    'admin_id' => $admin->id,
                ], [
                    'read_at' => now(),
                ]);
            }

            return ApiResponse::responseJson(true, 'Đã đánh dấu đọc toàn bộ thông báo', 200, null, 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Truy vấn danh sách thông báo theo quyền hạn của admin
    private function accessibleQuery($admin): Builder
    {
        $query = Notification::query()
            ->where(fn (Builder $targetAdminQuery): Builder => $targetAdminQuery
                ->whereNull('target_admin_id')
                ->orWhere('target_admin_id', $admin->id)
            );

        if (! AdminScope::isSuperAdmin($admin)) {
            $query->where(fn (Builder $scopeQuery): Builder => $scopeQuery
                ->where('created_by', $admin->id)
                ->orWhere('target_admin_id', $admin->id)
                ->orWhere(fn (Builder $buildingQuery): Builder => $buildingQuery
                    ->whereNotNull('building_id')
                    ->whereIn('building_id', function ($buildingIds) use ($admin): void {
                        $buildingIds->select('id')
                            ->from('buildings')
                            ->where('manager_admin_id', $admin->id);
                    })
                )
            );
        }

        return $query;
    }
}
