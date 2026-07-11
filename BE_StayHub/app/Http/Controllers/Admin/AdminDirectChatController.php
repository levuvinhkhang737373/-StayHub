<?php

namespace App\Http\Controllers\Admin;

use App\Events\ChatConversationRead;
use App\Events\ChatMessageSent;
use App\Events\NotificationSent;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Chat\IndexConversationRequest;
use App\Http\Requests\Admin\Chat\MessageIndexRequest;
use App\Http\Requests\Admin\Chat\SendMessageRequest;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\Admin;
use App\Models\Building;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDirectChatController extends Controller
{
    // Danh sách các cuộc trò chuyện trực tiếp của admin
    public function index(IndexConversationRequest $request): JsonResponse
    {
        try {
            /** @var Admin|null $admin */
            $admin = $request->user('admin');
            if (! $admin || (! AdminScope::isSuperAdmin($admin) && ! AdminScope::isBuildingManager($admin))) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập đoạn chat quản trị', 403, null, 403);
            }

            $this->syncConversationsFor($admin);
            $validatedData = $request->validated();

            $query = ChatConversation::query()
                ->with(['superAdmin', 'manager.managedBuildings', 'lastMessage.sender'])
                ->where('conversation_type', ChatConversation::TYPE_SUPER_ADMIN_MANAGER)
                ->when(
                    AdminScope::isSuperAdmin($admin),
                    fn (Builder $query): Builder => $query
                        ->where('super_admin_id', $admin->id)
                        ->whereHas('manager', function (Builder $managerQuery): void {
                            $managerQuery
                                ->where('role', Admin::ROLE_BUILDING_MANAGER)
                                ->where('status', Admin::STATUS_ACTIVE)
                                ->whereHas('managedBuildings', function (Builder $buildingQuery): void {
                                    $buildingQuery->where('status', Building::STATUS_ACTIVE);
                                });
                        }),
                    fn (Builder $query): Builder => $query
                        ->where('manager_admin_id', $admin->id)
                        ->whereHas('superAdmin', function (Builder $superAdminQuery): void {
                            $superAdminQuery
                                ->where('role', Admin::ROLE_SUPER_ADMIN)
                                ->where('status', Admin::STATUS_ACTIVE);
                        })
                )
                ->when($request->boolean('unread'), function (Builder $query) use ($admin): void {
                    $query->where(function (Builder $sub) use ($admin): void {
                        $sub->where(fn ($q) => $q->where('super_admin_id', $admin->id)->where('admin_unread_count', '>', 0))
                            ->orWhere(fn ($q) => $q->where('manager_admin_id', $admin->id)->where('tenant_unread_count', '>', 0));
                    });
                })
                ->when(filled($validatedData['keyword'] ?? null), function (Builder $query) use ($validatedData): void {
                    $keyword = trim((string) $validatedData['keyword']);
                    $query->where(function (Builder $q) use ($keyword): void {
                        $q->whereHas('manager', function (Builder $adminQuery) use ($keyword): void {
                            $adminQuery->where('full_name', 'like', '%' . $keyword . '%')
                                ->orWhere('username', 'like', '%' . $keyword . '%')
                                ->orWhere('email', 'like', '%' . $keyword . '%')
                                ->orWhere('phone', 'like', '%' . $keyword . '%');
                        })->orWhereHas('superAdmin', function (Builder $adminQuery) use ($keyword): void {
                            $adminQuery->where('full_name', 'like', '%' . $keyword . '%')
                                ->orWhere('username', 'like', '%' . $keyword . '%')
                                ->orWhere('email', 'like', '%' . $keyword . '%')
                                ->orWhere('phone', 'like', '%' . $keyword . '%');
                        });
                    });
                })
                ->orderByRaw('COALESCE(last_message_at, updated_at) DESC')
                ->orderByDesc('id');

            $conversations = $query->paginate((int) ($validatedData['per_page'] ?? 20));

            return ApiResponse::responseJson(true, 'Danh sách đoạn chat quản trị', 200, [
                'data' => ChatConversationResource::collection($conversations->items())->resolve(),
                'pagination' => [
                    'current_page' => $conversations->currentPage(),
                    'per_page' => $conversations->perPage(),
                    'total' => $conversations->total(),
                    'last_page' => $conversations->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Danh sách tin nhắn trong cuộc trò chuyện trực tiếp
    public function messages(MessageIndexRequest $request, ChatConversation $conversation): JsonResponse
    {
        try {
            $authorization = $this->authorizeConversation($request->user('admin'), $conversation);
            if ($authorization instanceof JsonResponse) {
                return $authorization;
            }

            $validatedData = $request->validated();
            $perPage = (int) ($validatedData['per_page'] ?? 30);
            $messages = $conversation->messages()
                ->with('sender')
                ->when(isset($validatedData['before_id']), fn (Builder $query): Builder => $query->where('id', '<', (int) $validatedData['before_id']))
                ->orderByDesc('id')
                ->limit($perPage + 1)
                ->get();
            $hasMore = $messages->count() > $perPage;
            $messages = $messages->take($perPage)->reverse()->values();

            return ApiResponse::responseJson(true, 'Danh sách tin nhắn quản trị', 200, [
                'data' => ChatMessageResource::collection($messages)->resolve(),
                'pagination' => [
                    'has_more' => $hasMore,
                    'oldest_id' => $messages->first()?->id,
                    'newest_id' => $messages->last()?->id,
                ],
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Gửi tin nhắn trực tiếp đến admin khác
    public function sendMessage(SendMessageRequest $request, ChatConversation $conversation): JsonResponse
    {
        try {
            /** @var Admin|null $admin */
            $admin = $request->user('admin');
            $authorization = $this->authorizeConversation($admin, $conversation);
            if ($authorization instanceof JsonResponse) {
                return $authorization;
            }

            $validatedData = $request->validated();
            $attachments = null;
            if ($request->hasFile('images')) {
                $attachments = [];
                foreach ($request->file('images') as $image) {
                    $attachments[] = ImageHelper::create($image, 'chats');
                }
            }

            $message = DB::transaction(function () use ($admin, $conversation, $validatedData, $attachments): ChatMessage {
                $lockedConversation = ChatConversation::query()
                    ->whereKey($conversation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $message = $lockedConversation->messages()->create([
                    'sender_type' => 'admin',
                    'sender_id' => $admin->id,
                    'sender_role' => ChatMessage::SENDER_ADMIN,
                    'body' => trim((string) ($validatedData['body'] ?? '')),
                    'attachments' => $attachments,
                    'queued_at' => now(),
                    'sent_at' => now(),
                ]);

                if ((int) $lockedConversation->super_admin_id === (int) $admin->id) {
                    $lockedConversation->forceFill([
                        'last_message_id' => $message->id,
                        'last_message_at' => $message->created_at,
                        'tenant_unread_count' => $lockedConversation->tenant_unread_count + 1,
                        'admin_unread_count' => 0,
                        'admin_last_read_at' => now(),
                        'status' => ChatConversation::STATUS_ACTIVE,
                    ])->save();
                } else {
                    $lockedConversation->forceFill([
                        'last_message_id' => $message->id,
                        'last_message_at' => $message->created_at,
                        'admin_unread_count' => $lockedConversation->admin_unread_count + 1,
                        'tenant_unread_count' => 0,
                        'tenant_last_read_at' => now(),
                        'status' => ChatConversation::STATUS_ACTIVE,
                    ])->save();
                }

                $this->createRecipientNotification($lockedConversation, $message, $admin);
                $conversation->refresh();

                return $message;
            });

            $message->load(['sender', 'conversation.superAdmin', 'conversation.manager.managedBuildings', 'conversation.lastMessage.sender']);
            event(new ChatMessageSent($message));

            return ApiResponse::responseJson(true, 'Gửi tin nhắn thành công', 201, [
                'message' => (new ChatMessageResource($message))->resolve(),
                'conversation' => (new ChatConversationResource($message->conversation))->resolve(),
            ], 201);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Đánh dấu đã đọc toàn bộ tin nhắn trong cuộc trò chuyện trực tiếp
    public function markAsRead(ChatConversation $conversation): JsonResponse
    {
        try {
            /** @var Admin|null $admin */
            $admin = request()->user('admin');
            $authorization = $this->authorizeConversation($admin, $conversation);
            if ($authorization instanceof JsonResponse) {
                return $authorization;
            }

            if ((int) $conversation->super_admin_id === (int) $admin->id) {
                $conversation->forceFill([
                    'admin_unread_count' => 0,
                    'admin_last_read_at' => now(),
                ])->save();

                $conversation->messages()
                    ->where('sender_id', $conversation->manager_admin_id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);

                $readerType = 'super_admin';
            } else {
                $conversation->forceFill([
                    'tenant_unread_count' => 0,
                    'tenant_last_read_at' => now(),
                ])->save();

                $conversation->messages()
                    ->where('sender_id', $conversation->super_admin_id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);

                $readerType = 'manager';
            }

            $conversation->load(['superAdmin', 'manager.managedBuildings', 'lastMessage.sender']);
            event(new ChatConversationRead($conversation, $readerType));

            return ApiResponse::responseJson(true, 'Đã đánh dấu đoạn chat là đã đọc', 200, (new ChatConversationResource($conversation))->resolve(), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Đồng bộ danh sách cuộc trò chuyện cho admin chỉ định
    private function syncConversationsFor(Admin $admin): void
    {
        if (AdminScope::isSuperAdmin($admin)) {
            Admin::query()
                ->where('role', Admin::ROLE_BUILDING_MANAGER)
                ->where('status', Admin::STATUS_ACTIVE)
                ->whereHas('managedBuildings', function (Builder $query): void {
                    $query->where('status', Building::STATUS_ACTIVE);
                })
                ->where('id', '!=', $admin->id)
                ->select('id')
                ->chunkById(100, function ($otherAdmins) use ($admin): void {
                    foreach ($otherAdmins as $otherAdmin) {
                        $this->firstOrCreateConversation($admin->id, $otherAdmin->id);
                    }
                });
        } else {
            Admin::query()
                ->where('role', Admin::ROLE_SUPER_ADMIN)
                ->where('status', Admin::STATUS_ACTIVE)
                ->where('id', '!=', $admin->id)
                ->select('id')
                ->chunkById(100, function ($otherAdmins) use ($admin): void {
                    foreach ($otherAdmins as $otherAdmin) {
                        $this->firstOrCreateConversation($otherAdmin->id, $admin->id);
                    }
                });
        }
    }

    // Truy vấn danh sách quản lý tòa nhà đang hoạt động
    private function activeBuildingManagerQuery(): Builder
    {
        return Admin::query()
            ->where('role', Admin::ROLE_BUILDING_MANAGER)
            ->where('status', Admin::STATUS_ACTIVE);
    }

    // Tìm hoặc tạo cuộc trò chuyện trực tiếp mới giữa hai admin
    private function firstOrCreateConversation(int $superAdminId, int $managerAdminId): ChatConversation
    {
        try {
            return ChatConversation::query()->firstOrCreate([
                'conversation_type' => ChatConversation::TYPE_SUPER_ADMIN_MANAGER,
                'super_admin_id' => $superAdminId,
                'manager_admin_id' => $managerAdminId,
            ], [
                'status' => ChatConversation::STATUS_ACTIVE,
            ]);
        } catch (UniqueConstraintViolationException) {
            return ChatConversation::query()
                ->where('conversation_type', ChatConversation::TYPE_SUPER_ADMIN_MANAGER)
                ->where('super_admin_id', $superAdminId)
                ->where('manager_admin_id', $managerAdminId)
                ->firstOrFail();
        }
    }

    // Kiểm tra quyền tham gia cuộc trò chuyện trực tiếp của admin
    private function authorizeConversation(?Admin $admin, ChatConversation $conversation): true|JsonResponse
    {
        if (! $admin) {
            return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập với tài khoản admin', 401, null, 401);
        }

        if ((int) $conversation->conversation_type !== ChatConversation::TYPE_SUPER_ADMIN_MANAGER) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập đoạn chat này', 403, null, 403);
        }

        if (AdminScope::isSuperAdmin($admin)
            && (int) $conversation->super_admin_id === (int) $admin->id
            && $this->hasActiveBuildingManagerParticipant($conversation)
        ) {
            return true;
        }

        if (AdminScope::isBuildingManager($admin)
            && (int) $conversation->manager_admin_id === (int) $admin->id
            && $this->hasActiveSuperAdminParticipant($conversation)
        ) {
            return true;
        }

        return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập đoạn chat này', 403, null, 403);
    }

    // Tạo thông báo tin nhắn mới cho admin nhận tin
    private function createRecipientNotification(ChatConversation $conversation, ChatMessage $message, Admin $sender): void
    {
        $bodyText = trim((string) $message->body);
        if (empty($bodyText) && ! empty($message->attachments)) {
            $bodyText = '[Hình ảnh]';
        }

        $recipientId = $this->recipientAdminId($conversation, $sender);
        $senderName = $sender->full_name ?? $sender->username;
        $actionUrl = '/admin/chat?tab=direct&direct_conversation_id=' . $conversation->id;

        Notification::query()
            ->where('notification_type', Notification::NOTIFICATION_TYPE_CHAT)
            ->where('target_type', Notification::TARGET_TYPE_ADMIN)
            ->where('target_admin_id', $recipientId)
            ->where('action_url', $actionUrl)
            ->delete();

        $notification = Notification::query()->create([
            'title' => 'Tin nhắn mới từ ' . $senderName,
            'content' => $senderName . ': ' . str($bodyText)->limit(160)->toString(),
            'notification_type' => Notification::NOTIFICATION_TYPE_CHAT,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'action_url' => $actionUrl,
            'building_id' => null,
            'room_id' => null,
            'tenant_id' => null,
            'target_admin_id' => $recipientId,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $sender->id,
        ]);

        DB::afterCommit(fn (): mixed => event(new NotificationSent($notification)));
    }

    // Xác định ID của admin nhận tin nhắn trong cuộc hội thoại
    private function recipientAdminId(ChatConversation $conversation, Admin $sender): int
    {
        return (int) $conversation->super_admin_id === (int) $sender->id ? (int) $conversation->manager_admin_id : (int) $conversation->super_admin_id;
    }

    // Kiểm tra có Super Admin nào đang hoạt động tham gia không
    private function hasActiveSuperAdminParticipant(ChatConversation $conversation): bool
    {
        return Admin::query()
            ->whereKey($conversation->super_admin_id)
            ->where('role', Admin::ROLE_SUPER_ADMIN)
            ->where('status', Admin::STATUS_ACTIVE)
            ->exists();
    }

    // Kiểm tra có Quản lý tòa nhà nào đang hoạt động tham gia không
    private function hasActiveBuildingManagerParticipant(ChatConversation $conversation): bool
    {
        return Admin::query()
            ->whereKey($conversation->manager_admin_id)
            ->where('role', Admin::ROLE_BUILDING_MANAGER)
            ->where('status', Admin::STATUS_ACTIVE)
            ->whereHas('managedBuildings', function (Builder $query): void {
                $query->where('status', Building::STATUS_ACTIVE);
            })
            ->exists();
    }
}
