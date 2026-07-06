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
use App\Models\RoomMovement;
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

    public function test_admin_can_preview_invoice_without_persisting_or_side_effects(): void
    {
        Event::fake();

        $contract = Contract::create([
            'contract_code' => 'HD-PREVIEW',
            'room_id' => $this->room->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-02-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $electricDevice = MeterDevice::create([
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

        $electricReading = MeterReading::create([
            'meter_device_id' => $electricDevice->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'previous_reading' => '100',
            'current_reading' => '150',
            'consumption' => '50',
            'reading_date' => '2026-02-28',
            'status' => MeterReading::STATUS_CONFIRMED,
        ]);

        $waterReading = MeterReading::create([
            'meter_device_id' => $waterDevice->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'previous_reading' => '10',
            'current_reading' => '15',
            'consumption' => '5',
            'reading_date' => '2026-02-28',
            'status' => MeterReading::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/invoices/preview', [
                'contract_id' => $contract->id,
                'billing_month' => 2,
                'billing_year' => 2026,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.is_preview', true)
            ->assertJsonPath('result.invoice_code', null)
            ->assertJsonPath('result.invoice_code_note', 'Mã hóa đơn sẽ được cấp khi phát hành')
            ->assertJsonPath('result.total_amount', '3300000.00')
            ->assertJsonPath('result.items_count', 3);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertEquals(MeterReading::STATUS_CONFIRMED, $electricReading->fresh()->status);
        $this->assertEquals(MeterReading::STATUS_CONFIRMED, $waterReading->fresh()->status);
        Event::assertNotDispatched(\App\Events\InvoiceIssued::class);
    }

    public function test_admin_invoice_preview_rejects_existing_invoice_period(): void
    {
        $contract = Contract::create([
            'contract_code' => 'HD-PREVIEW-DUP',
            'room_id' => $this->room->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        Invoice::create([
            'invoice_code' => 'INV-2026-02-0001',
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

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/invoices/preview', [
                'contract_id' => $contract->id,
                'billing_month' => 2,
                'billing_year' => 2026,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Hợp đồng này đã có hóa đơn trong kỳ đã chọn');

        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_admin_invoice_preview_rejects_unconfirmed_meter_reading(): void
    {
        $contract = Contract::create([
            'contract_code' => 'HD-PREVIEW-DRAFT-METER',
            'room_id' => $this->room->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $electricDevice = MeterDevice::create([
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
            'meter_device_id' => $electricDevice->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'previous_reading' => '100',
            'current_reading' => '150',
            'consumption' => '50',
            'reading_date' => '2026-02-28',
            'status' => MeterReading::STATUS_DRAFT,
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

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/invoices/preview', [
                'contract_id' => $contract->id,
                'billing_month' => 2,
                'billing_year' => 2026,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ số Điện tháng 2/2026 chưa được xác nhận.');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
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

        // Create garbage service (charge by person) and internet service (fixed charge)
        $garbageService = Service::create([
            'name' => 'Rác',
            'slug' => 'garbage',
            'charge_method' => Service::CHARGE_METHOD_BY_PERSON,
            'unit_name' => 'Người',
            'is_active' => true,
        ]);

        $wifiService = Service::create([
            'name' => 'Internet',
            'slug' => 'internet',
            'charge_method' => Service::CHARGE_METHOD_FIXED,
            'unit_name' => 'Phòng',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $garbageService->id,
            'building_id' => $this->building->id,
            'price' => '20000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        ServicePrice::create([
            'service_id' => $wifiService->id,
            'building_id' => $this->building->id,
            'price' => '50000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Register a vehicle for the contract
        $vehicle = Vehicle::create([
            'tenant_id' => $this->tenant->id,
            'vehicle_type' => 1,
            'license_plate' => '29-A1 123.45',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);

        ContractVehicle::create([
            'contract_id' => $this->contract->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-02-15',
            'monthly_fee' => '100000.00',
            'charge_policy' => 1,
            'is_active' => true,
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
            ->postJson('/api/v1/admin/invoices/generate', $payload);

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

        // Verify garbage (by person) = 20,000 * 14 / 28 = 10,000
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'item_type' => InvoiceItem::ITEM_TYPE_TRASH,
            'amount' => '10000.00',
        ]);

        // Verify internet (fixed) = 50,000 * 14 / 28 = 25,000
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'item_type' => InvoiceItem::ITEM_TYPE_INTERNET,
            'amount' => '25000.00',
        ]);

        // Verify parking = 100,000 * 14 / 28 = 50,000
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
            'amount' => '50000.00',
        ]);

        // Total = 1,500,000 + 200,000 + 100,000 + 10,000 + 25,000 + 50,000 = 1,885,000
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'total_amount' => '1885000.00',
            'status' => Invoice::STATUS_UNPAID,
        ]);
    }

    public function test_vehicle_fee_uses_contract_vehicle_billing_window_for_mid_month_transfer(): void
    {
        $this->electricityService->update(['is_active' => false]);
        $this->waterService->update(['is_active' => false]);

        $parkingService = Service::create([
            'name' => 'Gửi xe',
            'slug' => 'parking-transfer',
            'charge_method' => Service::CHARGE_METHOD_BY_VEHICLE,
            'unit_name' => 'Xe',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $parkingService->id,
            'building_id' => $this->building->id,
            'price' => '0.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $oldContract = Contract::create([
            'contract_code' => 'HD-OLD-VEHICLE-INVOICE',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'actual_end_date' => '2026-07-14',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_LIQUIDATED,
            'created_by' => $this->superAdmin->id,
        ]);

        $newContract = Contract::create([
            'contract_code' => 'HD-NEW-VEHICLE-INVOICE',
            'room_id' => $this->room->id,
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $vehicle = Vehicle::create([
            'tenant_id' => $this->tenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => '31-A1 888.88',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);

        ContractVehicle::create([
            'contract_id' => $oldContract->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'ended_at' => '2026-07-14',
            'billing_end_date' => '2026-07-14',
            'monthly_fee' => '310000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => false,
        ]);

        ContractVehicle::create([
            'contract_id' => $newContract->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-07-15',
            'billing_start_date' => '2026-07-15',
            'monthly_fee' => '310000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        $oldResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/generate', [
            'contract_id' => $oldContract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $newResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/generate', [
            'contract_id' => $newContract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $oldResponse->assertStatus(201);
        $newResponse->assertStatus(201);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $oldResponse->json('result.id'),
            'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
            'amount' => '140000.00',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $newResponse->json('result.id'),
            'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
            'amount' => '170000.00',
        ]);
    }

    public function test_invoice_preview_uses_pending_transfer_cutoff_for_source_contract_before_execution(): void
    {
        Carbon::setTestNow('2026-07-28 09:00:00');
        $this->electricityService->update(['is_active' => false]);
        $this->waterService->update(['is_active' => false]);

        $contract = Contract::create([
            'contract_code' => 'HD-PARTIAL-PENDING-TRANSFER',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $this->tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $movingTenant = Tenant::create([
            'username' => 'moving_tenant_test',
            'full_name' => 'Moving Tenant Test',
            'email' => 'moving_tenant_test@stayhub.local',
            'phone' => '0922222222',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789099',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $movingTenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $internetService = Service::create([
            'name' => 'Internet',
            'slug' => 'internet-pending-transfer',
            'charge_method' => Service::CHARGE_METHOD_FIXED,
            'unit_name' => 'Phòng',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $internetService->id,
            'building_id' => $this->building->id,
            'price' => '50000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $parkingService = Service::create([
            'name' => 'Gửi xe',
            'slug' => 'parking-pending-transfer',
            'charge_method' => Service::CHARGE_METHOD_BY_VEHICLE,
            'unit_name' => 'Xe',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $parkingService->id,
            'building_id' => $this->building->id,
            'price' => '0.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $toRoom = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->room->room_type_id,
            'room_number' => '202',
            'slug' => '202',
            'floor' => 2,
            'base_price' => '3000000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $remainingVehicle = Vehicle::create([
            'tenant_id' => $this->tenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => '29-A1 111.11',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);

        ContractVehicle::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $remainingVehicle->id,
            'started_at' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'tenant_id' => $movingTenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => '29-A1 999.99',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);

        ContractVehicle::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        RoomMovement::create([
            'transfer_code' => 'TRF-2026-07-PENDING',
            'tenant_id' => $movingTenant->id,
            'contract_id' => $contract->id,
            'source_contract_id' => $contract->id,
            'from_room_id' => $this->room->id,
            'to_room_id' => $toRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_PENDING,
            'movement_date' => '2026-07-29 00:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '0.00',
            'manual_refund_amount' => '0.00',
            'deposit_due_amount' => '0.00',
            'extra_charge_amount' => '0.00',
            'settlement_due_amount' => '0.00',
            'settlement_paid_amount' => '0.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
            'settlement_payment_references' => [],
            'scheduled_payload' => [
                'tenant_ids' => [$movingTenant->id],
                'to_room_id' => $toRoom->id,
                'movement_date' => '2026-07-29',
            ],
            'created_by' => $this->superAdmin->id,
        ]);

        $previewResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/preview', [
            'contract_id' => $contract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $previewResponse->assertStatus(200)
            ->assertJsonPath('result.transfer_cutoffs.0.transfer_code', 'TRF-2026-07-PENDING')
            ->assertJsonPath('result.transfer_cutoffs.0.cutoff_date', '2026-07-28')
            ->assertJsonPath('result.transfer_cutoffs.0.moving_all_active_tenants', false)
            ->assertJsonPath('result.transfer_cutoffs.0.closes_source_contract', true)
            ->assertJsonPath('result.period_end', '2026-07-28');

        $previewItems = collect($previewResponse->json('result.items'));
        $roomItem = $previewItems->firstWhere('item_type', InvoiceItem::ITEM_TYPE_ROOM);
        $internetItem = $previewItems->firstWhere('service_id', $internetService->id);
        $parkingItems = $previewItems->where('item_type', InvoiceItem::ITEM_TYPE_PARKING)->values();
        $parkingAmounts = $parkingItems->pluck('amount')->sort()->values()->all();

        $this->assertSame('2709677.42', $roomItem['amount']);
        $this->assertSame('45161.29', $internetItem['amount']);
        $this->assertStringContainsString('tính đến 28/07/2026', $roomItem['description']);
        $this->assertStringContainsString('tính đến 28/07/2026', $internetItem['description']);

        $this->assertCount(2, $parkingItems);
        $this->assertSame(['90322.58', '90322.58'], $parkingAmounts);
        $this->assertTrue($parkingItems->every(fn (array $item): bool => str_contains($item['description'], 'tính đến 28/07/2026')));

        $generateResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/generate', [
            'contract_id' => $contract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $generateResponse->assertStatus(201);
        $this->assertSame('2026-07-28', Invoice::query()->findOrFail($generateResponse->json('result.id'))->period_end?->toDateString());

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $generateResponse->json('result.id'),
            'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
            'amount' => '90322.58',
        ]);


        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $generateResponse->json('result.id'),
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'amount' => '2709677.42',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $generateResponse->json('result.id'),
            'service_id' => $internetService->id,
            'amount' => '45161.29',
        ]);

        Carbon::setTestNow();
    }

    public function test_transfer_final_invoice_replaces_unpaid_full_month_invoice_created_before_transfer_schedule(): void
    {
        Carbon::setTestNow('2026-07-28 09:00:00');
        $this->electricityService->update(['is_active' => false]);
        $this->waterService->update(['is_active' => false]);

        $contract = Contract::create([
            'contract_code' => 'HD-TRANSFER-REPLACE-FULL-INVOICE',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $this->tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3100000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $fullMonthInvoice = Invoice::create([
            'invoice_code' => 'INV-FULL-BEFORE-TRANSFER',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'previous_debt_amount' => '0.00',
            'total_amount' => '3100000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '3100000.00',
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $fullMonthInvoice->items()->create([
            'service_id' => null,
            'meter_reading_id' => null,
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => 'Tiền phòng tháng 07/2026',
            'quantity' => '1.00',
            'unit_price' => '3100000.00',
            'amount' => '3100000.00',
        ]);

        $toRoom = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->room->room_type_id,
            'room_number' => '209',
            'slug' => '209',
            'floor' => 2,
            'base_price' => '3000000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        RoomMovement::create([
            'transfer_code' => 'TRF-REPLACE-FULL-2026-07',
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'source_contract_id' => $contract->id,
            'from_room_id' => $this->room->id,
            'to_room_id' => $toRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_PENDING,
            'movement_date' => '2026-07-29 00:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '0.00',
            'manual_refund_amount' => '0.00',
            'deposit_due_amount' => '0.00',
            'extra_charge_amount' => '0.00',
            'settlement_due_amount' => '0.00',
            'settlement_paid_amount' => '0.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
            'settlement_payment_references' => [],
            'scheduled_payload' => [
                'tenant_ids' => [$this->tenant->id],
                'to_room_id' => $toRoom->id,
                'movement_date' => '2026-07-29',
            ],
            'created_by' => $this->superAdmin->id,
        ]);

        $generateResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/generate', [
            'contract_id' => $contract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $generateResponse->assertStatus(201)
            ->assertJsonPath('result.period_end', '2026-07-28');

        $fullMonthInvoice->refresh();
        $this->assertSame(Invoice::STATUS_CANCELLED, (int) $fullMonthInvoice->status);
        $this->assertSame('0.00', (string) $fullMonthInvoice->remaining_amount);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $generateResponse->json('result.id'),
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'amount' => '2800000.00',
        ]);

        Carbon::setTestNow();
    }

    public function test_invoice_preview_uses_pending_transfer_date_to_add_new_room_vehicle_fee_before_execution(): void
    {
        Carbon::setTestNow('2026-07-28 09:00:00');
        $this->electricityService->update(['is_active' => false]);
        $this->waterService->update(['is_active' => false]);

        $parkingService = Service::create([
            'name' => 'Gửi xe',
            'slug' => 'parking-incoming-pending-transfer',
            'charge_method' => Service::CHARGE_METHOD_BY_VEHICLE,
            'unit_name' => 'Xe',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $parkingService->id,
            'building_id' => $this->building->id,
            'price' => '0.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $sourceRoom = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->room->room_type_id,
            'room_number' => '303',
            'slug' => '303',
            'floor' => 3,
            'base_price' => '3000000.00',
            'max_occupants' => 5,
            'current_occupants' => 1,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $destinationTenant = Tenant::create([
            'username' => 'destination_tenant_test',
            'full_name' => 'Destination Tenant Test',
            'email' => 'destination_tenant_test@stayhub.local',
            'phone' => '0933333333',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789088',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        $sourceContract = Contract::create([
            'contract_code' => 'HD-SOURCE-PENDING-INCOMING',
            'room_id' => $sourceRoom->id,
            'representative_tenant_id' => $destinationTenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $destinationContract = Contract::create([
            'contract_code' => 'HD-DEST-PENDING-INCOMING',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $this->tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $sourceContract->id,
            'tenant_id' => $destinationTenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $destinationContract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $vehicle = Vehicle::create([
            'tenant_id' => $destinationTenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => '30-A1 777.77',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);

        ContractVehicle::create([
            'contract_id' => $sourceContract->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        RoomMovement::create([
            'transfer_code' => 'TRF-2026-07-INCOMING',
            'tenant_id' => $destinationTenant->id,
            'contract_id' => $sourceContract->id,
            'source_contract_id' => $sourceContract->id,
            'from_room_id' => $sourceRoom->id,
            'to_room_id' => $this->room->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_PENDING,
            'movement_date' => '2026-07-29 00:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '0.00',
            'manual_refund_amount' => '0.00',
            'deposit_due_amount' => '0.00',
            'extra_charge_amount' => '0.00',
            'settlement_due_amount' => '0.00',
            'settlement_paid_amount' => '0.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
            'settlement_payment_references' => [],
            'scheduled_payload' => [
                'tenant_ids' => [$destinationTenant->id],
                'to_room_id' => $this->room->id,
                'movement_date' => '2026-07-29',
            ],
            'created_by' => $this->superAdmin->id,
        ]);

        $previewResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/preview', [
            'contract_id' => $destinationContract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $previewResponse->assertStatus(200)
            ->assertJsonPath('result.transfer_cutoffs.0.transfer_code', 'TRF-2026-07-INCOMING')
            ->assertJsonPath('result.transfer_cutoffs.0.direction', 'incoming')
            ->assertJsonPath('result.transfer_cutoffs.0.vehicle_start_date', '2026-07-29');

        $items = collect($previewResponse->json('result.items'));
        $roomItem = $items->firstWhere('item_type', InvoiceItem::ITEM_TYPE_ROOM);
        $parkingItem = $items->firstWhere('item_type', InvoiceItem::ITEM_TYPE_PARKING);

        $this->assertSame('3000000.00', $roomItem['amount']);
        $this->assertSame('9677.42', $parkingItem['amount']);
        $this->assertStringContainsString('tính từ 29/07/2026', $parkingItem['description']);

        $generateResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/generate', [
            'contract_id' => $destinationContract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $generateResponse->assertStatus(201);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $generateResponse->json('result.id'),
            'item_type' => InvoiceItem::ITEM_TYPE_PARKING,
            'amount' => '9677.42',
        ]);

        Carbon::setTestNow();
    }

    public function test_invoice_preview_for_remaining_contract_after_non_representative_transfer_keeps_room_services_and_old_vehicle_days(): void
    {
        $this->electricityService->update(['is_active' => false]);
        $this->waterService->update(['is_active' => false]);

        $remainingTenant = $this->tenant;
        $movingRepresentative = Tenant::create([
            'username' => 'representative_transfer_test',
            'full_name' => 'Representative Transfer Test',
            'email' => 'representative_transfer_test@stayhub.local',
            'phone' => '0933333333',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789222',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        $sourceContract = Contract::create([
            'contract_code' => 'HD-REP-SOURCE-TRANSFER',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $remainingTenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'actual_end_date' => '2026-07-14',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_LIQUIDATED,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $sourceContract->id,
            'tenant_id' => $remainingTenant->id,
            'join_date' => '2026-01-01',
            'leave_date' => '2026-07-14',
            'billing_start_date' => '2026-01-01',
            'billing_end_date' => '2026-07-14',
            'is_staying' => false,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $sourceContract->id,
            'tenant_id' => $movingRepresentative->id,
            'join_date' => '2026-01-01',
            'leave_date' => '2026-07-14',
            'billing_start_date' => '2026-01-01',
            'billing_end_date' => '2026-07-14',
            'is_staying' => false,
            'created_by' => $this->superAdmin->id,
        ]);

        $remainingContract = Contract::create([
            'contract_code' => 'HD-REP-REMAINING-TRANSFER',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $remainingTenant->id,
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'note' => 'Hợp đồng mới cho người còn ở lại sau khi đại diện chuyển phòng.',
            'created_by' => $this->superAdmin->id,
            'parent_contract_id' => $sourceContract->id,
        ]);

        ContractTenant::create([
            'contract_id' => $remainingContract->id,
            'tenant_id' => $remainingTenant->id,
            'join_date' => '2026-07-15',
            'billing_start_date' => '2026-07-15',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $destinationRoom = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->room->room_type_id,
            'room_number' => '303',
            'slug' => '303',
            'floor' => 3,
            'base_price' => '3000000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $destinationContract = Contract::create([
            'contract_code' => 'HD-REP-DEST-TRANSFER',
            'room_id' => $destinationRoom->id,
            'representative_tenant_id' => $movingRepresentative->id,
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $internetService = Service::create([
            'name' => 'Internet phòng',
            'slug' => 'internet-representative-transfer',
            'charge_method' => Service::CHARGE_METHOD_FIXED,
            'unit_name' => 'Phòng',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $internetService->id,
            'building_id' => $this->building->id,
            'price' => '60000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $parkingService = Service::create([
            'name' => 'Gửi xe',
            'slug' => 'parking-representative-transfer',
            'charge_method' => Service::CHARGE_METHOD_BY_VEHICLE,
            'unit_name' => 'Xe',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $parkingService->id,
            'building_id' => $this->building->id,
            'price' => '0.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $remainingVehicle = Vehicle::create([
            'tenant_id' => $remainingTenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => 'REP-A-001',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);

        $movingVehicle = Vehicle::create([
            'tenant_id' => $movingRepresentative->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => 'REP-B-001',
            'brand' => 'Yamaha',
            'color' => 'Blue',
            'is_active' => true,
        ]);

        ContractVehicle::create([
            'contract_id' => $sourceContract->id,
            'vehicle_id' => $remainingVehicle->id,
            'started_at' => '2026-01-01',
            'ended_at' => '2026-07-14',
            'billing_start_date' => '2026-01-01',
            'billing_end_date' => '2026-07-14',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => false,
        ]);

        ContractVehicle::create([
            'contract_id' => $sourceContract->id,
            'vehicle_id' => $movingVehicle->id,
            'started_at' => '2026-01-01',
            'ended_at' => '2026-07-14',
            'billing_start_date' => '2026-01-01',
            'billing_end_date' => '2026-07-14',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => false,
        ]);

        ContractVehicle::create([
            'contract_id' => $remainingContract->id,
            'vehicle_id' => $remainingVehicle->id,
            'started_at' => '2026-07-15',
            'billing_start_date' => '2026-07-15',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        RoomMovement::create([
            'transfer_code' => 'TRF-REP-2026-07',
            'tenant_id' => $movingRepresentative->id,
            'contract_id' => $destinationContract->id,
            'source_contract_id' => $sourceContract->id,
            'destination_contract_id' => $destinationContract->id,
            'from_room_id' => $this->room->id,
            'to_room_id' => $destinationRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_EXECUTED,
            'movement_date' => '2026-07-15 00:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '0.00',
            'manual_refund_amount' => '0.00',
            'deposit_due_amount' => '0.00',
            'extra_charge_amount' => '0.00',
            'settlement_due_amount' => '0.00',
            'settlement_paid_amount' => '0.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
            'settlement_payment_references' => [],
            'scheduled_payload' => [
                'tenant_ids' => [$movingRepresentative->id],
                'to_room_id' => $destinationRoom->id,
                'movement_date' => '2026-07-15',
            ],
            'executed_at' => '2026-07-15 01:00:00',
            'created_by' => $this->superAdmin->id,
        ]);

        $previewResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/preview', [
            'contract_id' => $remainingContract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $previewResponse->assertStatus(200)
            ->assertJsonPath('result.total_amount', '3205161.29');

        $items = collect($previewResponse->json('result.items'));
        $roomItem = $items->firstWhere('item_type', InvoiceItem::ITEM_TYPE_ROOM);
        $internetItem = $items->firstWhere('service_id', $internetService->id);
        $parkingItems = $items->where('item_type', InvoiceItem::ITEM_TYPE_PARKING)->values();
        $remainingVehicleItems = $parkingItems->filter(fn (array $item): bool => str_contains($item['description'], 'REP-A-001'))->values();
        $movingVehicleItem = $parkingItems->first(fn (array $item): bool => str_contains($item['description'], 'REP-B-001'));

        $this->assertSame('3000000.00', $roomItem['amount']);
        $this->assertSame('60000.00', $internetItem['amount']);
        $this->assertCount(3, $parkingItems);
        $this->assertSame(['45161.29', '54838.71'], $remainingVehicleItems->pluck('amount')->sort()->values()->all());
        $this->assertSame('45161.29', $movingVehicleItem['amount']);
        $this->assertStringContainsString('tính đến 14/07/2026 trước khi chuyển phòng', $movingVehicleItem['description']);
    }

    public function test_invoice_preview_for_remaining_contract_does_not_duplicate_source_period_when_source_invoice_exists(): void
    {
        $this->electricityService->update(['is_active' => false]);
        $this->waterService->update(['is_active' => false]);

        $remainingTenant = $this->tenant;
        $movingRepresentative = Tenant::create([
            'username' => 'representative_transfer_existing_invoice',
            'full_name' => 'Representative Existing Invoice',
            'email' => 'representative_transfer_existing_invoice@stayhub.local',
            'phone' => '0944444444',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789333',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        $sourceContract = Contract::create([
            'contract_code' => 'HD-REP-SOURCE-INVOICED',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $movingRepresentative->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'actual_end_date' => '2026-07-14',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_LIQUIDATED,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $sourceContract->id,
            'tenant_id' => $remainingTenant->id,
            'join_date' => '2026-01-01',
            'leave_date' => '2026-07-14',
            'billing_start_date' => '2026-01-01',
            'billing_end_date' => '2026-07-14',
            'is_staying' => false,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $sourceContract->id,
            'tenant_id' => $movingRepresentative->id,
            'join_date' => '2026-01-01',
            'leave_date' => '2026-07-14',
            'billing_start_date' => '2026-01-01',
            'billing_end_date' => '2026-07-14',
            'is_staying' => false,
            'created_by' => $this->superAdmin->id,
        ]);

        $remainingContract = Contract::create([
            'contract_code' => 'HD-REP-REMAINING-INVOICED',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $remainingTenant->id,
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'note' => 'Hợp đồng mới cho người còn ở lại sau khi đại diện chuyển phòng.',
            'created_by' => $this->superAdmin->id,
            'parent_contract_id' => $sourceContract->id,
        ]);

        ContractTenant::create([
            'contract_id' => $remainingContract->id,
            'tenant_id' => $remainingTenant->id,
            'join_date' => '2026-07-15',
            'billing_start_date' => '2026-07-15',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $destinationRoom = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->room->room_type_id,
            'room_number' => '304',
            'slug' => '304',
            'floor' => 3,
            'base_price' => '3000000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $destinationContract = Contract::create([
            'contract_code' => 'HD-REP-DEST-INVOICED',
            'room_id' => $destinationRoom->id,
            'representative_tenant_id' => $movingRepresentative->id,
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $parkingService = Service::create([
            'name' => 'Gửi xe',
            'slug' => 'parking-representative-transfer-invoiced',
            'charge_method' => Service::CHARGE_METHOD_BY_VEHICLE,
            'unit_name' => 'Xe',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $parkingService->id,
            'building_id' => $this->building->id,
            'price' => '0.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $remainingVehicle = Vehicle::create([
            'tenant_id' => $remainingTenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => 'INV-A-001',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);

        $movingVehicle = Vehicle::create([
            'tenant_id' => $movingRepresentative->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => 'INV-B-001',
            'brand' => 'Yamaha',
            'color' => 'Blue',
            'is_active' => true,
        ]);

        ContractVehicle::create([
            'contract_id' => $sourceContract->id,
            'vehicle_id' => $remainingVehicle->id,
            'started_at' => '2026-01-01',
            'ended_at' => '2026-07-14',
            'billing_start_date' => '2026-01-01',
            'billing_end_date' => '2026-07-14',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => false,
        ]);

        ContractVehicle::create([
            'contract_id' => $sourceContract->id,
            'vehicle_id' => $movingVehicle->id,
            'started_at' => '2026-01-01',
            'ended_at' => '2026-07-14',
            'billing_start_date' => '2026-01-01',
            'billing_end_date' => '2026-07-14',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => false,
        ]);

        ContractVehicle::create([
            'contract_id' => $remainingContract->id,
            'vehicle_id' => $remainingVehicle->id,
            'started_at' => '2026-07-15',
            'billing_start_date' => '2026-07-15',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        RoomMovement::create([
            'transfer_code' => 'TRF-REP-INVOICED-2026-07',
            'tenant_id' => $movingRepresentative->id,
            'contract_id' => $destinationContract->id,
            'source_contract_id' => $sourceContract->id,
            'destination_contract_id' => $destinationContract->id,
            'from_room_id' => $this->room->id,
            'to_room_id' => $destinationRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_EXECUTED,
            'movement_date' => '2026-07-15 00:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '0.00',
            'manual_refund_amount' => '0.00',
            'deposit_due_amount' => '0.00',
            'extra_charge_amount' => '0.00',
            'settlement_due_amount' => '0.00',
            'settlement_paid_amount' => '0.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
            'settlement_payment_references' => [],
            'scheduled_payload' => [
                'tenant_ids' => [$movingRepresentative->id],
                'to_room_id' => $destinationRoom->id,
                'movement_date' => '2026-07-15',
            ],
            'executed_at' => '2026-07-15 01:00:00',
            'created_by' => $this->superAdmin->id,
        ]);

        Invoice::create([
            'invoice_code' => 'INV-SOURCE-2026-07',
            'contract_id' => $sourceContract->id,
            'room_id' => $this->room->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'previous_debt_amount' => '0.00',
            'total_amount' => '1451612.90',
            'paid_amount' => '0.00',
            'remaining_amount' => '1451612.90',
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $previewResponse = $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/invoices/preview', [
            'contract_id' => $remainingContract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $previewResponse->assertStatus(200);

        $parkingItems = collect($previewResponse->json('result.items'))
            ->where('item_type', InvoiceItem::ITEM_TYPE_PARKING)
            ->values();

        $this->assertCount(1, $parkingItems);
        $this->assertSame('54838.71', $parkingItems->first()['amount']);
        $this->assertStringContainsString('INV-A-001', $parkingItems->first()['description']);
        $this->assertFalse($parkingItems->contains(fn (array $item): bool => str_contains($item['description'], 'INV-B-001')));
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
            ->postJson("/api/v1/admin/invoices/{$invoice->id}/payments", $payload);

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
            ->getJson('/api/v1/tenant/invoices');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'result.data');

        // Test GET show
        $detailResponse = $this->actingAs($this->tenant, 'tenant')
            ->getJson("/api/v1/tenant/invoices/{$invoice->id}");

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
            ->postJson("/api/v1/tenant/invoices/{$invoice->id}/payment-proof", $payload);

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

        $response = $this->postJson('/api/v1/sepay-webhook', $payload, [
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

    public function test_admin_can_bulk_generate_invoices_successfully(): void
    {
        $contract = Contract::create([
            'contract_code' => 'HD-BULK-1',
            'room_id' => $this->room->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-02-01',
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
            'building_id' => $this->building->id,
            'billing_month' => 2,
            'billing_year' => 2026,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/buildings/{$this->building->id}/invoices/bulk-generate", $payload);

        $response->assertStatus(202);

        $job = new \App\Jobs\BulkGenerateInvoicesJob(
            $this->building->id,
            2,
            2026,
            $this->superAdmin->id
        );
        $job->handle();

        $this->assertDatabaseHas('invoices', [
            'contract_id' => $contract->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'room_id' => $this->room->id,
        ]);
    }

    public function test_admin_can_reissue_invoice_and_sync_meter_readings_items_debt_and_notifications(): void
    {
        Event::fake();

        $contract = Contract::create([
            'contract_code' => 'HD-REISSUE',
            'room_id' => $this->room->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-02-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $meter = MeterDevice::create([
            'room_id' => $this->room->id,
            'service_id' => $this->electricityService->id,
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => '190.00',
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        $februaryReading = MeterReading::create([
            'meter_device_id' => $meter->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'previous_reading' => '100.00',
            'current_reading' => '150.00',
            'consumption' => '50.00',
            'reading_date' => '2026-02-28',
            'status' => MeterReading::STATUS_INVOICED,
        ]);

        $marchReading = MeterReading::create([
            'meter_device_id' => $meter->id,
            'billing_month' => 3,
            'billing_year' => 2026,
            'previous_reading' => '150.00',
            'current_reading' => '190.00',
            'consumption' => '40.00',
            'reading_date' => '2026-03-31',
            'status' => MeterReading::STATUS_INVOICED,
        ]);

        $februaryInvoice = Invoice::create([
            'invoice_code' => 'INV-2026-02-0001',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'previous_debt_amount' => '0.00',
            'total_amount' => '3200000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '3200000.00',
            'due_date' => '2026-03-05',
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
            'created_by' => $this->superAdmin->id,
        ]);

        $februaryInvoice->items()->createMany([
            ['item_type' => InvoiceItem::ITEM_TYPE_ROOM, 'description' => 'Tiền phòng tháng 02/2026', 'quantity' => '1.00', 'unit_price' => '3000000.00', 'amount' => '3000000.00'],
            ['service_id' => $this->electricityService->id, 'meter_reading_id' => $februaryReading->id, 'item_type' => InvoiceItem::ITEM_TYPE_ELECTRIC, 'description' => 'Điện (100.00 → 150.00)', 'quantity' => '50.00', 'unit_price' => '4000.00', 'amount' => '200000.00'],
        ]);

        $marchInvoice = Invoice::create([
            'invoice_code' => 'INV-2026-03-0001',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 3,
            'billing_year' => 2026,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'previous_debt_amount' => '3200000.00',
            'total_amount' => '6360000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '6360000.00',
            'due_date' => '2026-04-05',
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
            'created_by' => $this->superAdmin->id,
        ]);

        $marchInvoice->items()->createMany([
            ['item_type' => InvoiceItem::ITEM_TYPE_ROOM, 'description' => 'Tiền phòng tháng 03/2026', 'quantity' => '1.00', 'unit_price' => '3000000.00', 'amount' => '3000000.00'],
            ['service_id' => $this->electricityService->id, 'meter_reading_id' => $marchReading->id, 'item_type' => InvoiceItem::ITEM_TYPE_ELECTRIC, 'description' => 'Điện (150.00 → 190.00)', 'quantity' => '40.00', 'unit_price' => '4000.00', 'amount' => '160000.00'],
            ['item_type' => InvoiceItem::ITEM_TYPE_OLD_DEBT, 'description' => 'Nợ cũ các kỳ trước', 'quantity' => '1.00', 'unit_price' => '3200000.00', 'amount' => '3200000.00'],
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/invoices/{$februaryInvoice->id}", [
                'reason' => 'Nhập sai chỉ số điện tháng 02',
                'due_date' => '2026-03-10',
                'meter_readings' => [[
                    'meter_reading_id' => $februaryReading->id,
                    'current_reading' => '170.00',
                    'reading_date' => '2026-02-28',
                ]],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('meter_readings', ['id' => $februaryReading->id, 'current_reading' => '170.00', 'consumption' => '70.00']);
        $this->assertDatabaseHas('meter_readings', ['id' => $marchReading->id, 'previous_reading' => '170.00', 'consumption' => '20.00']);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $februaryInvoice->id, 'meter_reading_id' => $februaryReading->id, 'quantity' => '70.00', 'amount' => '280000.00']);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $marchInvoice->id, 'item_type' => InvoiceItem::ITEM_TYPE_OLD_DEBT, 'amount' => '3280000.00']);
        $this->assertDatabaseHas('invoices', ['id' => $februaryInvoice->id, 'total_amount' => '3280000.00', 'remaining_amount' => '3280000.00', 'revision' => 2, 'reissue_reason' => 'Nhập sai chỉ số điện tháng 02']);
        $this->assertDatabaseHas('invoices', ['id' => $marchInvoice->id, 'revision' => 2, 'previous_debt_amount' => '3280000.00']);
        $this->assertDatabaseHas('notifications', ['tenant_id' => $this->tenant->id, 'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE, 'title' => 'Hóa đơn đã được cập nhật và phát hành lại']);
        Event::assertDispatched(\App\Events\InvoiceReissued::class, 2);
    }

    public function test_admin_cannot_reissue_invoice_when_decrease_adjustments_exceed_invoice_amount(): void
    {
        Event::fake();

        $contract = Contract::create([
            'contract_code' => 'HD-REISSUE-DISCOUNT-LIMIT',
            'room_id' => $this->room->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '1000000.00',
            'deposit_amount' => '1000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-02-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-REISSUE-DISCOUNT-LIMIT',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => 2,
            'billing_year' => 2026,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'previous_debt_amount' => '0.00',
            'total_amount' => '1000000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '1000000.00',
            'due_date' => '2026-03-05',
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice->items()->create([
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => 'Tiền phòng tháng 02/2026',
            'quantity' => '1.00',
            'unit_price' => '1000000.00',
            'amount' => '1000000.00',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/invoices/{$invoice->id}", [
                'reason' => 'Kiểm tra giới hạn giảm trừ',
                'adjustments' => [
                    [
                        'item_type' => InvoiceItem::ITEM_TYPE_SURCHARGE,
                        'description' => 'Phụ thu thêm',
                        'quantity' => '1',
                        'unit_price' => '500000',
                    ],
                    [
                        'item_type' => InvoiceItem::ITEM_TYPE_DISCOUNT,
                        'description' => 'Giảm trừ vượt số tiền hóa đơn',
                        'quantity' => '1',
                        'unit_price' => '1200000',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tổng giảm trừ không được vượt quá số tiền hóa đơn');

        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $invoice->id,
            'item_type' => InvoiceItem::ITEM_TYPE_DISCOUNT,
            'amount' => '-1200000.00',
        ]);
    }

    public function test_admin_cannot_preview_invoice_when_decrease_adjustments_exceed_invoice_amount(): void
    {
        $this->electricityService->update(['is_active' => false]);
        $this->waterService->update(['is_active' => false]);

        $contract = Contract::create([
            'contract_code' => 'HD-PREVIEW-DISCOUNT-LIMIT',
            'room_id' => $this->room->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '1000000.00',
            'deposit_amount' => '1000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-02-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/invoices/preview', [
                'contract_id' => $contract->id,
                'billing_month' => 2,
                'billing_year' => 2026,
                'adjustments' => [
                    [
                        'item_type' => InvoiceItem::ITEM_TYPE_SURCHARGE,
                        'description' => 'Phụ thu thêm',
                        'quantity' => '1',
                        'unit_price' => '500000',
                    ],
                    [
                        'item_type' => InvoiceItem::ITEM_TYPE_DISCOUNT,
                        'description' => 'Giảm trừ vượt số tiền hóa đơn',
                        'quantity' => '1',
                        'unit_price' => '1200000',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tổng giảm trừ không được vượt quá số tiền hóa đơn');
    }

    public function test_admin_can_reissue_overdue_invoice_with_future_due_date_to_unpaid(): void
    {
        Event::fake();

        $contract = Contract::create([
            'contract_code' => 'HD-REISSUE-DUE-DATE',
            'room_id' => $this->room->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'billing_cycle_day' => 5,
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => now()->subMonths(2)->toDateString(),
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-REISSUE-DUE-0001',
            'contract_id' => $contract->id,
            'room_id' => $this->room->id,
            'billing_month' => (int) now()->subMonth()->format('m'),
            'billing_year' => (int) now()->subMonth()->format('Y'),
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'previous_debt_amount' => '0.00',
            'total_amount' => '3000000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '3000000.00',
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => Invoice::STATUS_OVERDUE,
            'issued_at' => now()->subMonth(),
            'created_by' => $this->superAdmin->id,
        ]);

        $invoice->items()->create([
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => 'Tiền phòng',
            'quantity' => '1.00',
            'unit_price' => '3000000.00',
            'amount' => '3000000.00',
        ]);

        $newDueDate = now()->addDays(7)->toDateString();

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/invoices/{$invoice->id}", [
                'reason' => 'Gia hạn hạn thanh toán do phát hành lại hóa đơn',
                'due_date' => $newDueDate,
            ]);

        $response->assertOk();

        $invoice->refresh();

        $this->assertSame($newDueDate, $invoice->due_date->toDateString());
        $this->assertSame(Invoice::STATUS_UNPAID, (int) $invoice->status);
        $this->assertSame(2, (int) $invoice->revision);
    }
}
