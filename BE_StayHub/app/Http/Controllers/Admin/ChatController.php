<?php

namespace App\Http\Controllers\Admin;

use App\Events\ChatConversationRead;
use App\Events\ChatMessageSent;
use App\Events\NotificationSent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Chat\IndexConversationRequest;
use App\Http\Requests\Admin\Chat\MessageIndexRequest;
use App\Http\Requests\Admin\Chat\SendMessageRequest;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index(IndexConversationRequest $request): JsonResponse
    {
        try {
            /** @var Admin $admin */
            $admin = $request->user('admin');
            $validatedData = $request->validated();

            // Auto-create/sync conversations for all active tenants in buildings managed by this Admin
            $buildingIds = \App\Models\Building::where('manager_admin_id', $admin->id)->pluck('id')->toArray();

            if (!empty($buildingIds)) {
                $activeContractTenants = \App\Models\ContractTenant::query()
                    ->where('is_staying', true)
                    ->whereNull('leave_date')
                    ->whereHas('contract', function (Builder $query): void {
                        $query->where('status', \App\Models\Contract::STATUS_ACTIVE);
                    })
                    ->whereHas('contract.room', function (Builder $query) use ($buildingIds): void {
                        $query->whereIn('building_id', $buildingIds);
                    })
                    ->with(['tenant', 'contract.room.building'])
                    ->get();

                if ($activeContractTenants->isNotEmpty()) {
                    $existingConversations = ChatConversation::query()
                        ->where('manager_admin_id', $admin->id)
                        ->get()
                        ->keyBy(fn ($item) => $item->tenant_id . '-' . $item->building_id);

                    $newConversations = [];
                    $keysToInsert = [];
                    $now = now();

                    foreach ($activeContractTenants as $ct) {
                        $tenant = $ct->tenant;
                        if (!$tenant) continue;

                        $room = $ct->contract?->room;
                        if (!$room) continue;

                        $building = $room->building;
                        if (!$building) continue;

                        $key = $tenant->id . '-' . $building->id;
                        if (isset($keysToInsert[$key])) {
                            continue;
                        }

                        if (!isset($existingConversations[$key])) {
                            $keysToInsert[$key] = true;
                            $newConversations[] = [
                                'tenant_id' => $tenant->id,
                                'building_id' => $building->id,
                                'room_id' => $room->id,
                                'manager_admin_id' => $admin->id,
                                'status' => ChatConversation::STATUS_ACTIVE,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        } else {
                            $conversation = $existingConversations[$key];
                            if ((int) $conversation->room_id !== (int) $room->id) {
                                $conversation->update(['room_id' => $room->id]);
                            }
                        }
                    }

                    if (!empty($newConversations)) {
                        ChatConversation::insert($newConversations);
                    }
                }
            }

            $query = ChatConversation::query()
                ->with(['building', 'room', 'tenant', 'manager', 'lastMessage.sender'])
                ->where('manager_admin_id', $admin->id)
                ->when(isset($validatedData['building_id']), fn (Builder $query): Builder => $query->where('building_id', (int) $validatedData['building_id']))
                ->when($request->boolean('unread'), fn (Builder $query): Builder => $query->where('admin_unread_count', '>', 0))
                ->when(filled($validatedData['keyword'] ?? null), function (Builder $query) use ($validatedData): void {
                    $keyword = trim((string) $validatedData['keyword']);
                    $query->where(function (Builder $subQuery) use ($keyword): void {
                        $subQuery
                            ->whereHas('tenant', function (Builder $tenantQuery) use ($keyword): void {
                                $tenantQuery->where('full_name', 'like', '%' . $keyword . '%')
                                    ->orWhere('phone', 'like', '%' . $keyword . '%')
                                    ->orWhere('username', 'like', '%' . $keyword . '%');
                            })
                            ->orWhereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('room_number', 'like', '%' . $keyword . '%'))
                            ->orWhereHas('building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('name', 'like', '%' . $keyword . '%'));
                    });
                })
                ->orderByRaw('COALESCE(last_message_at, updated_at) DESC')
                ->orderByDesc('id');

            $conversations = $query->paginate((int) ($validatedData['per_page'] ?? 20));

            return ApiResponse::responseJson(true, 'Danh sách đoạn chat', 200, [
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
            $messages = $messages
                ->take($perPage)
                ->reverse()
                ->values();

            return ApiResponse::responseJson(true, 'Danh sách tin nhắn', 200, [
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

    public function sendMessage(SendMessageRequest $request, ChatConversation $conversation): JsonResponse
    {
        try {
            /** @var Admin $admin */
            $admin = $request->user('admin');
            $authorization = $this->authorizeConversation($admin, $conversation);
            if ($authorization instanceof JsonResponse) {
                return $authorization;
            }

            $validatedData = $request->validated();
            $message = DB::transaction(function () use ($admin, $conversation, $validatedData): ChatMessage {
                $lockedConversation = ChatConversation::query()
                    ->whereKey($conversation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $message = $lockedConversation->messages()->create([
                    'sender_type' => 'admin',
                    'sender_id' => $admin->id,
                    'sender_role' => ChatMessage::SENDER_ADMIN,
                    'body' => trim((string) $validatedData['body']),
                    'queued_at' => now(),
                    'sent_at' => now(),
                ]);

                $lockedConversation->forceFill([
                    'last_message_id' => $message->id,
                    'last_message_at' => $message->created_at,
                    'tenant_unread_count' => $lockedConversation->tenant_unread_count + 1,
                    'admin_unread_count' => 0,
                    'admin_last_read_at' => now(),
                    'status' => ChatConversation::STATUS_ACTIVE,
                ])->save();

                $this->createTenantChatNotification($lockedConversation, $message, $admin);

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

    public function markAsRead(ChatConversation $conversation): JsonResponse
    {
        try {
            $authorization = $this->authorizeConversation(request()->user('admin'), $conversation);
            if ($authorization instanceof JsonResponse) {
                return $authorization;
            }

            $conversation->forceFill([
                'admin_unread_count' => 0,
                'admin_last_read_at' => now(),
            ])->save();

            $conversation->messages()
                ->where('sender_role', ChatMessage::SENDER_TENANT)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $conversation->load(['building', 'room', 'tenant', 'manager', 'lastMessage.sender']);
            event(new ChatConversationRead($conversation, 'admin'));

            return ApiResponse::responseJson(true, 'Đã đánh dấu đoạn chat là đã đọc', 200, (new ChatConversationResource($conversation))->resolve(), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    private function authorizeConversation(?Admin $admin, ChatConversation $conversation): true|JsonResponse
    {
        if (! $admin) {
            return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập với tài khoản admin', 401, null, 401);
        }

        if ((int) $conversation->manager_admin_id !== (int) $admin->id) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập đoạn chat này', 403, null, 403);
        }

        return true;
    }

    private function createTenantChatNotification(ChatConversation $conversation, ChatMessage $message, Admin $admin): void
    {
        // Clean up any existing unread chat notifications for this tenant to combine them
        Notification::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('notification_type', Notification::NOTIFICATION_TYPE_CHAT)
            ->where('target_type', Notification::TARGET_TYPE_TENANT)
            ->whereDoesntHave('reads', function ($q) use ($conversation) {
                $q->where('tenant_id', $conversation->tenant_id);
            })
            ->delete();

        $notification = Notification::query()->create([
            'title' => 'Tin nhắn mới từ quản lý',
            'content' => 'Phòng ' . ($conversation->room?->room_number ?? $conversation->room_id) . ': ' . str($message->body)->limit(160)->toString(),
            'notification_type' => Notification::NOTIFICATION_TYPE_CHAT,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'building_id' => $conversation->building_id,
            'room_id' => $conversation->room_id,
            'tenant_id' => $conversation->tenant_id,
            'target_admin_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]);

        DB::afterCommit(fn (): mixed => event(new NotificationSent($notification)));
    }
}
