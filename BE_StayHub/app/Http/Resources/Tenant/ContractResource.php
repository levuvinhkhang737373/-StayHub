<?php

namespace App\Http\Resources\Tenant;

use App\Helpers\ImageHelper;
use App\Helpers\VietQRHelper;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_code' => $this->contract_code,
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'building_id' => $this->whenLoaded('room', fn () => $this->room?->building_id),
            'building_name' => $this->whenLoaded('room', fn (): ?string => $this->room?->relationLoaded('building') ? $this->room?->building?->name : null),
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
            'deposit_qr_url' => $this->is_deposit_paid ? null : VietQRHelper::generateLink(
                null,
                null,
                null,
                (float) $this->deposit_amount,
                $this->contract_code
            ),
            'contract_files' => $this->contractFiles(),
            'representative_tenant' => $this->relationLoaded('contractTenants') && $this->contractTenants->isNotEmpty()
                ? [
                    'id' => $this->contractTenants->first()->tenant?->id,
                    'full_name' => $this->contractTenants->first()->tenant?->full_name,
                    'phone' => $this->contractTenants->first()->tenant?->phone,
                    'email' => $this->contractTenants->first()->tenant?->email,
                    'identity_number' => $this->contractTenants->first()->tenant?->identity_number,
                ]
                : null,
            'tenant_name' => $this->relationLoaded('contractTenants') && $this->contractTenants->isNotEmpty()
                ? ($this->contractTenants->first()->tenant?->full_name ?? '')
                : null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
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

