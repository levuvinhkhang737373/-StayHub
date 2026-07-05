<?php

namespace App\Http\Resources\Admin;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'action_url' => $this->action_url,
            'notification_type' => $this->notification_type,
            'notification_type_label' => Notification::NOTIFICATION_TYPE_LABELS[$this->notification_type] ?? 'Khác',
            'target_type' => $this->target_type,
            'target_type_label' => Notification::TARGET_TYPE_LABELS[$this->target_type] ?? 'Khác',
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building?->name),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn () => $this->room?->room_number),
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn () => $this->tenant?->full_name),
            'target_admin_id' => $this->target_admin_id,
            'published_at' => optional($this->published_at)->toDateTimeString(),
            'status' => $this->status,
            'status_label' => Notification::STATUS_LABELS[$this->status] ?? 'Nháp',
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn () => $this->creator?->full_name),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
