<?php

namespace App\Http\Resources\Admin;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'building_id' => $this->whenLoaded('room', fn () => $this->room?->building_id),
            'building_name' => $this->whenLoaded('room', fn (): ?string => $this->room?->relationLoaded('building') ? $this->room?->building?->name : null),
            'tenant_name' => $this->whenLoaded('contract', fn (): ?string => $this->contract?->relationLoaded('contractTenants')
                ? $this->contract?->contractTenants?->first()?->tenant?->full_name
                : null),
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
            'items_count' => $this->whenCounted('items'),
            'payments_count' => $this->whenCounted('payments'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
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
            'rolled_over_amount' => \App\Helpers\DecimalMoney::maxZero(\App\Helpers\DecimalMoney::subtract($rollover->amount, $rollover->settled_amount)),
        ];
    }

    private function collectibleRemainingAmount(?array $rolledOverInfo): string
    {
        if (! $rolledOverInfo) {
            return (string) $this->remaining_amount;
        }

        return \App\Helpers\DecimalMoney::maxZero(\App\Helpers\DecimalMoney::subtract($this->remaining_amount, $rolledOverInfo['rolled_over_amount']));
    }
}
