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
            'conversation_type' => $this->conversation_type,
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
            'manager_username' => $this->whenLoaded('manager', fn (): ?string => $this->manager?->username),
            'manager_phone' => $this->whenLoaded('manager', fn (): ?string => $this->manager?->phone),
            'manager_email' => $this->whenLoaded('manager', fn (): ?string => $this->manager?->email),
            'manager_avatar_url' => $this->whenLoaded('manager', fn (): ?string => $this->manager?->avatar_url ? \App\Helpers\ImageHelper::load($this->manager->avatar_url) : null),
            'manager_buildings_count' => $this->whenLoaded('manager', fn (): int => $this->manager?->relationLoaded('managedBuildings') ? $this->manager->managedBuildings->count() : 0),
            'manager_building_names' => $this->whenLoaded('manager', fn () => $this->manager?->relationLoaded('managedBuildings') ? $this->manager->managedBuildings->pluck('name')->filter()->values() : []),
            'super_admin_id' => $this->super_admin_id,
            'super_admin_name' => $this->whenLoaded('superAdmin', fn (): ?string => $this->superAdmin?->full_name),
            'super_admin_username' => $this->whenLoaded('superAdmin', fn (): ?string => $this->superAdmin?->username),
            'super_admin_avatar_url' => $this->whenLoaded('superAdmin', fn (): ?string => $this->superAdmin?->avatar_url ? \App\Helpers\ImageHelper::load($this->superAdmin->avatar_url) : null),
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
