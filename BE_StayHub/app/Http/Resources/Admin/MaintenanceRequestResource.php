<?php

namespace App\Http\Resources\Admin;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    /**
     * Dữ liệu phiếu bảo trì tối ưu cho danh sách và chi tiết.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_code' => $this->request_code,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn (): ?string => $this->tenant?->full_name),
            'tenant_phone' => $this->whenLoaded('tenant', fn (): ?string => $this->tenant?->phone),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'building_id' => $this->whenLoaded('room', fn (): ?int => $this->room?->building_id),
            'building_name' => $this->whenLoaded('room.building', fn (): ?string => $this->room?->building?->name),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => MaintenanceRequest::STATUS_LABELS[$this->status] ?? null,
            'images' => $this->images ?? [],
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->whenLoaded('assignee', fn (): ?string => $this->assignee?->full_name),
            'received_at' => optional($this->received_at)->toDateTimeString(),
            'completed_at' => optional($this->completed_at)->toDateTimeString(),
            'logs' => MaintenanceRequestLogResource::collection($this->whenLoaded('logs')),
            'feedbacks_count' => $this->whenCounted('feedbacks'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
