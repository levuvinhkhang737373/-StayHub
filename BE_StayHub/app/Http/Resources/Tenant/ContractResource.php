<?php

namespace App\Http\Resources\Tenant;

use App\Helpers\ImageHelper;
use App\Helpers\DecimalMoney;
use App\Helpers\VietQRHelper;
use App\Models\Contract;
use App\Models\RoomMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStaying = true;
        $tenant = $request->user('tenant');
        if ($tenant) {
            $ct = $this->relationLoaded('contractTenants')
                ? $this->contractTenants->firstWhere('tenant_id', $tenant->id)
                : \App\Models\ContractTenant::where('contract_id', $this->id)->where('tenant_id', $tenant->id)->first();
            if ($ct) {
                $isStaying = (bool) $ct->is_staying;
            }
        }



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
            'deposit_due_amount' => $this->depositDueAmount(),
            'status' => $this->status,
            'status_label' => Contract::STATUS_LABELS[$this->status] ?? null,
            'negotiation_status' => $this->negotiation_status,
            'negotiation_status_label' => Contract::NEGOTIATION_STATUS_LABELS[$this->negotiation_status] ?? 'Không thương lượng',
            'proposed_room_price' => $this->proposed_room_price === null ? null : (string) $this->proposed_room_price,
            'proposed_services' => $this->proposed_services,
            'room_services' => $this->relationLoaded('room') && $this->room->relationLoaded('services')
                ? $this->room->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'charge_method' => $service->charge_method,
                    'charge_method_label' => \App\Models\Service::CHARGE_METHOD_LABELS[$service->charge_method] ?? '',
                    'unit_name' => $service->unit_name,
                    'price' => (string) $service->pivot->price,
                    'is_required' => $service->is_required,
                ])
                : null,
            'is_staying' => $isStaying,
            'payment_status' => $this->payment_status,
            'payment_status_label' => Contract::PAYMENT_STATUS_LABELS[$this->payment_status] ?? null,
            'is_deposit_paid' => $this->is_deposit_paid,
            'deposit_balance' => (string) $this->deposit_balance,
            'deposit_qr_url' => $this->depositQrUrl(),
            'transfer_settlement' => $this->transferSettlementPayload(),
            'contract_files' => $this->contractFiles(),
            'representative_tenant_id' => $this->representative_tenant_id,
            'representative_tenant' => $this->representativeTenantPayload(),
            'tenant_name' => $this->relationLoaded('contractTenants') && $this->contractTenants->isNotEmpty()
                ? ($this->contractTenants->first()->tenant?->full_name ?? '')
                : null,
            'tenant_signed_at' => optional($this->tenant_signed_at)->toDateTimeString(),
            'tenant_signature_url' => $this->tenant_signature_url ? ImageHelper::urlFromDisk($this->tenant_signature_url, 'public') : null,
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

    private function representativeTenantPayload(): ?array
    {
        if ($this->relationLoaded('representativeTenant') && $this->representativeTenant) {
            return [
                'id' => $this->representativeTenant->id,
                'full_name' => $this->representativeTenant->full_name,
                'phone' => $this->representativeTenant->phone,
                'email' => $this->representativeTenant->email,
                'identity_number' => $this->representativeTenant->identity_number,
            ];
        }

        if ($this->relationLoaded('contractTenants') && $this->contractTenants->isNotEmpty()) {
            $tenant = $this->contractTenants->first()->tenant;

            return $tenant ? [
                'id' => $tenant->id,
                'full_name' => $tenant->full_name,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'identity_number' => $tenant->identity_number,
            ] : null;
        }

        return null;
    }

    private function depositDueAmount(): string
    {
        return DecimalMoney::maxZero(DecimalMoney::subtract($this->deposit_amount ?? '0', $this->deposit_balance ?? '0'));
    }

    private function depositQrUrl(): ?string
    {
        if ((int) $this->status === Contract::STATUS_PENDING_SIGN) {
            return null;
        }

        $transferMovement = $this->unpaidTransferMovement();
        if ($transferMovement) {
            $remainingAmount = DecimalMoney::maxZero(DecimalMoney::subtract($transferMovement->settlement_due_amount, $transferMovement->settlement_paid_amount));

            return DecimalMoney::isPositive($remainingAmount)
                ? VietQRHelper::generateLink(null, null, null, $remainingAmount, $transferMovement->transfer_code)
                : null;
        }

        $depositDueAmount = $this->depositDueAmount();

        return DecimalMoney::isPositive($depositDueAmount)
            ? VietQRHelper::generateLink(null, null, null, $depositDueAmount, $this->contract_code)
            : null;
    }

    private function transferSettlementPayload(): ?array
    {
        $movement = $this->unpaidTransferMovement();

        if (! $movement) {
            return null;
        }

        $remainingAmount = DecimalMoney::maxZero(DecimalMoney::subtract($movement->settlement_due_amount, $movement->settlement_paid_amount));
        $depositDueAmount = $movement->deposit_due_amount === null ? '0.00' : (string) $movement->deposit_due_amount;
        $extraChargeAmount = $movement->extra_charge_amount === null ? '0.00' : (string) $movement->extra_charge_amount;
        $paymentSplit = $this->settlementPaymentSplit($movement, $depositDueAmount);

        return [
            'transfer_code' => $movement->transfer_code,
            'deposit_due_amount' => $depositDueAmount,
            'deposit_paid_amount' => $paymentSplit['deposit_paid_amount'],
            'deposit_remaining_amount' => DecimalMoney::maxZero(DecimalMoney::subtract($depositDueAmount, $paymentSplit['deposit_paid_amount'])),
            'extra_charge_amount' => $extraChargeAmount,
            'extra_paid_amount' => $paymentSplit['extra_paid_amount'],
            'extra_remaining_amount' => DecimalMoney::maxZero(DecimalMoney::subtract($extraChargeAmount, $paymentSplit['extra_paid_amount'])),
            'transfer_fee' => $movement->transfer_fee === null ? '0.00' : (string) $movement->transfer_fee,
            'deduction_amount' => $movement->deduction_amount === null ? '0.00' : (string) $movement->deduction_amount,
            'deposit_transfer_amount' => $movement->deposit_transfer_amount === null ? '0.00' : (string) $movement->deposit_transfer_amount,
            'deposit_refund_amount' => $movement->deposit_refund_amount === null ? '0.00' : (string) $movement->deposit_refund_amount,
            'manual_refund_amount' => $movement->manual_refund_amount === null ? '0.00' : (string) $movement->manual_refund_amount,
            'settlement_due_amount' => (string) $movement->settlement_due_amount,
            'settlement_paid_amount' => (string) $movement->settlement_paid_amount,
            'settlement_remaining_amount' => $remainingAmount,
            'settlement_payment_status' => $movement->settlement_payment_status,
            'settlement_payment_status_label' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_LABELS[$movement->settlement_payment_status] ?? null,
            'settlement_qr_url' => DecimalMoney::isPositive($remainingAmount)
                ? VietQRHelper::generateLink(null, null, null, $remainingAmount, $movement->transfer_code)
                : null,
        ];
    }

    private function settlementPaymentSplit(RoomMovement $movement, string $depositDueAmount): array
    {
        $references = collect($movement->settlement_payment_references ?? []);
        $depositPaidAmount = DecimalMoney::add($references->pluck('deposit_amount')->all());
        $extraPaidAmount = DecimalMoney::add($references->pluck('extra_amount')->all());

        if (DecimalMoney::isPositive($depositPaidAmount) || DecimalMoney::isPositive($extraPaidAmount)) {
            return [
                'deposit_paid_amount' => $depositPaidAmount,
                'extra_paid_amount' => $extraPaidAmount,
            ];
        }

        $paidAmount = $movement->settlement_paid_amount === null ? '0.00' : (string) $movement->settlement_paid_amount;
        $inferredDepositPaidAmount = DecimalMoney::min($paidAmount, $depositDueAmount);

        return [
            'deposit_paid_amount' => $inferredDepositPaidAmount,
            'extra_paid_amount' => DecimalMoney::maxZero(DecimalMoney::subtract($paidAmount, $inferredDepositPaidAmount)),
        ];
    }

    private function unpaidTransferMovement(): ?RoomMovement
    {
        if (! $this->id || (int) $this->status === Contract::STATUS_PENDING_SIGN) {
            return null;
        }

        return RoomMovement::query()
            ->where('destination_contract_id', $this->id)
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->where('status', RoomMovement::STATUS_EXECUTED)
            ->whereColumn('settlement_paid_amount', '<', 'settlement_due_amount')
            ->orderByDesc('id')
            ->first();
    }

}
