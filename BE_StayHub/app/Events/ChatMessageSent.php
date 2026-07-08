<?php

namespace App\Events;

use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'redis';

    public string $queue = 'chat';

    public function __construct(public ChatMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;

        if ((int) $conversation->conversation_type === \App\Models\ChatConversation::TYPE_SUPER_ADMIN_MANAGER) {
            return [
                new PrivateChannel('chat.conversation.' . $this->message->conversation_id),
                new PrivateChannel('chat.admin.' . $conversation->super_admin_id),
                new PrivateChannel('chat.admin.' . $conversation->manager_admin_id),
            ];
        }

        return [
            new PrivateChannel('chat.conversation.' . $this->message->conversation_id),
            new PrivateChannel('chat.admin.' . $conversation->manager_admin_id),
            new PrivateChannel('chat.tenant.' . $conversation->tenant_id),
        ];
    }

    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing(['sender', 'conversation.building', 'conversation.room', 'conversation.tenant', 'conversation.manager.managedBuildings', 'conversation.superAdmin', 'conversation.lastMessage.sender']);

        return [
            'message' => (new ChatMessageResource($message))->resolve(),
            'conversation' => (new ChatConversationResource($message->conversation))->resolve(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageSent';
    }

    public function broadcastQueue(): string
    {
        return 'chat';
    }
}
