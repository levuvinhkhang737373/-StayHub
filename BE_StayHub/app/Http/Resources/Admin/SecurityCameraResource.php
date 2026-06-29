<?php

namespace App\Http\Resources\Admin;

use App\Models\SecurityCamera;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecurityCameraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'manager_admin_id' => $this->whenLoaded('building', fn () => $this->building?->manager_admin_id),
            'manager_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->manager?->full_name),
            'name' => $this->name,
            'location' => $this->location,
            'source_type' => $this->source_type,
            'source_type_label' => SecurityCamera::SOURCE_TYPE_LABELS[$this->source_type] ?? 'Không xác định',
            'stream_url' => $this->stream_url,
            'username' => $this->username,
            'has_password' => filled($this->password),
            'is_ai_enabled' => (bool) $this->is_ai_enabled,
            'frame_interval_seconds' => (int) $this->frame_interval_seconds,
            'frames_per_batch' => (int) $this->frames_per_batch,
            'alert_cooldown_seconds' => (int) $this->alert_cooldown_seconds,
            'status' => $this->status,
            'status_label' => SecurityCamera::STATUS_LABELS[$this->status] ?? 'Không xác định',
            'alerts_count' => $this->whenCounted('alerts'),
            'latest_alert' => new FireSafetyAlertResource($this->whenLoaded('latestAlert')),
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'updated_by' => $this->updated_by,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
