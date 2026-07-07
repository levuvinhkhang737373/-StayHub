<?php

namespace App\Http\Controllers\Tenant;

use App\Events\ChatConversationRead;
use App\Events\ChatMessageSent;
use App\Events\NotificationSent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Chat\MessageIndexRequest;
use App\Http\Requests\Tenant\Chat\SendMessageRequest;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\Building;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function conversation(): JsonResponse
    {
        try {
            /** @var Tenant $tenant */
            $tenant = request()->user('tenant');
            $conversation = $this->findOrCreateConversation($tenant);

            if ($conversation instanceof JsonResponse) {
                return $conversation;
            }

            return ApiResponse::responseJson(true, 'Đoạn chat của khách thuê', 200, (new ChatConversationResource($conversation))->resolve(), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function messages(MessageIndexRequest $request): JsonResponse
    {
        try {
            /** @var Tenant $tenant */
            $tenant = $request->user('tenant');
            $conversation = $this->findOrCreateConversation($tenant);

            if ($conversation instanceof JsonResponse) {
                return $conversation;
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
            $messages = $messages
                ->take($perPage)
                ->reverse()
                ->values();

            return ApiResponse::responseJson(true, 'Danh sách tin nhắn', 200, [
                'conversation' => (new ChatConversationResource($conversation))->resolve(),
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

    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            /** @var Tenant $tenant */
            $tenant = $request->user('tenant');
            $conversation = $this->findOrCreateConversation($tenant);

            if ($conversation instanceof JsonResponse) {
                return $conversation;
            }

            $validatedData = $request->validated();
            $attachments = null;
            if ($request->hasFile('images')) {
                $attachments = [];
                foreach ($request->file('images') as $image) {
                    $attachments[] = \App\Helpers\ImageHelper::create($image, 'chats');
                }
            }

            $message = DB::transaction(function () use ($tenant, $conversation, $validatedData, $attachments): ChatMessage {
                $lockedConversation = ChatConversation::query()
                    ->whereKey($conversation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $message = $lockedConversation->messages()->create([
                    'sender_type' => 'tenant',
                    'sender_id' => $tenant->id,
                    'sender_role' => ChatMessage::SENDER_TENANT,
                    'body' => trim((string) ($validatedData['body'] ?? '')),
                    'attachments' => $attachments,
                    'queued_at' => now(),
                    'sent_at' => now(),
                ]);

                $lockedConversation->forceFill([
                    'last_message_id' => $message->id,
                    'last_message_at' => $message->created_at,
                    'tenant_unread_count' => 0,
                    'admin_unread_count' => $lockedConversation->admin_unread_count + 1,
                    'tenant_last_read_at' => now(),
                    'status' => ChatConversation::STATUS_ACTIVE,
                ])->save();

                $this->createAdminChatNotification($lockedConversation, $message, $tenant);

                $conversation->refresh();

                return $message;
            });

            $message->load(['sender', 'conversation.building', 'conversation.room', 'conversation.tenant', 'conversation.manager', 'conversation.lastMessage.sender']);
            event(new ChatMessageSent($message));

            return ApiResponse::responseJson(true, 'Gửi tin nhắn thành công', 201, [
                'message' => (new ChatMessageResource($message))->resolve(),
                'conversation' => (new ChatConversationResource($message->conversation))->resolve(),
            ], 201);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function markAsRead(): JsonResponse
    {
        try {
            /** @var Tenant $tenant */
            $tenant = request()->user('tenant');
            $conversation = ChatConversation::query()
                ->where('tenant_id', $tenant->id)
                ->with(['building', 'room', 'tenant', 'manager', 'lastMessage.sender'])
                ->first();

            if (! $conversation) {
                return ApiResponse::responseJson(true, 'Chưa có đoạn chat cần đánh dấu đọc', 200, null, 200);
            }

            $conversation->forceFill([
                'tenant_unread_count' => 0,
                'tenant_last_read_at' => now(),
            ])->save();

            $conversation->messages()
                ->where('sender_role', ChatMessage::SENDER_ADMIN)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $conversation->refresh()->load(['building', 'room', 'tenant', 'manager', 'lastMessage.sender']);
            event(new ChatConversationRead($conversation, 'tenant'));

            return ApiResponse::responseJson(true, 'Đã đánh dấu đoạn chat là đã đọc', 200, (new ChatConversationResource($conversation))->resolve(), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    private function findOrCreateConversation(Tenant $tenant): ChatConversation|JsonResponse
    {
        $currentRoom = $tenant->contractTenants()
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', fn (Builder $query): Builder => $query->where('status', Contract::STATUS_ACTIVE))
            ->with('contract.room.building.manager')
            ->latest('id')
            ->first()?->contract?->room;

        if (! $currentRoom) {
            return ApiResponse::responseJson(false, 'Bạn chưa có phòng đang thuê nên chưa thể chat với quản lý', 422, null, 422);
        }

        /** @var Building|null $building */
        $building = $currentRoom->building;
        if (! $building?->manager_admin_id) {
            return ApiResponse::responseJson(false, 'Tòa nhà hiện chưa có quản lý phụ trách chat', 422, null, 422);
        }

        try {
            $conversation = ChatConversation::query()->firstOrCreate([
                'conversation_type' => ChatConversation::TYPE_TENANT_MANAGER,
                'tenant_id' => $tenant->id,
                'building_id' => $building->id,
            ], [
                'room_id' => $currentRoom->id,
                'manager_admin_id' => $building->manager_admin_id,
                'status' => ChatConversation::STATUS_ACTIVE,
            ]);
        } catch (UniqueConstraintViolationException) {
            $conversation = ChatConversation::query()
                ->where('conversation_type', ChatConversation::TYPE_TENANT_MANAGER)
                ->where('tenant_id', $tenant->id)
                ->where('building_id', $building->id)
                ->firstOrFail();
        }

        if ((int) $conversation->room_id !== (int) $currentRoom->id || (int) $conversation->manager_admin_id !== (int) $building->manager_admin_id) {
            $conversation->forceFill([
                'room_id' => $currentRoom->id,
                'manager_admin_id' => $building->manager_admin_id,
                'status' => ChatConversation::STATUS_ACTIVE,
            ])->save();
        }

        return $conversation->load(['building', 'room', 'tenant', 'manager', 'lastMessage.sender']);
    }

    private function createAdminChatNotification(ChatConversation $conversation, ChatMessage $message, Tenant $tenant): void
    {
        $bodyText = trim((string) $message->body);
        if (empty($bodyText) && !empty($message->attachments)) {
            $bodyText = '[Hình ảnh]';
        }

        $notification = Notification::query()->create([
            'title' => 'Tin nhắn mới từ khách thuê',
            'content' => 'Phòng ' . ($conversation->room?->room_number ?? $conversation->room_id) . ' - ' . ($tenant->full_name ?? $tenant->username) . ': ' . str($bodyText)->limit(160)->toString(),
            'notification_type' => Notification::NOTIFICATION_TYPE_CHAT,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'action_url' => '/admin/chat?conversation_id=' . $conversation->id,
            'building_id' => $conversation->building_id,
            'room_id' => $conversation->room_id,
            'tenant_id' => $conversation->tenant_id,
            'target_admin_id' => $conversation->manager_admin_id,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => null,
        ]);

        DB::afterCommit(fn (): mixed => event(new NotificationSent($notification)));
    }
}
