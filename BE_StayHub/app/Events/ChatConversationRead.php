<?php

namespace App\Events;

use App\Http\Resources\Chat\ChatConversationResource;
use App\Models\ChatConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatConversationRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'redis';

    public string $queue = 'chat';

    public function __construct(public ChatConversation $conversation, public string $readerType)
    {
    }

    public function broadcastOn(): array
    {
        if ((int) $this->conversation->conversation_type === \App\Models\ChatConversation::TYPE_SUPER_ADMIN_MANAGER) {
            return [
                new PrivateChannel('chat.conversation.' . $this->conversation->id),
                new PrivateChannel('chat.admin.' . $this->conversation->super_admin_id),
                new PrivateChannel('chat.admin.' . $this->conversation->manager_admin_id),
            ];
        }

        return [
            new PrivateChannel('chat.conversation.' . $this->conversation->id),
            new PrivateChannel('chat.admin.' . $this->conversation->manager_admin_id),
            new PrivateChannel('chat.tenant.' . $this->conversation->tenant_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'reader_type' => $this->readerType,
            'conversation' => (new ChatConversationResource($this->conversation->loadMissing(['building', 'room', 'tenant', 'manager.managedBuildings', 'superAdmin', 'lastMessage.sender'])))->resolve(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatConversationRead';
    }

    public function broadcastQueue(): string
    {
        return 'chat';
    }
}
