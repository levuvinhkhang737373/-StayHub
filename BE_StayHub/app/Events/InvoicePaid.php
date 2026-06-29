<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaid implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $invoice;
    private array $tenantIds;

    public function __construct(Invoice $invoice)
    {
        $invoice->loadMissing(['room.building', 'contract.contractTenants.tenant']);

        $this->tenantIds = $invoice->contract?->contractTenants
            ?->where('is_staying', true)
            ->pluck('tenant_id')
            ->unique()
            ->values()
            ->all() ?? [];

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
            'status' => $invoice->status,
            'status_label' => Invoice::STATUS_LABELS[$invoice->status] ?? null,
        ];
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('admin-maintenance')];

        foreach ($this->tenantIds as $tenantId) {
            $channels[] = new PrivateChannel('tenant.' . $tenantId);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return ['invoice' => $this->invoice];
    }

    public function broadcastAs(): string
    {
        return 'InvoicePaid';
    }
}
