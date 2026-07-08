<?php

namespace Tests\Feature\Webhook;

use App\Events\ContractDepositPaid;
use App\Events\InvoicePaid;
use App\Events\NotificationSent;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SePayWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'username' => 'superadmin_test',
            'full_name' => 'Super Admin Test',
            'email' => 'superadmin_test@stayhub.local',
            'phone' => '0901234567',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        $region = Region::create([
            'name' => 'Region Test',
            'code' => 'REG_TEST',
            'created_by' => $this->superAdmin->id,
        ]);

        $building = Building::create([
            'name' => 'Building A',
            'slug' => 'building-a',
            'address' => '123 Test St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $roomType = RoomType::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $room = Room::create([
            'building_id' => $building->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'slug' => '101',
            'floor' => 1,
            'base_price' => '3500000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create a contract waiting for deposit
        $this->contract = Contract::create([
            'contract_code' => 'HD-TEST-WEBHOOK',
            'room_id' => $room->id,
            'start_date' => '2026-06-12',
            'end_date' => '2026-12-12',
            'room_price' => 3500000,
            'deposit_amount' => 3500000,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_sepay_webhook_processes_payment_successfully(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');
        $this->createTenantForContract($this->contract, 'deposit');
        Event::fake([ContractDepositPaid::class, NotificationSent::class]);

        $payload = [
            'id' => 99999,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-06-12 08:30:00',
            'accountNumber' => '99928876789',
            'amount' => 3500000,
            'transferType' => 'in',
            'content' => 'COC HD-TEST-WEBHOOK',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/v1/sepay-webhook', $payload, [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Xử lý thanh toán thành công.'
            ]);

        // Verify transaction is created
        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $this->contract->id,
            'amount' => 3500000.00,
            'transaction_reference' => 'FT12345678',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
        ]);

        // Verify contract status is updated to Paid/Success
        $this->contract->refresh();
        $this->assertEquals(Contract::PAYMENT_STATUS_SUCCESS, $this->contract->payment_status);
        Event::assertDispatched(ContractDepositPaid::class, fn (ContractDepositPaid $event): bool => $event->contract['id'] === $this->contract->id);
        Event::assertDispatched(NotificationSent::class);
    }

    public function test_sepay_webhook_processes_invoice_payment_and_dispatches_realtime_event(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');
        $this->createTenantForContract($this->contract, 'invoice');
        Event::fake([InvoicePaid::class, NotificationSent::class]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-WEBHOOK-001',
            'contract_id' => $this->contract->id,
            'room_id' => $this->contract->room_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => '0.00',
            'total_amount' => '1250000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '1250000.00',
            'due_date' => '2026-07-05',
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->postJson('/api/v1/sepay-webhook', [
            'id' => 88888,
            'gateway' => 'MBBank',
            'amount' => 1250000,
            'transferType' => 'in',
            'content' => 'Thanh toan INV-WEBHOOK-001',
            'code' => 'FT-INVOICE-001',
        ], [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Xử lý thanh toán hóa đơn thành công.'
            ]);

        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEquals('1250000.00', $invoice->paid_amount);
        $this->assertEquals('0.00', $invoice->remaining_amount);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'transaction_reference' => 'FT-INVOICE-001',
            'status' => Payment::STATUS_CONFIRMED,
        ]);
        Event::assertDispatched(InvoicePaid::class, fn (InvoicePaid $event): bool => $event->invoice['id'] === $invoice->id);
        Event::assertDispatched(NotificationSent::class);
    }

    public function test_sepay_webhook_fails_with_invalid_token(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        $payload = [
            'id' => 99999,
            'gateway' => 'MBBank',
            'amount' => 3500000,
            'transferType' => 'in',
            'content' => 'COC HD-TEST-WEBHOOK',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/v1/sepay-webhook', $payload, [
            'Authorization' => 'Apikey wrong-token'
        ]);

        $response->assertStatus(401);
    }

    public function test_sepay_webhook_ignores_outgoing_transfers(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        $payload = [
            'id' => 99999,
            'gateway' => 'MBBank',
            'amount' => 3500000,
            'transferType' => 'out',
            'content' => 'COC HD-TEST-WEBHOOK',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/v1/sepay-webhook', $payload, [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Chỉ xử lý giao dịch nhận tiền.'
            ]);
    }

    public function test_sepay_webhook_ignores_duplicate_reference(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        // Create transaction with this reference first
        ContractDepositTransaction::create([
            'contract_id' => $this->contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => 3500000,
            'transaction_date' => '2026-06-12',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'transaction_reference' => 'FT12345678',
            'created_by' => null,
        ]);

        $payload = [
            'id' => 99999,
            'gateway' => 'MBBank',
            'amount' => 3500000,
            'transferType' => 'in',
            'content' => 'COC HD-TEST-WEBHOOK',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/v1/sepay-webhook', $payload, [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Giao dịch đã được xử lý.'
            ]);
    }

    public function test_sepay_webhook_processes_transfer_extra_charge_without_collecting_destination_deposit(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');
        Event::fake([ContractDepositPaid::class, NotificationSent::class]);

        $room = $this->contract->room()->with('building')->firstOrFail();
        $tenant = Tenant::create([
            'username' => 'transfer_extra_tenant',
            'full_name' => 'Transfer Extra Tenant',
            'email' => 'transfer-extra-tenant@stayhub.local',
            'phone' => '0912222333',
            'password' => bcrypt('password'),
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123123123123',
            'date_of_birth' => '2000-01-01',
            'building_id' => $room->building_id,
            'created_by' => $this->superAdmin->id,
        ]);

        RoomMovement::create([
            'transfer_code' => 'TRF-2026-07-0001',
            'tenant_id' => $tenant->id,
            'contract_id' => $this->contract->id,
            'source_contract_id' => $this->contract->id,
            'destination_contract_id' => $this->contract->id,
            'from_room_id' => $room->id,
            'to_room_id' => $room->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_EXECUTED,
            'movement_date' => '2026-07-01 00:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '0.00',
            'deposit_due_amount' => '0.00',
            'extra_charge_amount' => '500000.00',
            'settlement_due_amount' => '500000.00',
            'settlement_paid_amount' => '0.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
            'settlement_payment_references' => [],
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->postJson('/api/v1/sepay-webhook', [
            'id' => 100001,
            'gateway' => 'MBBank',
            'amount' => 500000,
            'transferType' => 'in',
            'content' => 'Thanh toan TRF-2026-07-0001',
            'code' => 'FT-TRF-EXTRA-001',
        ], [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Xử lý thanh toán chuyển phòng thành công.'
            ]);

        $this->assertDatabaseMissing('contract_deposit_transactions', [
            'contract_id' => $this->contract->id,
            'transaction_reference' => 'FT-TRF-EXTRA-001',
        ]);

        $this->assertDatabaseHas('room_movements', [
            'transfer_code' => 'TRF-2026-07-0001',
            'settlement_paid_amount' => '500000.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
        ]);
        Event::assertNotDispatched(ContractDepositPaid::class);
        Event::assertDispatched(NotificationSent::class);
    }

    public function test_sepay_webhook_splits_transfer_payment_between_deposit_and_extra_charge(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');
        Event::fake([ContractDepositPaid::class, NotificationSent::class]);

        $room = $this->contract->room()->with('building')->firstOrFail();
        $tenant = Tenant::create([
            'username' => 'transfer_split_tenant',
            'full_name' => 'Transfer Split Tenant',
            'email' => 'transfer-split-tenant@stayhub.local',
            'phone' => '0912222444',
            'password' => bcrypt('password'),
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123123123124',
            'date_of_birth' => '2000-01-01',
            'building_id' => $room->building_id,
            'created_by' => $this->superAdmin->id,
        ]);

        $destinationContract = Contract::create([
            'contract_code' => 'HD-TRANSFER-SPLIT',
            'room_id' => $room->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'room_price' => 3500000,
            'deposit_amount' => 3000000,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        RoomMovement::create([
            'transfer_code' => 'TRF-2026-07-0002',
            'tenant_id' => $tenant->id,
            'contract_id' => $destinationContract->id,
            'source_contract_id' => $this->contract->id,
            'destination_contract_id' => $destinationContract->id,
            'from_room_id' => $room->id,
            'to_room_id' => $room->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_EXECUTED,
            'movement_date' => '2026-07-01 00:00:00',
            'old_room_final_amount' => '4000000.00',
            'transfer_fee' => '200000.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '300000.00',
            'manual_refund_amount' => '0.00',
            'deposit_due_amount' => '3000000.00',
            'extra_charge_amount' => '500000.00',
            'settlement_due_amount' => '3500000.00',
            'settlement_paid_amount' => '0.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
            'settlement_payment_references' => [],
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->postJson('/api/v1/sepay-webhook', [
            'id' => 100002,
            'gateway' => 'MBBank',
            'amount' => 3500000,
            'transferType' => 'in',
            'content' => 'Thanh toan TRF-2026-07-0002',
            'code' => 'FT-TRF-SPLIT-001',
        ], [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Xử lý thanh toán chuyển phòng thành công.'
            ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $destinationContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '3000000.00',
            'transaction_reference' => 'FT-TRF-SPLIT-001',
        ]);

        $movement = RoomMovement::query()->where('transfer_code', 'TRF-2026-07-0002')->firstOrFail();
        $this->assertSame('3500000.00', (string) $movement->settlement_paid_amount);
        $this->assertSame(RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID, $movement->settlement_payment_status);
        $this->assertSame('3000000.00', $movement->settlement_payment_references[0]['deposit_amount']);
        $this->assertSame('500000.00', $movement->settlement_payment_references[0]['extra_amount']);

        Event::assertDispatched(ContractDepositPaid::class);
        Event::assertDispatched(NotificationSent::class);
    }

    private function createTenantForContract(Contract $contract, string $suffix): Tenant
    {
        $tenant = Tenant::create([
            'username' => "webhook_{$suffix}_tenant",
            'full_name' => "Webhook {$suffix} Tenant",
            'email' => "webhook-{$suffix}-tenant@stayhub.local",
            'phone' => '091' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'password' => bcrypt('password'),
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '999' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
            'date_of_birth' => '2000-01-01',
            'building_id' => $contract->room?->building_id,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $tenant->id,
            'join_date' => $contract->start_date?->toDateString() ?? '2026-06-12',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        return $tenant;
    }

    public function test_sepay_webhook_bypasses_test_delivery(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        $payload = [
            'id' => 0,
            'gateway' => 'SePay',
            'transactionDate' => '2026-06-12 08:31:48',
            'accountNumber' => '0000000000',
            'transferType' => 'in',
            'transferAmount' => 10000,
            'code' => 'SEPAYTEST',
            'content' => 'SEPAY TEST WEBHOOK',
        ];

        $response = $this->postJson('/api/v1/sepay-webhook', $payload, [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Nhận webhook thử nghiệm thành công.'
            ]);
    }

}
