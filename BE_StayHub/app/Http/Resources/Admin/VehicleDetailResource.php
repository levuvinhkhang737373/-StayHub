<?php

namespace App\Http\Resources\Admin;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isActive = (bool) $this->is_active;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn (): ?string => $this->tenant?->full_name),
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant?->id,
                'full_name' => $this->tenant?->full_name,
                'phone' => $this->tenant?->phone,
                'email' => $this->tenant?->email,
            ]),
            'vehicle_type' => $this->vehicle_type,
            'vehicle_type_label' => Vehicle::VEHICLE_TYPE_LABELS[$this->vehicle_type] ?? null,
            'license_plate' => $this->license_plate,
            'brand' => $this->brand,
            'color' => $this->color,
            'is_active' => $isActive,
            'is_active_label' => Vehicle::ACTIVE_LABELS[$isActive] ?? null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
