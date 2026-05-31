<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractDetailResource extends JsonResource
{
    /**
     * Dữ liệu hợp đồng đầy đủ theo schema hiện tại.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_code' => $this->contract_code,
            'room_id' => $this->room_id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'representative_tenant_id' => $this->representative_tenant_id,
            'representative_tenant' => new TenantResource($this->whenLoaded('representativeTenant')),
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'actual_end_date' => optional($this->actual_end_date)->toDateString(),
            'billing_cycle_day' => $this->billing_cycle_day,
            'room_price' => $this->room_price === null ? null : (string) $this->room_price,
            'deposit_amount' => $this->deposit_amount === null ? null : (string) $this->deposit_amount,
            'status' => $this->status,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'contract_tenants_count' => $this->whenCounted('contractTenants'),
            'tenants_count' => $this->whenCounted('tenants'),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'contract_vehicles_count' => $this->whenCounted('contractVehicles'),
            'deposit_transactions_count' => $this->whenCounted('depositTransactions'),
            'room_movements_count' => $this->whenCounted('roomMovements'),
            'invoices_count' => $this->whenCounted('invoices'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
        ];
    }
}
