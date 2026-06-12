<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantRoomOptionResource extends JsonResource
{
    
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'room_type_id' => $this->room_type_id,
            'room_type_name' => $this->whenLoaded('roomType', fn (): ?string => $this->roomType?->name),
            'room_number' => $this->room_number,
            'slug' => $this->slug,
            'floor' => $this->floor === null ? null : (int) $this->floor,
            'base_price' => $this->base_price === null ? null : (string) $this->base_price,
            'max_occupants' => (int) $this->max_occupants,
            'current_occupants' => (int) $this->current_occupants,
            'status' => $this->status,
        ];
    }
}
