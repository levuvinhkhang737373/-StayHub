<?php

namespace App\Events;

use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
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

        $channels = [
            new PrivateChannel('chat.conversation.' . $this->message->conversation_id),
            new PrivateChannel('chat.admin.' . $conversation->manager_admin_id),
            new PrivateChannel('chat.tenant.' . $conversation->tenant_id),
        ];

        // Broadcast to all super admins so their sidebars update in real-time
        foreach (\App\Models\Admin::where('role', \App\Models\Admin::ROLE_SUPER_ADMIN)->pluck('id') as $superAdminId) {
            if ($superAdminId !== $conversation->manager_admin_id) {
                $channels[] = new PrivateChannel('chat.admin.' . $superAdminId);
            }
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing(['sender', 'conversation.building', 'conversation.room', 'conversation.tenant', 'conversation.manager', 'conversation.lastMessage.sender']);

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
