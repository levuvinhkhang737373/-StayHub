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
            'images' => array_map(fn ($image) => \App\Helpers\ImageHelper::temporaryUrlFromDisk($image), $this->images ?? []),
            'assigned_to' => null,
            'assignee_name' => null,
            'received_at' => optional($this->received_at)->toDateTimeString(),
            'completed_at' => optional($this->completed_at)->toDateTimeString(),
            'logs' => MaintenanceRequestLogResource::collection($this->whenLoaded('logs')),
            'feedback' => $this->whenLoaded('feedbacks', fn (): ?string => $this->feedbacks->first()?->comment),
            'feedbacks' => $this->whenLoaded('feedbacks', fn () => $this->feedbacks->map(fn ($f) => [
                'id' => $f->id,
                'rating' => $f->rating,
                'comment' => $f->comment,
                'images' => array_map(fn ($img) => \App\Helpers\ImageHelper::temporaryUrlFromDisk($img), $f->images ?? []),
                'created_at' => optional($f->created_at)->toDateTimeString(),
            ])),
            'feedbacks_count' => $this->whenCounted('feedbacks'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
