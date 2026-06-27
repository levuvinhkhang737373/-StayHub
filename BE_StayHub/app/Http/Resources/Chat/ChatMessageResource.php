<?php

namespace App\Http\Resources\Chat;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sender = $this->resource->relationLoaded('sender') ? $this->sender : null;

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_type' => $this->sender_type,
            'sender_id' => $this->sender_id,
            'sender_role' => $this->sender_role,
            'sender_role_label' => ChatMessage::SENDER_LABELS[$this->sender_role] ?? null,
            'sender_name' => $this->senderName($sender),
            'sender_avatar_url' => $this->senderAvatar($sender),
            'body' => $this->body,
            'queued_at' => optional($this->queued_at)->toDateTimeString(),
            'sent_at' => optional($this->sent_at)->toDateTimeString(),
            'read_at' => optional($this->read_at)->toDateTimeString(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    private function senderName(mixed $sender): ?string
    {
        if (! is_object($sender)) {
            return null;
        }

        return $sender->full_name ?? $sender->username ?? null;
    }

    private function senderAvatar(mixed $sender): ?string
    {
        if (! is_object($sender)) {
            return null;
        }

        return $sender->avatar_url ?? null;
    }
}
