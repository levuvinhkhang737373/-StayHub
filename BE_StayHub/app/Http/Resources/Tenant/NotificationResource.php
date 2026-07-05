<?php

namespace App\Http\Resources\Tenant;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenantId = $request->user('tenant')?->id;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'notification_type' => $this->notification_type,
            'notification_type_label' => Notification::NOTIFICATION_TYPE_LABELS[$this->notification_type] ?? 'Khác',
            'target_type' => $this->target_type,
            'published_at' => optional($this->published_at)->toDateTimeString(),
            'is_read' => $this->reads->contains('tenant_id', $tenantId),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'creator_name' => $this->creator?->full_name ?? $this->creator?->username,
        ];
    }
}
