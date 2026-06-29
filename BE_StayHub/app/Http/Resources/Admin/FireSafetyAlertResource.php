<?php

namespace App\Http\Resources\Admin;

use App\Helpers\ImageHelper;
use App\Models\FireSafetyAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FireSafetyAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'security_camera_id' => $this->security_camera_id,
            'camera_name' => $this->whenLoaded('securityCamera', fn (): ?string => $this->securityCamera?->name),
            'camera_location' => $this->whenLoaded('securityCamera', fn (): ?string => $this->securityCamera?->location),
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'source_label' => $this->source_label,
            'risk_level' => $this->risk_level,
            'risk_level_label' => FireSafetyAlert::RISK_LABELS[$this->risk_level] ?? 'Không xác định',
            'detected_fire' => (bool) $this->detected_fire,
            'detected_smoke' => (bool) $this->detected_smoke,
            'detected_smoking' => (bool) $this->detected_smoking,
            'confidence' => (float) $this->confidence,
            'snapshot_path' => $this->snapshot_path,
            'snapshot_url' => $this->snapshot_path ? ImageHelper::load($this->snapshot_path) : null,
            'ai_summary' => $this->ai_summary,
            'raw_ai_payload' => $this->raw_ai_payload,
            'status' => $this->status,
            'status_label' => FireSafetyAlert::STATUS_LABELS[$this->status] ?? 'Không xác định',
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledger_name' => $this->whenLoaded('acknowledger', fn (): ?string => $this->acknowledger?->full_name),
            'acknowledged_at' => optional($this->acknowledged_at)->toDateTimeString(),
            'resolved_by' => $this->resolved_by,
            'resolver_name' => $this->whenLoaded('resolver', fn (): ?string => $this->resolver?->full_name),
            'resolved_at' => optional($this->resolved_at)->toDateTimeString(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
