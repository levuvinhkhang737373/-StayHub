<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaintenanceRequest\AssignRequest;
use App\Http\Requests\Admin\MaintenanceRequest\StatusRequest;
use App\Http\Resources\Admin\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceRequestController extends Controller
{
    private const IMAGE_DISK = 's3';

    /**
     * Danh sách tất cả yêu cầu sửa chữa (Dành cho Admin)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập chức năng này', 403, null, 403);
            }

            // Lọc theo tòa nhà, phòng, trạng thái hoặc từ khóa tìm kiếm
            $query = MaintenanceRequest::query()
                ->with(['tenant', 'room.building', 'assignee', 'logs.creator', 'feedbacks']);

            $query = AdminScope::applyMaintenanceRequestScope($query, $admin);

            $query->when($request->filled('status'), function ($q) use ($request) {
                    $q->where('status', $request->integer('status'));
                })
                ->when($request->filled('building_id'), function ($q) use ($request) {
                    $q->whereHas('room', function ($rq) use ($request) {
                        $rq->where('building_id', $request->integer('building_id'));
                    });
                })
                ->when($request->filled('room_number'), function ($q) use ($request) {
                    $q->whereHas('room', function ($rq) use ($request) {
                        $rq->where('room_number', 'like', '%' . $request->input('room_number') . '%');
                    });
                })
                ->when($request->filled('keyword'), function ($q) use ($request) {
                    $keyword = '%' . $request->input('keyword') . '%';
                    $q->where(function ($sub) use ($keyword) {
                        $sub->where('title', 'like', $keyword)
                            ->orWhere('description', 'like', $keyword)
                            ->orWhere('request_code', 'like', $keyword)
                            ->orWhereHas('tenant', function ($t) use ($keyword) {
                                $t->where('full_name', 'like', $keyword)
                                  ->orWhere('phone', 'like', $keyword);
                            });
                    });
                });

            $requests = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

            return ApiResponse::responseJson(true, 'Danh sách yêu cầu sửa chữa', 200, [
                'data' => MaintenanceRequestResource::collection($requests->items())->resolve(),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'last_page' => $requests->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Chi tiết yêu cầu sửa chữa (Dành cho Admin)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập chức năng này', 403, null, 403);
            }

            $query = MaintenanceRequest::query()
                ->with(['tenant', 'room.building', 'assignee', 'logs.creator', 'feedbacks']);

            $query = AdminScope::applyMaintenanceRequestScope($query, $admin);

            $maintenance = $query->find($id);

            if (! $maintenance) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phiếu sửa chữa yêu cầu hoặc bạn không có quyền xem', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết phiếu sửa chữa', 200, new MaintenanceRequestResource($maintenance), 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Phân công nhân viên sửa chữa
     */
    public function assign(AssignRequest $request, int $id): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập chức năng này', 403, null, 403);
            }

            $maintenance = MaintenanceRequest::query()->find($id);
            if (! $maintenance) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phiếu sửa chữa', 404, null, 404);
            }

            // Kiểm tra quyền phân công phiếu sửa chữa
            if (! AdminScope::canUpdateMaintenanceRequestStatus($admin, $maintenance)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền phân công yêu cầu sửa chữa này', 403, null, 403);
            }

            $oldData = $maintenance->toArray();
            $oldStatus = $maintenance->status;
            // Nếu phiếu đang ở trạng thái Mới tạo (1), chuyển sang Đã tiếp nhận (2)
            $newStatus = ($oldStatus === MaintenanceRequest::STATUS_CREATED)
                ? MaintenanceRequest::STATUS_RECEIVED
                : $oldStatus;

            $result = DB::transaction(function () use ($request, $admin, $maintenance, $oldStatus, $newStatus) {
                $updatePayload = [
                    'assigned_to' => $request->input('assigned_to'),
                    'status' => $newStatus,
                ];

                if ($newStatus === MaintenanceRequest::STATUS_RECEIVED && is_null($maintenance->received_at)) {
                    $updatePayload['received_at'] = now();
                }

                $maintenance->update($updatePayload);

                // Ghi nhận lịch sử (Log)
                MaintenanceRequestLog::query()->create([
                    'maintenance_request_id' => $maintenance->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'note' => 'Admin phân công xử lý yêu cầu sửa chữa.',
                    'created_by' => $admin->id,
                ]);

                // Ghi log hoạt động của admin
                AdminActivityLogger::write(
                    $admin,
                    'assign_maintenance_staff',
                    MaintenanceRequest::class,
                    $maintenance->id,
                    $oldStatus !== $newStatus ? ['status' => $oldStatus, 'assigned_to' => $maintenance->getOriginal('assigned_to')] : null,
                    $maintenance->fresh()->toArray(),
                    $request
                );

                // Tạo thông báo cho Tenant
                $notification = \App\Models\Notification::query()->create([
                    'title' => 'Cập nhật yêu cầu sửa chữa',
                    'content' => "Yêu cầu sửa chữa '{$maintenance->title}' của bạn đã được phân công cho nhân sự bảo trì.",
                    'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_MAINTENANCE,
                    'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
                    'tenant_id' => $maintenance->tenant_id,
                    'room_id' => $maintenance->room_id,
                    'building_id' => $maintenance->room?->building_id,
                    'status' => \App\Models\Notification::STATUS_SENT,
                    'published_at' => now(),
                    'created_by' => $admin->id,
                ]);

                return [
                    'maintenance' => $maintenance,
                    'notification' => $notification,
                ];
            });

            $maintenance = $result['maintenance'];
            $notification = $result['notification'];

            $maintenance->load(['tenant', 'room.building', 'assignee', 'logs.creator', 'feedbacks']);

            // Phát sự kiện Broadcast
            broadcast(new \App\Events\MaintenanceRequestAssigned($maintenance));
            broadcast(new \App\Events\NotificationSent($notification));

            return ApiResponse::responseJson(true, 'Phân công nhân viên thành công', 200, new MaintenanceRequestResource($maintenance), 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Cập nhật trạng thái phiếu sửa chữa
     */
    public function updateStatus(StatusRequest $request, int $id): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập chức năng này', 403, null, 403);
            }

            $maintenance = MaintenanceRequest::query()->find($id);
            if (! $maintenance) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phiếu sửa chữa', 404, null, 404);
            }

            // Kiểm tra quyền cập nhật trạng thái phiếu sửa chữa
            if (! AdminScope::canUpdateMaintenanceRequestStatus($admin, $maintenance)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật trạng thái phiếu sửa chữa này', 403, null, 403);
            }

            $oldData = $maintenance->toArray();
            $oldStatus = $maintenance->status;
            $newStatus = $request->integer('status');
            $note = $request->input('note') ?? 'Admin cập nhật trạng thái phiếu sửa chữa.';

            $result = DB::transaction(function () use ($request, $admin, $maintenance, $oldStatus, $newStatus, $note, $oldData) {
                $images = $maintenance->images ?? [];
                
                // Nếu trạng thái đổi sang Đã hoàn thành (4) và có đính kèm ảnh
                if ($newStatus === MaintenanceRequest::STATUS_COMPLETED && $request->hasFile('after_image')) {
                    $folder = 'maintenance/requests/' . $maintenance->request_code . '/after';
                    $afterImagePath = ImageHelper::storeOnDisk($request->file('after_image'), $folder, self::IMAGE_DISK);
                    
                    // Giữ ảnh trước (nếu có)
                    $beforeImage = count($images) > 0 ? $images[0] : null;
                    $images = array_values(array_filter([$beforeImage, $afterImagePath]));
                }

                $updatePayload = [
                    'status' => $newStatus,
                    'images' => $images,
                ];

                if ($newStatus === MaintenanceRequest::STATUS_RECEIVED && is_null($maintenance->received_at)) {
                    $updatePayload['received_at'] = now();
                }

                if ($newStatus === MaintenanceRequest::STATUS_COMPLETED) {
                    $updatePayload['completed_at'] = now();
                }

                $maintenance->update($updatePayload);

                // Ghi nhận lịch sử (Log)
                MaintenanceRequestLog::query()->create([
                    'maintenance_request_id' => $maintenance->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'note' => $note,
                    'created_by' => $admin->id,
                ]);

                // Ghi log hoạt động của admin
                AdminActivityLogger::write(
                    $admin,
                    'update_maintenance_status',
                    MaintenanceRequest::class,
                    $maintenance->id,
                    $oldData,
                    $maintenance->fresh()->toArray(),
                    $request
                );

                // Lấy nhãn trạng thái mới
                $statusLabel = MaintenanceRequest::STATUS_LABELS[$newStatus] ?? 'Khác';

                // Tạo thông báo cho Tenant
                $notification = \App\Models\Notification::query()->create([
                    'title' => 'Cập nhật yêu cầu sửa chữa',
                    'content' => "Yêu cầu sửa chữa '{$maintenance->title}' của bạn đã chuyển sang trạng thái: {$statusLabel}.",
                    'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_MAINTENANCE,
                    'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
                    'tenant_id' => $maintenance->tenant_id,
                    'room_id' => $maintenance->room_id,
                    'building_id' => $maintenance->room?->building_id,
                    'status' => \App\Models\Notification::STATUS_SENT,
                    'published_at' => now(),
                    'created_by' => $admin->id,
                ]);

                return [
                    'maintenance' => $maintenance,
                    'notification' => $notification,
                ];
            });

            $maintenance = $result['maintenance'];
            $notification = $result['notification'];

            $maintenance->load(['tenant', 'room.building', 'assignee', 'logs.creator', 'feedbacks']);

            // Phát sự kiện Broadcast tùy theo trạng thái
            if ($newStatus === MaintenanceRequest::STATUS_PROCESSING) {
                broadcast(new \App\Events\MaintenanceRequestProcessing($maintenance));
            } elseif ($newStatus === MaintenanceRequest::STATUS_COMPLETED) {
                broadcast(new \App\Events\MaintenanceRequestCompleted($maintenance));
            } else {
                broadcast(new \App\Events\MaintenanceRequestAssigned($maintenance));
            }
            broadcast(new \App\Events\NotificationSent($notification));

            return ApiResponse::responseJson(true, 'Cập nhật trạng thái thành công', 200, new MaintenanceRequestResource($maintenance), 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
