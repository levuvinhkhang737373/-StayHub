<?php

namespace App\Http\Resources\Admin;

use App\Helpers\ImageHelper;
use App\Helpers\VietQRHelper;
use App\Models\Contract;
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
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'actual_end_date' => optional($this->actual_end_date)->toDateString(),
            'billing_cycle_day' => $this->billing_cycle_day,
            'room_price' => $this->room_price === null ? null : (string) $this->room_price,
            'deposit_amount' => $this->deposit_amount === null ? null : (string) $this->deposit_amount,
            'status' => $this->status,
            'status_label' => Contract::STATUS_LABELS[$this->status] ?? null,
            'payment_status' => $this->payment_status,
            'payment_status_label' => Contract::PAYMENT_STATUS_LABELS[$this->payment_status] ?? null,
            'is_deposit_paid' => $this->is_deposit_paid,
            'deposit_balance' => (string) $this->deposit_balance,
            'deposit_qr_url' => ($this->is_deposit_paid || $this->status === Contract::STATUS_PENDING_SIGN) ? null : VietQRHelper::generateLink(
                null,
                null,
                null,
                (string) $this->deposit_amount,
                $this->contract_code
            ),
            'contract_files' => $this->contractFiles(),
            'tenant_signed_at' => optional($this->tenant_signed_at)->toDateTimeString(),
            'tenant_signature_url' => $this->tenant_signature_url ? ImageHelper::urlFromDisk($this->tenant_signature_url, 'public') : null,
            'landlord_info' => array_merge(config('contract.landlord') ?? [], [
                'signature_url' => config('contract.landlord.signature_url') ? ImageHelper::urlFromDisk(config('contract.landlord.signature_url'), 'public') : null,
            ]),
            'building_name' => $this->relationLoaded('room') && $this->room?->relationLoaded('building') ? $this->room?->building?->name : null,
            'building_address' => $this->relationLoaded('room') && $this->room?->relationLoaded('building') ? $this->room?->building?->address : null,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'contract_tenants' => ContractTenantResource::collection($this->whenLoaded('contractTenants')),
            'contract_vehicles' => ContractVehicleResource::collection($this->whenLoaded('contractVehicles')),
            'deposit_transactions' => ContractDepositTransactionResource::collection($this->whenLoaded('depositTransactions')),
            'contract_tenants_count' => $this->whenCounted('contractTenants'),
            'tenants_count' => $this->whenCounted('tenants'),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'contract_vehicles_count' => $this->whenCounted('contractVehicles'),
            'deposit_transactions_count' => $this->whenCounted('depositTransactions'),
            'room_movements_count' => $this->whenCounted('roomMovements'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
        ];
    }

    private function contractFiles(): array
    {
        return collect($this->contract_files ?? [])
            ->filter(fn ($path): bool => filled($path))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => basename($path),
                'url' => ImageHelper::urlFromDisk($path, 'public'),
            ])
            ->values()
            ->all();
    }
}
