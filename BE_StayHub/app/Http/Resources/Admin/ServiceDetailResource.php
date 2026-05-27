<?php

namespace App\Http\Resources\Admin;

use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isRequired = (bool) $this->is_required;
        $isActive = (bool) $this->is_active;

        return [
            'id' => $this->id,
            'service_code' => $this->service_code,
            'name' => $this->name,
            'slug' => $this->slug,
            'service_type' => $this->service_type,
            'service_type_label' => Service::SERVICE_TYPE_LABELS[$this->service_type] ?? null,
            'charge_method' => $this->charge_method,
            'charge_method_label' => Service::CHARGE_METHOD_LABELS[$this->charge_method] ?? null,
            'unit_name' => $this->unit_name,
            'is_required' => $isRequired,
            'is_required_label' => Service::REQUIRED_LABELS[$isRequired] ?? null,
            'is_active' => $isActive,
            'is_active_label' => Service::ACTIVE_LABELS[$isActive] ?? null,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'full_name' => $this->creator?->full_name,
            ]),
            'prices_count' => $this->whenCounted('prices'),
            'meter_devices_count' => $this->whenCounted('meterDevices'),
            'invoice_items_count' => $this->whenCounted('invoiceItems'),
            'prices' => $this->whenLoaded('prices', fn () => $this->prices->map(fn (ServicePrice $price): array => [
                'id' => $price->id,
                'building_id' => $price->building_id,
                'building_name' => $price->relationLoaded('building') ? $price->building?->name : null,
                'price' => $price->price === null ? null : (float) $price->price,
                'effective_from' => optional($price->effective_from)->toDateString(),
                'effective_to' => optional($price->effective_to)->toDateString(),
                'status' => $price->status,
                'status_label' => ServicePrice::STATUS_LABELS[$price->status] ?? null,
            ])),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
