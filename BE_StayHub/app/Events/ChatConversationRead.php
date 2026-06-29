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
        $channels = [
            new PrivateChannel('chat.conversation.' . $this->conversation->id),
            new PrivateChannel('chat.admin.' . $this->conversation->manager_admin_id),
            new PrivateChannel('chat.tenant.' . $this->conversation->tenant_id),
        ];

        // Broadcast to all super admins so their sidebars update in real-time
        foreach (\App\Models\Admin::where('role', \App\Models\Admin::ROLE_SUPER_ADMIN)->pluck('id') as $superAdminId) {
            if ($superAdminId !== $this->conversation->manager_admin_id) {
                $channels[] = new PrivateChannel('chat.admin.' . $superAdminId);
            }
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'reader_type' => $this->readerType,
            'conversation' => (new ChatConversationResource($this->conversation->loadMissing(['building', 'room', 'tenant', 'manager', 'lastMessage.sender'])))->resolve(),
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
