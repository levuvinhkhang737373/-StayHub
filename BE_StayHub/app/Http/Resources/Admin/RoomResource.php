<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Dữ liệu phòng tối ưu cho danh sách.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'room_type_id' => $this->room_type_id,
            'room_type_name' => $this->whenLoaded('roomType', fn (): ?string => $this->roomType?->name),
            'room_code' => $this->room_code,
            'room_number' => $this->room_number,
            'slug' => $this->slug,
            'floor' => $this->floor === null ? null : (int) $this->floor,
            'area_m2' => $this->area_m2 === null ? null : (float) $this->area_m2,
            'base_price' => $this->base_price === null ? null : (string) $this->base_price,
            'max_occupants' => (int) $this->max_occupants,
            'current_occupants' => (int) $this->current_occupants,
            'status' => $this->status,
            'assets_count' => $this->whenCounted('assets'),
            'contracts_count' => $this->whenCounted('contracts'),
            'active_contracts_count' => $this->when(isset($this->active_contracts_count), $this->active_contracts_count),
            'meter_devices_count' => $this->whenCounted('meterDevices'),
            'maintenance_requests_count' => $this->whenCounted('maintenanceRequests'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
