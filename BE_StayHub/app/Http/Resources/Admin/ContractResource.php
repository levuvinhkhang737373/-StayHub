<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    /**
     * Dữ liệu hợp đồng tối ưu cho danh sách.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_code' => $this->contract_code,
            'room_id' => $this->room_id,
            'room_code' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_code),
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'building_id' => $this->whenLoaded('room', fn () => $this->room?->building_id),
            'building_name' => $this->whenLoaded('room', fn (): ?string => $this->room?->relationLoaded('building') ? $this->room?->building?->name : null),
            'representative_tenant_id' => $this->representative_tenant_id,
            'representative_name' => $this->whenLoaded('representativeTenant', fn (): ?string => $this->representativeTenant?->full_name),
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'billing_cycle_day' => $this->billing_cycle_day,
            'room_price' => $this->room_price === null ? null : (string) $this->room_price,
            'deposit_amount' => $this->deposit_amount === null ? null : (string) $this->deposit_amount,
            'status' => $this->status,
            'tenants_count' => $this->whenCounted('tenants'),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'deposit_transactions_count' => $this->whenCounted('depositTransactions'),
            'invoices_count' => $this->whenCounted('invoices'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
