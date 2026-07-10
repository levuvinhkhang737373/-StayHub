<?php

namespace App\Http\Controllers\Admin;

use App\Events\MaintenanceRequestAssigned;
use App\Events\MaintenanceRequestCompleted;
use App\Events\MaintenanceRequestProcessing;
use App\Events\NotificationSent;
use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaintenanceRequest\IndexRequest;
use App\Http\Requests\Admin\MaintenanceRequest\StatusRequest;
use App\Http\Resources\Admin\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestLog;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceRequestController extends Controller
{
    private const IMAGE_DISK = 'local';

    /**
     * Danh sách tất cả yêu cầu sửa chữa (Dành cho Admin)
     */
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập chức năng này', 403, null, 403);
            }

            $query = MaintenanceRequest::query()
                ->with(['tenant', 'room.building', 'logs.creator', 'feedbacks']);

            $query = AdminScope::applyMaintenanceRequestScope($query, $admin);

            $query->when(isset($validated['status']), function (Builder $q) use ($validated): void {
                $q->where('status', $validated['status']);
            })
                ->when(isset($validated['building_id']), function (Builder $q) use ($validated): void {
                    $q->whereHas('room', function (Builder $rq) use ($validated): void {
                        $rq->where('building_id', $validated['building_id']);
                    });
                })
                ->when(! empty($validated['room_number']), function (Builder $q) use ($validated): void {
                    $q->whereHas('room', function (Builder $rq) use ($validated): void {
                        $rq->where('room_number', 'like', '%'.$validated['room_number'].'%');
                    });
                })
                ->when(! empty($validated['keyword']), function (Builder $q) use ($validated): void {
                    $keyword = '%'.$validated['keyword'].'%';
                    $q->where(function (Builder $sub) use ($keyword): void {
                        $sub->where('title', 'like', $keyword)
                            ->orWhere('description', 'like', $keyword)
                            ->orWhere('request_code', 'like', $keyword)
                            ->orWhereHas('tenant', function (Builder $t) use ($keyword): void {
                                $t->where('full_name', 'like', $keyword)
                                    ->orWhere('phone', 'like', $keyword);
                            });
                    });
                });

            $requests = $query->orderByDesc('created_at')->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách yêu cầu sửa chữa', 200, $this->paginatedResource($requests), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
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
                ->with(['tenant', 'room.building', 'logs.creator', 'feedbacks']);

            $query = AdminScope::applyMaintenanceRequestScope($query, $admin);

            $maintenance = $query->find($id);

            if (! $maintenance) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phiếu sửa chữa yêu cầu hoặc bạn không có quyền xem', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết phiếu sửa chữa', 200, new MaintenanceRequestResource($maintenance), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Phân công nhân viên sửa chữa
     */

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
                    $folder = 'maintenance/requests/'.$maintenance->request_code.'/after';
                    $afterImagePath = ImageHelper::storeOnDisk($request->file('after_image'), $folder, self::IMAGE_DISK);

                    // Giữ ảnh trước (nếu có)
                    $beforeImage = count($images) > 0 ? $images[0] : null;
                    $images = array_values(array_filter([$beforeImage, $afterImagePath]));
                }

                $updatePayload = [
                    'status' => $newStatus,
                    'images' => $images,
                ];

                if ($newStatus === MaintenanceRequest::STATUS_PROCESSING && is_null($maintenance->received_at)) {
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
                    'Cập nhật trạng thái bảo trì',
                    MaintenanceRequest::class,
                    $maintenance->id,
                    $oldData,
                    $maintenance->fresh()->toArray(),
                    $request
                );

                // Lấy nhãn trạng thái mới
                $statusLabel = MaintenanceRequest::STATUS_LABELS[$newStatus] ?? 'Khác';

                // Tạo thông báo cho toàn bộ khách thuê trong phòng
                $notification = Notification::query()->create([
                    'title' => 'Cập nhật yêu cầu sửa chữa',
                    'content' => "Yêu cầu sửa chữa '{$maintenance->title}' của phòng bạn đã chuyển sang trạng thái: {$statusLabel}.",
                    'notification_type' => Notification::NOTIFICATION_TYPE_MAINTENANCE,
                    'target_type' => Notification::TARGET_TYPE_ROOM,
                    'action_url' => '/admin/maintenance?id=' . $maintenance->id,
                    'room_id' => $maintenance->room_id,
                    'building_id' => $maintenance->room?->building_id,
                    'status' => Notification::STATUS_SENT,
                    'published_at' => now(),
                ]);

                return [
                    'maintenance' => $maintenance,
                    'notification' => $notification,
                ];
            });

            $maintenance = $result['maintenance'];
            $notification = $result['notification'];

            $maintenance->load(['tenant', 'room.building', 'logs.creator', 'feedbacks']);

            // Phát sự kiện Broadcast tùy theo trạng thái
            if ($newStatus === MaintenanceRequest::STATUS_PROCESSING) {
                broadcast(new MaintenanceRequestProcessing($maintenance));
            } elseif ($newStatus === MaintenanceRequest::STATUS_COMPLETED) {
                broadcast(new MaintenanceRequestCompleted($maintenance));
            } else {
                broadcast(new MaintenanceRequestAssigned($maintenance));
            }
            broadcast(new NotificationSent($notification));

            return ApiResponse::responseJson(true, 'Cập nhật trạng thái thành công', 200, new MaintenanceRequestResource($maintenance), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function paginatedResource(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => MaintenanceRequestResource::collection($paginator->items())->resolve(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
