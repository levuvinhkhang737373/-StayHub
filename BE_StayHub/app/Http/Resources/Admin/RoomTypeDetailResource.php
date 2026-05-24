<?php

namespace App\Http\Resources\Admin;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeDetailResource extends JsonResource
{
    /**
     * Dữ liệu loại phòng đầy đủ cho trang chi tiết.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'building_id' => $this->building_id,
            'building' => new BuildingResource($this->whenLoaded('building')),
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'default_price' => $this->default_price === null ? null : (float) $this->default_price,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => RoomType::STATUS_LABELS[$this->status] ?? null,
            'is_active' => (int) $this->status === RoomType::STATUS_ACTIVE,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'full_name' => $this->creator?->full_name,
            ]),
            'rooms_count' => $this->whenCounted('rooms'),
            'rooms' => $this->whenLoaded('rooms', fn () => $this->rooms->map(fn (Room $room): array => [
                'id' => $room->id,
                'building_id' => $room->building_id,
                'building_name' => $room->relationLoaded('building') ? $room->building?->name : null,
                'room_number' => $room->room_number,
                'slug' => $room->slug,
                'base_price' => $room->base_price === null ? null : (float) $room->base_price,
                'max_occupants' => (int) $room->max_occupants,
                'current_occupants' => (int) $room->current_occupants,
                'status' => $room->status,
            ])),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
