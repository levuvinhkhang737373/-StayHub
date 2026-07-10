<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomServicePriceRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'room_number' => $this->room_number,
            'floor' => $this->floor,
            'status' => $this->status,
            'services' => RoomServicePriceResource::collection($this->whenLoaded('roomServices')),
        ];
    }
}
