<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MaintenanceRequest\StoreRequest;
use App\Http\Requests\Tenant\MaintenanceRequest\FeedbackRequest;
use App\Http\Resources\Tenant\MaintenanceRequestResource;
use App\Models\MaintenanceFeedback;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceRequestController extends Controller
{
    private const IMAGE_DISK = 'local';

    /**
     * Danh sách phiếu sửa chữa của tenant đang đăng nhập
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            // Tối ưu N+1: load room, building, assignee và feedbacks
            $requests = MaintenanceRequest::query()
                ->where('tenant_id', $tenant->id)
                ->with(['room.building', 'feedbacks'])
                ->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 20));

            return ApiResponse::responseJson(true, 'Danh sách yêu cầu bảo trì', 200, [
                'data' => MaintenanceRequestResource::collection($requests->items())->resolve(),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'last_page' => $requests->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Gửi một yêu cầu sửa chữa mới
     */
    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            // Lấy thông tin phòng của khách thuê
            $currentRoom = $tenant->currentContractTenant?->contract?->room;
            $roomId = $currentRoom?->id;
            if (! $roomId) {
                return ApiResponse::responseJson(false, 'Tài khoản của bạn chưa được liên kết với phòng nào', 422, null, 422);
            }

            // Tạo mã phiếu ngẫu nhiên duy nhất, VD: SC-123456
            $requestCode = 'SC-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            while (MaintenanceRequest::query()->where('request_code', $requestCode)->exists()) {
                $requestCode = 'SC-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            }

            $uploadedPaths = [];

            // Chạy transaction để đảm bảo tính nhất quán dữ liệu
            $result = DB::transaction(function () use ($request, $tenant, $roomId, $currentRoom, $requestCode, &$uploadedPaths) {
                // Xử lý upload ảnh minh chứng trước khi sửa (chấp nhận cả mảng hoặc tệp tin đơn lẻ)
                if ($request->hasFile('images')) {
                    $images = $request->file('images');
                    if (is_array($images)) {
                        foreach ($images as $image) {
                            $folder = 'maintenance/requests/' . $requestCode . '/before';
                            $uploadedPaths[] = ImageHelper::storeOnDisk($image, $folder, self::IMAGE_DISK);
                        }
                    } else {
                        $folder = 'maintenance/requests/' . $requestCode . '/before';
                        $uploadedPaths[] = ImageHelper::storeOnDisk($images, $folder, self::IMAGE_DISK);
                    }
                }

                if ($request->hasFile('image')) {
                    $image = $request->file('image');
                    $folder = 'maintenance/requests/' . $requestCode . '/before';
                    $uploadedPaths[] = ImageHelper::storeOnDisk($image, $folder, self::IMAGE_DISK);
                }

                // 1. Tạo phiếu sửa chữa
                $maintenance = MaintenanceRequest::query()->create([
                    'request_code' => $requestCode,
                    'tenant_id' => $tenant->id,
                    'room_id' => $roomId,
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'status' => MaintenanceRequest::STATUS_CREATED,
                    'images' => $uploadedPaths,
                ]);

                // 2. Ghi nhận lịch sử (Log)
                MaintenanceRequestLog::query()->create([
                    'maintenance_request_id' => $maintenance->id,
                    'old_status' => null,
                    'new_status' => MaintenanceRequest::STATUS_CREATED,
                    'note' => 'Khách thuê tạo yêu cầu sửa chữa.',
                ]);

                // 3. Tạo thông báo cho toàn bộ khách thuê trong phòng (bao gồm người tạo)
                $notification = \App\Models\Notification::query()->create([
                    'title' => 'Yêu cầu sửa chữa mới',
                    'content' => "Yêu cầu sửa chữa '{$maintenance->title}' của phòng bạn đã được gửi thành công bởi {$tenant->full_name} và đang chờ tiếp nhận.",
                    'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_MAINTENANCE,
                    'target_type' => \App\Models\Notification::TARGET_TYPE_ROOM,
                    'action_url' => '/admin/maintenance?id=' . $maintenance->id,
                    'room_id' => $roomId,
                    'building_id' => $currentRoom->building_id,
                    'status' => \App\Models\Notification::STATUS_SENT,
                    'published_at' => now(),
                ]);

                return [
                    'maintenance' => $maintenance,
                    'notification' => $notification,
                ];
            });

            $maintenanceRequest = $result['maintenance'];
            $notification = $result['notification'];

            // Load đầy đủ quan hệ để trả về resource
            $maintenanceRequest->load(['room.building', 'feedbacks']);

            // 4. Phát sự kiện Broadcast
            broadcast(new \App\Events\MaintenanceRequestCreated($maintenanceRequest));
            broadcast(new \App\Events\NotificationSent($notification));

            return ApiResponse::responseJson(true, 'Gửi yêu cầu sửa chữa thành công', 201, new MaintenanceRequestResource($maintenanceRequest), 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Chi tiết phiếu sửa chữa
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $maintenance = MaintenanceRequest::query()
                ->where('tenant_id', $tenant->id)
                ->with(['room.building', 'feedbacks'])
                ->find($id);

            if (! $maintenance) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phiếu sửa chữa yêu cầu', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết phiếu sửa chữa', 200, new MaintenanceRequestResource($maintenance), 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Gửi đánh giá phản hồi chất lượng sửa chữa
     */
    public function feedback(FeedbackRequest $request, int $id): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (! $tenant) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $maintenance = MaintenanceRequest::query()
                ->where('tenant_id', $tenant->id)
                ->find($id);

            if (! $maintenance) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phiếu sửa chữa yêu cầu', 404, null, 404);
            }

            // Chỉ cho phép đánh giá khi phiếu đã hoàn thành (STATUS_COMPLETED = 4)
            if ($maintenance->status !== MaintenanceRequest::STATUS_COMPLETED) {
                return ApiResponse::responseJson(false, 'Bạn chỉ có thể đánh giá phiếu sửa chữa khi trạng thái đã hoàn thành', 422, null, 422);
            }

            // Kiểm tra xem đã có đánh giá nào chưa
            $exists = MaintenanceFeedback::query()
                ->where('maintenance_request_id', $maintenance->id)
                ->where('tenant_id', $tenant->id)
                ->exists();

            if ($exists) {
                return ApiResponse::responseJson(false, 'Bạn đã gửi đánh giá cho phiếu sửa chữa này rồi', 422, null, 422);
            }

            $uploadedFeedbackPaths = [];

            $feedback = DB::transaction(function () use ($request, $tenant, $maintenance, &$uploadedFeedbackPaths) {
                // Upload ảnh phản hồi
                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $image) {
                        $folder = 'maintenance/requests/' . $maintenance->request_code . '/feedback';
                        $uploadedFeedbackPaths[] = ImageHelper::storeOnDisk($image, $folder, self::IMAGE_DISK);
                    }
                }

                // Lưu phản hồi
                $fb = MaintenanceFeedback::query()->create([
                    'maintenance_request_id' => $maintenance->id,
                    'tenant_id' => $tenant->id,
                    'rating' => $request->input('rating'),
                    'comment' => $request->input('comment'),
                    'images' => $uploadedFeedbackPaths,
                ]);

                // Tạo thông báo lưu vào database cho Ban quản lý (TARGET_TYPE_ADMIN)
                $commentText = $fb->comment ? ": \"{$fb->comment}\"" : " (không có nội dung)";
                \App\Models\Notification::query()->create([
                    'title' => 'Phản hồi mới từ khách thuê',
                    'content' => "Khách thuê '{$tenant->full_name}' đã gửi phản hồi cho yêu cầu '{$maintenance->title}'{$commentText}",
                    'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_MAINTENANCE,
                    'target_type' => \App\Models\Notification::TARGET_TYPE_ADMIN,
                    'action_url' => '/admin/maintenance?id=' . $maintenance->id,
                    'tenant_id' => $tenant->id,
                    'room_id' => $maintenance->room_id,
                    'building_id' => $maintenance->room?->building_id,
                    'status' => \App\Models\Notification::STATUS_SENT,
                    'published_at' => now(),
                ]);

                return $fb;
            });

            // Phát sự kiện real-time tới Admin đối với mọi phản hồi (feedback)
            broadcast(new \App\Events\MaintenanceFeedbackCreated($feedback));

            return ApiResponse::responseJson(true, 'Gửi phản hồi thành công', 201, $feedback, 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
