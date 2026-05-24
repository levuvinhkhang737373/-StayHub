<?php

namespace App\Http\Resources\Admin;

use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomDetailResource extends JsonResource
{
    /**
     * Dữ liệu phòng đầy đủ cho trang chi tiết.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building' => new BuildingResource($this->whenLoaded('building')),
            'room_type_id' => $this->room_type_id,
            'room_type' => $this->whenLoaded('roomType', fn () => [
                'id' => $this->roomType?->id,
                'name' => $this->roomType?->name,
                'slug' => $this->roomType?->slug,
                'default_price' => $this->roomType?->default_price === null ? null : (float) $this->roomType?->default_price,
                'description' => $this->roomType?->description,
                'status' => $this->roomType?->status,
                'is_active' => (int) $this->roomType?->status === RoomType::STATUS_ACTIVE,
            ]),
            'room_code' => $this->room_code,
            'room_number' => $this->room_number,
            'slug' => $this->slug,
            'floor' => $this->floor === null ? null : (int) $this->floor,
            'area_m2' => $this->area_m2 === null ? null : (float) $this->area_m2,
            'base_price' => (float) $this->base_price,
            'max_occupants' => (int) $this->max_occupants,
            'current_occupants' => (int) $this->current_occupants,
            'status' => $this->status,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'assets_count' => $this->whenCounted('assets'),
            'contracts_count' => $this->whenCounted('contracts'),
            'active_contracts_count' => $this->when(isset($this->active_contracts_count), $this->active_contracts_count),
            'meter_devices_count' => $this->whenCounted('meterDevices'),
            'invoices_count' => $this->whenCounted('invoices'),
            'maintenance_requests_count' => $this->whenCounted('maintenanceRequests'),
            'notifications_count' => $this->whenCounted('notifications'),
            'expenses_count' => $this->whenCounted('expenses'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
        ];
    }
}
