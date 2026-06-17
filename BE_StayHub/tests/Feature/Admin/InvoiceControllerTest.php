<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Building $building;
    private Room $room;
    private Tenant $tenant;
    private Contract $contract;
    private Service $electricityService;
    private Service $waterService;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Super Admin
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

        // 2. Create Region
        $region = Region::create([
            'name' => 'Region Test',
            'code' => 'REG_TEST',
            'created_by' => $this->superAdmin->id,
        ]);

        // 3. Create Building
        $this->building = Building::create([
            'name' => 'Building A',
            'slug' => 'building-a',
            'address' => '123 Test St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        // 4. Create Room Type
        $roomType = RoomType::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // 5. Create Room
        $this->room = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'slug' => '101',
            'floor' => 1,
            'base_price' => '3000000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // 6. Create Tenant
        $this->tenant = Tenant::create([
            'username' => 'tenant_test',
            'full_name' => 'Tenant Test',
            'email' => 'tenant_test@stayhub.local',
            'phone' => '0911111111',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789012',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        // 7. Create Services
        $this->electricityService = Service::create([
            'name' => 'Điện',
            'slug' => 'electricity',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'kWh',
            'is_active' => true,
        ]);

        $this->waterService = Service::create([
            'name' => 'Nước',
            'slug' => 'water',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'm3',
            'is_active' => true,
        ]);

        // Create Service Prices
        ServicePrice::create([
            'service_id' => $this->electricityService->id,
            'building_id' => $this->building->id,
            'price' => '4000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        ServicePrice::create([
            'service_id' => $this->waterService->id,
            'building_id' => $this->building->id,
            'price' => '20000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);
    }

    public function test_admin_can_generate_draft_invoice_with_prorating_for_mid_month_checkin(): void
    {
        // February 2026 has 28 days. Contract starts on February 15th.
        // Actual days renting = 28 - 15 + 1 = 14 days.
        // Room Price = 3,000,000. Prorated room rent = 3,000,000 * 14 / 28 = 1,500,000.
        $this->contract = Contract::create([
            'contract_code' => 'HD-PRORATED',
            'room_id' => $this->room->id,
            'start_date' => '2026-02-15',
            'end_date' => '2026-08-15',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $this->contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-02-15',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        // Install meter devices and confirm meter readings for February 2026
        $elecDevice = MeterDevice::create([
            'room_id' => $this->room->id,
            'service_id' => $this->electricityService->id,
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => '100.00',
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        $waterDevice = MeterDevice::create([
            'room_id' => $this->room->id,
            'service_id' => $this->waterService->id,
            'meter_type' => MeterDevice::METER_TYPE_WATER,
            'initial_reading' => '10.00',
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        MeterReading::create([
            'meter_device_id' => $elecDevice->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'previous_reading' => '100',
            'current_reading' => '150',
            'consumption' => '50',
            'reading_date' => '2026-02-28',
            'status' => MeterReading::STATUS_CONFIRMED,
        ]);

        MeterReading::create([
            'meter_device_id' => $waterDevice->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'previous_reading' => '10',
            'current_reading' => '15',
            'consumption' => '5',
            'reading_date' => '2026-02-28',
            'status' => MeterReading::STATUS_CONFIRMED,
        ]);

        $payload = [
            'contract_id' => $this->contract->id,
            'billing_month' => 2,
            'billing_year' => 2026,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/admin/invoices/generate', $payload);

        $response->assertStatus(201);

        $invoiceId = $response->json('result.id');

        // Verify prorated room price = 1,500,000
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'amount' => '1500000.00',
        ]);

        // Verify electricity = 50 * 4000 = 200,000
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'item_type' => InvoiceItem::ITEM_TYPE_ELECTRIC,
            'amount' => '200000.00',
        ]);

        // Verify water = 5 * 20000 = 100,000
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'item_type' => InvoiceItem::ITEM_TYPE_WATER,
            'amount' => '100000.00',
        ]);

        // Total = 1,500,000 + 200,000 + 100,000 = 1,800,000
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'total_amount' => '1800000.00',
            'status' => Invoice::STATUS_DRAFT,
        ]);
    }

    public function test_admin_can_issue_draft_invoice_broadcasting_live(): void
    {
        Event::fake();

        $contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-07-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-202602-0001',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'previous_debt_amount' => '0.00',
            'total_amount' => '3000000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '3000000.00',
            'status' => Invoice::STATUS_DRAFT,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/admin/invoices/{$invoice->id}/issue");

        $response->assertStatus(200);

        $this->assertEquals(Invoice::STATUS_UNPAID, Invoice::find($invoice->id)->status);

        // Verify WebSocket event InvoiceIssued was dispatched
        Event::assertDispatched(\App\Events\InvoiceIssued::class, function ($event) use ($invoice) {
            return $event->invoice['id'] === $invoice->id;
        });

        // Verify notification is created for tenant
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'title' => 'Hóa đơn mới đã được phát hành',
        ]);
    }

    public function test_admin_can_record_payment_manually(): void
    {
        Event::fake();

        $contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-07-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-202602-0001',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'previous_debt_amount' => '0.00',
            'total_amount' => '3000000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '3000000.00',
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $payload = [
            'amount' => '3000000.00',
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
            'note' => 'Đóng tiền mặt trực tiếp',
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/admin/invoices/{$invoice->id}/payments", $payload);

        $response->assertStatus(201);

        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEquals('3000000.00', $invoice->paid_amount);
        $this->assertEquals('0.00', $invoice->remaining_amount);

        // Verify payment is recorded
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => '3000000.00',
            'status' => Payment::STATUS_CONFIRMED,
        ]);

        // Verify WebSocket event InvoicePaid was dispatched
        Event::assertDispatched(\App\Events\InvoicePaid::class);
    }

    public function test_tenant_can_view_own_invoices(): void
    {
        $contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-07-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-202602-0001',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'previous_debt_amount' => '0.00',
            'total_amount' => '3000000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '3000000.00',
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $this->superAdmin->id,
        ]);

        // Test GET index
        $response = $this->actingAs($this->tenant, 'tenant')
            ->getJson('/api/tenant/invoices');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'result.data');

        // Test GET show
        $detailResponse = $this->actingAs($this->tenant, 'tenant')
            ->getJson("/api/tenant/invoices/{$invoice->id}");

        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonPath('result.invoice_code', 'INV-202602-0001');
    }

    public function test_tenant_can_upload_payment_proof(): void
    {
        Storage::fake('public');

        $contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-07-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-202602-0001',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'previous_debt_amount' => '0.00',
            'total_amount' => '3000000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '3000000.00',
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $fakeImage = UploadedFile::fake()->image('receipt.jpg');

        $payload = [
            'amount' => '3000000.00',
            'transaction_reference' => 'TXN-TENANT-123',
            'note' => 'Biên lai chuyển khoản',
            'proof_image' => $fakeImage,
        ];

        $response = $this->actingAs($this->tenant, 'tenant')
            ->postJson("/api/tenant/invoices/{$invoice->id}/payment-proof", $payload);

        $response->assertStatus(201);

        // Assert payment is created in PENDING_CONFIRMATION status
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => '3000000.00',
            'transaction_reference' => 'TXN-TENANT-123',
            'status' => Payment::STATUS_PENDING_CONFIRMATION,
        ]);

        // Invoice status remains UNPAID until admin confirms
        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_UNPAID, $invoice->status);
    }

    public function test_sepay_webhook_updates_invoice_payment_realtime(): void
    {
        Config::set('services.sepay.webhook_token', 'sepay-test-token');
        Event::fake();

        $contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-07-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-202602-0001',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'previous_debt_amount' => '0.00',
            'total_amount' => '3000000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '3000000.00',
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $payload = [
            'id' => 12345,
            'gateway' => 'Vietcombank',
            'transactionDate' => '2026-02-28 10:00:00',
            'accountNumber' => '0011000123456',
            'amount' => 3000000,
            'transferType' => 'in',
            'content' => 'Thanh toan hoa don INV-202602-0001',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/sepay-webhook', $payload, [
            'Authorization' => 'Apikey sepay-test-token',
        ]);

        $response->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEquals('3000000.00', $invoice->paid_amount);
        $this->assertEquals('0.00', $invoice->remaining_amount);

        // Verify confirmed payment transaction is logged
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => '3000000.00',
            'status' => Payment::STATUS_CONFIRMED,
            'transaction_reference' => 'FT12345678',
        ]);

        // Verify WebSocket event InvoicePaid was dispatched
        Event::assertDispatched(\App\Events\InvoicePaid::class);
    }
}
