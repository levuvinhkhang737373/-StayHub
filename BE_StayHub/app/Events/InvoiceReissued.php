<?php

namespace App\Events;

use App\Helpers\DecimalMoney;
use App\Helpers\VietQRHelper;
use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceReissued implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $invoice;
    private array $tenantIds;
    private bool $broadcastToAdmin;

    public function __construct(Invoice $invoice, ?array $tenantIds = null, bool $broadcastToAdmin = true)
    {
        $invoice->loadMissing(['room.building', 'contract.contractTenants.tenant']);

        $this->tenantIds = $tenantIds !== null
            ? collect($tenantIds)->map(fn ($tenantId): int => (int) $tenantId)->unique()->values()->all()
            : ($invoice->contract?->contractTenants
                ?->where('is_staying', true)
                ->pluck('tenant_id')
                ->unique()
                ->values()
                ->all() ?? []);
        $this->broadcastToAdmin = $broadcastToAdmin;

        $this->invoice = [
            'id' => $invoice->id,
            'invoice_code' => $invoice->invoice_code,
            'contract_id' => $invoice->contract_id,
            'contract_code' => $invoice->contract?->contract_code,
            'room_id' => $invoice->room_id,
            'room_number' => $invoice->room?->room_number,
            'building_id' => $invoice->room?->building_id,
            'building_name' => $invoice->room?->building?->name,
            'billing_month' => $invoice->billing_month,
            'billing_year' => $invoice->billing_year,
            'total_amount' => (string) $invoice->total_amount,
            'paid_amount' => (string) $invoice->paid_amount,
            'remaining_amount' => (string) $invoice->remaining_amount,
            'due_date' => optional($invoice->due_date)->toDateString(),
            'status' => $invoice->status,
            'status_label' => Invoice::STATUS_LABELS[$invoice->status] ?? null,
            'payment_qr_url' => $this->paymentQrUrl($invoice),
            'issued_at' => optional($invoice->issued_at)->toDateTimeString(),
            'revision' => $invoice->revision ?? 1,
            'reissued_at' => optional($invoice->reissued_at)->toDateTimeString(),
            'reissue_reason' => $invoice->reissue_reason,
        ];
    }

    public function broadcastOn(): array
    {
        $channels = collect($this->tenantIds)
            ->map(fn (int $tenantId): PrivateChannel => new PrivateChannel('tenant.' . $tenantId))
            ->values();

        if ($this->broadcastToAdmin) {
            $channels->push(new PrivateChannel('admin-maintenance'));
        }

        return $channels->all();
    }

    public function broadcastWith(): array
    {
        return ['invoice' => $this->invoice];
    }

    public function broadcastAs(): string
    {
        return 'InvoiceReissued';
    }

    private function paymentQrUrl(Invoice $invoice): ?string
    {
        if (in_array((int) $invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
            return null;
        }

        if (! DecimalMoney::isPositive($invoice->remaining_amount)) {
            return null;
        }

        return VietQRHelper::generateLink(null, null, null, (string) $invoice->remaining_amount, $invoice->invoice_code);
    }
}
