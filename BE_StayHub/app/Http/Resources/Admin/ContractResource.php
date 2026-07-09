<?php

namespace App\Http\Resources\Admin;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    /**
     * Dữ liệu hợp đồng .
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_code' => $this->contract_code,
            'room_id' => $this->room_id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'room_code' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_code),
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'building_id' => $this->whenLoaded('room', fn () => $this->room?->building_id),
            'building_name' => $this->whenLoaded('room', fn (): ?string => $this->room?->relationLoaded('building') ? $this->room?->building?->name : null),
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'actual_end_date' => optional($this->actual_end_date)->toDateString(),
            'room_price' => $this->room_price === null ? null : (string) $this->room_price,
            'deposit_amount' => $this->deposit_amount === null ? null : (string) $this->deposit_amount,
            'deposit_due_amount' => \App\Helpers\DecimalMoney::maxZero(\App\Helpers\DecimalMoney::subtract($this->deposit_amount ?? '0', $this->deposit_balance ?? '0')),
            'status' => $this->status,
            'status_label' => Contract::STATUS_LABELS[$this->status] ?? null,
            'payment_status' => $this->payment_status,
            'payment_status_label' => Contract::PAYMENT_STATUS_LABELS[$this->payment_status] ?? null,
            'is_deposit_paid' => $this->is_deposit_paid,
            'deposit_balance' => (string) $this->deposit_balance,
            'representative_tenant_id' => $this->representative_tenant_id,
            'tenant_name' => $this->relationLoaded('contractTenants') && $this->contractTenants->isNotEmpty()
                ? ($this->contractTenants->first()->tenant?->full_name ?? '')
                : null,
            'contract_tenants_count' => $this->whenCounted('contractTenants'),
            'tenants_count' => $this->whenCounted('tenants'),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'deposit_transactions_count' => $this->whenCounted('depositTransactions'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
