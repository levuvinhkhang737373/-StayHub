<?php

namespace App\Http\Resources\Admin;

use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
{
    /**
     * Dữ liệu loại phòng tối ưu cho danh sách.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'default_price' => $this->default_price === null ? null : (float) $this->default_price,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => RoomType::STATUS_LABELS[$this->status] ?? null,
            'is_active' => (int) $this->status === RoomType::STATUS_ACTIVE,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'rooms_count' => $this->whenCounted('rooms'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
