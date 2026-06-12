<?php

namespace App\Http\Resources\Tenant;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_code' => $this->request_code,
            'tenant_id' => $this->tenant_id,
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'building_name' => $this->whenLoaded('room.building', fn (): ?string => $this->room?->building?->name),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => MaintenanceRequest::STATUS_LABELS[$this->status] ?? null,
            'images' => array_map(fn ($image) => \App\Helpers\ImageHelper::temporaryUrlFromDisk($image), $this->images ?? []),
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->whenLoaded('assignee', fn (): ?string => $this->assignee?->full_name),
            'received_at' => optional($this->received_at)->toDateTimeString(),
            'completed_at' => optional($this->completed_at)->toDateTimeString(),
            'feedback' => $this->feedbacks->first()?->comment, // Lấy comment của phản hồi đầu tiên nếu có
            'rating' => $this->feedbacks->first()?->rating,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
