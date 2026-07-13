<?php

namespace Tests\Unit;

use App\Events\ContractDepositPaid;
use App\Events\InvoicePaid;
use App\Events\InvoiceReissued;
use App\Events\MaintenanceFeedbackCreated;
use App\Events\MaintenanceRequestAssigned;
use App\Events\MaintenanceRequestCompleted;
use App\Events\MaintenanceRequestCreated;
use App\Events\MaintenanceRequestProcessing;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MaintenanceFeedback;
use App\Models\MaintenanceRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class AdminRealtimeChannelSeparationTest extends TestCase
{
    public function test_admin_payment_events_only_use_admin_payments_channel(): void
    {
        $contract = new Contract([
            'id' => 10,
            'contract_code' => 'HD-TEST-001',
            'deposit_amount' => '1000000',
            'is_deposit_paid' => true,
        ]);
        $contract->id = 10;
        $contract->setRelation('tenants', collect());
        $contract->setRelation('depositTransactions', collect());

        $invoice = new Invoice([
            'id' => 20,
            'invoice_code' => 'INV-TEST-001',
            'total_amount' => '500000',
            'paid_amount' => '500000',
            'remaining_amount' => '0',
            'status' => Invoice::STATUS_PAID,
        ]);
        $invoice->id = 20;

        $paymentChannels = collect([
            new ContractDepositPaid($contract),
            new InvoicePaid($invoice),
            new InvoiceReissued($invoice, [], true),
        ])->flatMap(fn ($event): array => $this->channelNames($event))
            ->unique()
            ->values()
            ->all();

        $this->assertContains('private-admin-payments', $paymentChannels);
        $this->assertNotContains('private-admin-maintenance', $paymentChannels);
    }

    public function test_admin_maintenance_events_only_use_admin_maintenance_channel(): void
    {
        $maintenance = new MaintenanceRequest([
            'id' => 30,
            'tenant_id' => 40,
            'request_code' => 'SC-000030',
            'title' => 'Sửa vòi nước',
            'description' => 'Vòi nước bị rò',
            'status' => MaintenanceRequest::STATUS_CREATED,
        ]);
        $maintenance->id = 30;

        $feedback = new MaintenanceFeedback([
            'id' => 50,
            'maintenance_request_id' => 30,
            'tenant_id' => 40,
            'rating' => 5,
            'comment' => 'Tốt',
        ]);
        $feedback->id = 50;

        $maintenanceChannels = collect([
            new MaintenanceRequestCreated($maintenance),
            new MaintenanceRequestAssigned($maintenance),
            new MaintenanceRequestProcessing($maintenance),
            new MaintenanceRequestCompleted($maintenance),
            new MaintenanceFeedbackCreated($feedback),
        ])->flatMap(fn ($event): array => $this->channelNames($event))
            ->unique()
            ->values()
            ->all();

        $this->assertContains('private-admin-maintenance', $maintenanceChannels);
        $this->assertNotContains('private-admin-payments', $maintenanceChannels);
    }

    private function channelNames(object $event): array
    {
        return collect($event->broadcastOn())
            ->map(fn (PrivateChannel $channel): string => $channel->name)
            ->values()
            ->all();
    }
}
