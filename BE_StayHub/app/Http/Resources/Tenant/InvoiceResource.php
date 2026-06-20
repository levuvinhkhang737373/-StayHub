<?php

namespace App\Http\Resources\Tenant;

use App\Helpers\DecimalMoney;
use App\Helpers\VietQRHelper;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_code' => $this->invoice_code,
            'contract_id' => $this->contract_id,
            'contract_code' => $this->whenLoaded('contract', fn (): ?string => $this->contract?->contract_code),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'building_name' => $this->whenLoaded('room', fn (): ?string => $this->room?->relationLoaded('building') ? $this->room?->building?->name : null),
            'billing_month' => $this->billing_month,
            'billing_year' => $this->billing_year,
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'due_date' => optional($this->due_date)->toDateString(),
            'status' => $this->status,
            'status_label' => Invoice::STATUS_LABELS[$this->status] ?? null,
            'payment_qr_url' => $this->paymentQrUrl(),
            'issued_at' => optional($this->issued_at)->toDateTimeString(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }

    private function paymentQrUrl(): ?string
    {
        if ((int) $this->status === Invoice::STATUS_PAID || (int) $this->status === Invoice::STATUS_CANCELLED) {
            return null;
        }

        if (! DecimalMoney::isPositive($this->remaining_amount)) {
            return null;
        }

        return VietQRHelper::generateLink(null, null, null, (string) $this->remaining_amount, $this->invoice_code);
    }
}
