<?php

namespace App\Http\Resources\Chat;

use App\Models\ChatConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn (): ?string => $this->tenant?->full_name),
            'tenant_phone' => $this->whenLoaded('tenant', fn (): ?string => $this->tenant?->phone),
            'tenant_avatar_url' => $this->whenLoaded('tenant', fn (): ?string => $this->tenant?->avatar_url),
            'manager_admin_id' => $this->manager_admin_id,
            'manager_name' => $this->whenLoaded('manager', fn (): ?string => $this->manager?->full_name),
            'last_message_id' => $this->last_message_id,
            'last_message' => $this->resource->relationLoaded('lastMessage') && $this->lastMessage
                ? (new ChatMessageResource($this->lastMessage))->resolve()
                : null,
            'last_message_at' => optional($this->last_message_at)->toDateTimeString(),
            'tenant_unread_count' => $this->tenant_unread_count,
            'admin_unread_count' => $this->admin_unread_count,
            'tenant_last_read_at' => optional($this->tenant_last_read_at)->toDateTimeString(),
            'admin_last_read_at' => optional($this->admin_last_read_at)->toDateTimeString(),
            'status' => $this->status,
            'status_label' => ChatConversation::STATUS_LABELS[$this->status] ?? null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
