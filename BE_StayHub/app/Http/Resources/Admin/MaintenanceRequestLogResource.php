<?php

namespace App\Http\Resources\Admin;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestLogResource extends JsonResource
{
    /**
     * Dữ liệu lịch sử xử lý phiếu bảo trì.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_request_id' => $this->maintenance_request_id,
            'old_status' => $this->old_status,
            'old_status_label' => $this->old_status ? MaintenanceRequest::STATUS_LABELS[$this->old_status] ?? null : null,
            'new_status' => $this->new_status,
            'new_status_label' => MaintenanceRequest::STATUS_LABELS[$this->new_status] ?? null,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
