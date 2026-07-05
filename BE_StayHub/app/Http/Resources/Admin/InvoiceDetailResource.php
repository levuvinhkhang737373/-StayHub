<?php

namespace App\Http\Resources\Admin;

use App\Helpers\DecimalMoney;
use App\Helpers\VietQRHelper;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rolledOverInfo = $this->rolledOverInfo();

        return [
            'id' => $this->id,
            'invoice_code' => $this->invoice_code,
            'contract_id' => $this->contract_id,
            'contract_code' => $this->whenLoaded('contract', fn (): ?string => $this->contract?->contract_code),
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', fn (): ?array => $this->room ? [
                'id' => $this->room->id,
                'building_id' => $this->room->building_id,
                'building_name' => $this->room->relationLoaded('building') ? $this->room->building?->name : null,
                'room_number' => $this->room->room_number,
                'floor' => $this->room->floor,
                'status' => $this->room->status,
            ] : null),
            'tenants' => $this->tenantSummaries(),
            'billing_month' => $this->billing_month,
            'billing_year' => $this->billing_year,
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'previous_debt_amount' => $this->previous_debt_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'collectible_remaining_amount' => $this->collectibleRemainingAmount($rolledOverInfo),
            'is_debt_rolled_over' => $rolledOverInfo !== null,
            'rolled_to_invoice_id' => $rolledOverInfo['rolled_to_invoice_id'] ?? null,
            'rolled_to_invoice_code' => $rolledOverInfo['rolled_to_invoice_code'] ?? null,
            'rolled_over_amount' => $rolledOverInfo['rolled_over_amount'] ?? '0.00',
            'due_date' => optional($this->due_date)->toDateString(),
            'status' => $this->status,
            'status_label' => Invoice::STATUS_LABELS[$this->status] ?? null,
            'issued_at' => optional($this->issued_at)->toDateTimeString(),
            'revision' => $this->revision ?? 1,
            'reissued_at' => optional($this->reissued_at)->toDateTimeString(),
            'reissue_reason' => $this->reissue_reason,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'updater_name' => $this->whenLoaded('updater', fn (): ?string => $this->updater?->full_name),
            'payment_qr_url' => $this->paymentQrUrl(),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    private function paymentQrUrl(): ?string
    {
        if ($this->rolledOverInfo() !== null) {
            return null;
        }

        if ((int) $this->status === Invoice::STATUS_PAID || (int) $this->status === Invoice::STATUS_CANCELLED) {
            return null;
        }

        if (! DecimalMoney::isPositive($this->remaining_amount)) {
            return null;
        }

        return VietQRHelper::generateLink(null, null, null, (string) $this->remaining_amount, $this->invoice_code);
    }

    private function rolledOverInfo(): ?array
    {
        if (! $this->relationLoaded('debtRolloversOut')) {
            return null;
        }

        $rollover = $this->debtRolloversOut
            ->first(fn ($rollover): bool => (int) $rollover->status === \App\Models\InvoiceDebtRollover::STATUS_ACTIVE
                && (! $rollover->targetInvoice || (int) $rollover->targetInvoice->status !== Invoice::STATUS_CANCELLED));

        if (! $rollover) {
            return null;
        }

        return [
            'rolled_to_invoice_id' => $rollover->target_invoice_id,
            'rolled_to_invoice_code' => $rollover->targetInvoice?->invoice_code,
            'rolled_over_amount' => DecimalMoney::maxZero(DecimalMoney::subtract($rollover->amount, $rollover->settled_amount)),
        ];
    }

    private function collectibleRemainingAmount(?array $rolledOverInfo): string
    {
        if (! $rolledOverInfo) {
            return (string) $this->remaining_amount;
        }

        return DecimalMoney::maxZero(DecimalMoney::subtract($this->remaining_amount, $rolledOverInfo['rolled_over_amount']));
    }

    private function tenantSummaries(): array
    {
        if (! $this->relationLoaded('contract') || ! $this->contract?->relationLoaded('contractTenants')) {
            return [];
        }

        return $this->contract->contractTenants
            ->map(fn ($contractTenant): array => [
                'id' => $contractTenant->tenant?->id,
                'full_name' => $contractTenant->tenant?->full_name,
                'phone' => $contractTenant->tenant?->phone,
                'email' => $contractTenant->tenant?->email,
                'is_staying' => (bool) $contractTenant->is_staying,
            ])
            ->values()
            ->all();
    }
}
