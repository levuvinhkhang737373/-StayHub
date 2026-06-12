<?php

namespace App\Http\Resources\Admin;

use App\Models\ContractVehicle;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractVehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'vehicle_id' => $this->vehicle_id,
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle?->id,
                'tenant_id' => $this->vehicle?->tenant_id,
                'tenant_name' => $this->vehicle?->relationLoaded('tenant') ? $this->vehicle?->tenant?->full_name : null,
                'vehicle_type' => $this->vehicle?->vehicle_type,
                'vehicle_type_label' => $this->vehicle?->vehicle_type ? (Vehicle::VEHICLE_TYPE_LABELS[$this->vehicle?->vehicle_type] ?? null) : null,
                'license_plate' => $this->vehicle?->license_plate,
                'brand' => $this->vehicle?->brand,
                'color' => $this->vehicle?->color,
                'is_active' => $this->vehicle?->is_active,
            ]),
            'started_at' => optional($this->started_at)->toDateString(),
            'ended_at' => optional($this->ended_at)->toDateString(),
            'billing_start_date' => optional($this->billing_start_date)->toDateString(),
            'billing_end_date' => optional($this->billing_end_date)->toDateString(),
            'monthly_fee' => $this->monthly_fee === null ? null : (string) $this->monthly_fee,
            'charge_policy' => $this->charge_policy,
            'charge_policy_label' => ContractVehicle::CHARGE_POLICY_LABELS[$this->charge_policy] ?? null,
            'is_active' => (bool) $this->is_active,
            'is_active_label' => $this->is_active ? 'Còn tính phí' : 'Hết tính phí',
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
