<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceDebtRollover;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    private Admin $managerAdmin;

    private Admin $unauthorizedAdmin;

    private Building $building;

    private RoomType $roomType;

    private Room $room;

    private Service $electricityService;

    private Service $waterService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin
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

        // Create Manager Admin
        $this->managerAdmin = Admin::create([
            'username' => 'manager_test',
            'full_name' => 'Manager Test',
            'email' => 'manager_test@stayhub.local',
            'phone' => '0901234568',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        // Create Unauthorized Admin
        $this->unauthorizedAdmin = Admin::create([
            'username' => 'unauth_test',
            'full_name' => 'Unauth Test',
            'email' => 'unauth_test@stayhub.local',
            'phone' => '0901234569',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        // Create Region
        $region = Region::create([
            'name' => 'Region Test',
            'code' => 'REG_TEST',
            'created_by' => $this->superAdmin->id,
        ]);

        // Create Building managed by managerAdmin
        $this->building = Building::create([
            'name' => 'Building A',
            'slug' => 'building-a',
            'address' => '123 Test St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->managerAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $this->roomType = RoomType::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->room = Room::create([
            'room_number' => '101',
            'room_type_id' => $this->roomType->id,
            'building_id' => $this->building->id,
            'floor' => 1,
            'status' => Room::STATUS_ACTIVE,
            'base_price' => 5000000.00,
            'max_occupants' => 4,
            'current_occupants' => 0,
        ]);

        // Create Services
        $this->electricityService = Service::create([
            'name' => 'Điện sinh hoạt',
            'slug' => 'electric',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'kWh',
            'is_required' => true,
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->waterService = Service::create([
            'name' => 'Nước sinh hoạt',
            'slug' => 'water',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'm³',
            'is_required' => true,
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_unauthenticated_cannot_access_price_history(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/utility-price-history');
        $response->assertStatus(401);
    }

    public function test_unauthorized_manager_cannot_access_building_price_history(): void
    {
        $response = $this->actingAs($this->unauthorizedAdmin, 'admin')
            ->getJson("/api/v1/admin/dashboard/utility-price-history?building_id={$this->building->id}");

        $response->assertStatus(403);
    }

    public function test_unauthorized_manager_cannot_access_building_overview(): void
    {
        $response = $this->actingAs($this->unauthorizedAdmin, 'admin')
            ->getJson("/api/v1/admin/dashboard/overview?building_id={$this->building->id}&year=2026&month_from=6&month_to=6");

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Bạn không có quyền quản lý tòa nhà này');
    }

    public function test_dashboard_includes_current_debt_and_rolled_debt_in_revenue_without_double_counting(): void
    {
        $contract = Contract::create([
            'contract_code' => 'HD-DASH-DEBT',
            'room_id' => $this->room->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-12-01',
            'room_price' => 5000000.00,
            'deposit_amount' => 5000000.00,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $mayInvoice = Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $contract->id,
            'invoice_code' => 'DASH-DEBT-05',
            'billing_month' => 5,
            'billing_year' => 2026,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'previous_debt_amount' => 0.00,
            'total_amount' => 1000000.00,
            'paid_amount' => 500000.00,
            'remaining_amount' => 500000.00,
            'status' => Invoice::STATUS_OVERDUE,
            'due_date' => '2026-05-10',
        ]);

        $juneInvoice = Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $contract->id,
            'invoice_code' => 'DASH-DEBT-06',
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => 1000000.00,
            'total_amount' => 3000000.00,
            'paid_amount' => 500000.00,
            'remaining_amount' => 2500000.00,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'due_date' => '2026-06-10',
        ]);

        InvoiceItem::create([
            'invoice_id' => $juneInvoice->id,
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => 'Tiền phòng tháng 6',
            'quantity' => 1,
            'unit_price' => 2000000.00,
            'amount' => 2000000.00,
        ]);

        InvoiceItem::create([
            'invoice_id' => $juneInvoice->id,
            'item_type' => InvoiceItem::ITEM_TYPE_OLD_DEBT,
            'description' => 'Nợ cũ tháng 5',
            'quantity' => 1,
            'unit_price' => 1000000.00,
            'amount' => 1000000.00,
        ]);

        InvoiceDebtRollover::create([
            'source_invoice_id' => $mayInvoice->id,
            'target_invoice_id' => $juneInvoice->id,
            'amount' => 1000000.00,
            'settled_amount' => 500000.00,
            'status' => InvoiceDebtRollover::STATUS_ACTIVE,
        ]);

        Payment::create([
            'payment_code' => 'PAY-DASH-DEBT-001',
            'invoice_id' => $juneInvoice->id,
            'amount' => 500000.00,
            'payment_date' => '2026-06-15 09:00:00',
            'status' => Payment::STATUS_CONFIRMED,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/dashboard/overview?year=2026&month_from=6&month_to=6');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.revenue_chart.0.month', '06/2026');

        $this->assertEquals(3000000.00, (float) $response->json('result.kpis.monthly_revenue.value'));
        $this->assertEquals(3000000.00, (float) $response->json('result.kpis.monthly_profit.value'));
        $this->assertEquals(2500000.00, (float) $response->json('result.kpis.outstanding_debt.value'));
        $this->assertEquals(3000000.00, (float) $response->json('result.revenue_chart.0.revenue'));
        $this->assertEquals(500000.00, (float) $response->json('result.revenue_chart.0.collected_revenue'));
        $this->assertEquals(2500000.00, (float) $response->json('result.revenue_chart.0.debt'));
        $this->assertEquals(2000000.00, (float) $response->json('result.revenue_chart.0.current_debt'));
        $this->assertEquals(500000.00, (float) $response->json('result.revenue_chart.0.rolled_debt'));
        $this->assertEquals(3000000.00, (float) $response->json('result.revenue_chart.0.profit'));
    }

    public function test_building_manager_dashboard_only_contains_managed_building_money_flow(): void
    {
        $otherManager = Admin::create([
            'username' => 'other_manager_dashboard',
            'full_name' => 'Other Manager Dashboard',
            'email' => 'other_dashboard@stayhub.local',
            'phone' => '0901234598',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        $otherBuilding = Building::create([
            'name' => 'Building B',
            'slug' => 'building-b',
            'address' => '456 Test St',
            'region_id' => $this->building->region_id,
            'manager_admin_id' => $otherManager->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $otherRoom = Room::create([
            'room_number' => '202',
            'room_type_id' => $this->roomType->id,
            'building_id' => $otherBuilding->id,
            'floor' => 2,
            'status' => Room::STATUS_ACTIVE,
            'base_price' => 4000000.00,
            'max_occupants' => 4,
            'current_occupants' => 0,
        ]);

        $managedContract = Contract::create([
            'contract_code' => 'HD-DASH-MANAGED',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => 5000000.00,
            'deposit_amount' => 5000000.00,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $otherContract = Contract::create([
            'contract_code' => 'HD-DASH-OTHER',
            'room_id' => $otherRoom->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => 4000000.00,
            'deposit_amount' => 4000000.00,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $managedInvoice = Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $managedContract->id,
            'invoice_code' => 'DASH-MANAGED-06',
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => 0.00,
            'total_amount' => 1200000.00,
            'paid_amount' => 200000.00,
            'remaining_amount' => 1000000.00,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'due_date' => '2026-06-10',
        ]);

        $otherInvoice = Invoice::create([
            'room_id' => $otherRoom->id,
            'contract_id' => $otherContract->id,
            'invoice_code' => 'DASH-OTHER-06',
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => 0.00,
            'total_amount' => 7000000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 7000000.00,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-06-10',
        ]);

        Payment::create([
            'payment_code' => 'PAY-DASH-MANAGED',
            'invoice_id' => $managedInvoice->id,
            'amount' => 200000.00,
            'payment_date' => '2026-06-15 09:00:00',
            'status' => Payment::STATUS_CONFIRMED,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
        ]);

        Payment::create([
            'payment_code' => 'PAY-DASH-OTHER',
            'invoice_id' => $otherInvoice->id,
            'amount' => 500000.00,
            'payment_date' => '2026-06-16 09:00:00',
            'status' => Payment::STATUS_CONFIRMED,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
        ]);

        $response = $this->actingAs($this->managerAdmin, 'admin')
            ->getJson('/api/v1/admin/dashboard/overview?year=2026&month_from=6&month_to=6');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.meta.scope', 'building')
            ->assertJsonPath('result.meta.selected_building_id', $this->building->id);

        $this->assertEquals(1200000.00, (float) $response->json('result.kpis.monthly_revenue.value'));
        $this->assertEquals(200000.00, (float) $response->json('result.revenue_chart.0.collected_revenue'));
        $this->assertEquals(1000000.00, (float) $response->json('result.revenue_chart.0.debt'));
        $this->assertEquals(1000000.00, (float) $response->json('result.kpis.outstanding_debt.value'));
        $this->assertCount(1, $response->json('result.filters.buildings'));
        $this->assertEquals($this->building->id, $response->json('result.filters.buildings.0.id'));
    }

    public function test_dashboard_recent_debt_activity_excludes_fully_rolled_source_invoice(): void
    {
        Carbon::setTestNow('2026-06-20 10:00:00');

        $contract = Contract::create([
            'contract_code' => 'HD-DASH-ACTIVITY-DEBT',
            'room_id' => $this->room->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-12-01',
            'room_price' => 5000000.00,
            'deposit_amount' => 5000000.00,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $mayInvoice = Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $contract->id,
            'invoice_code' => 'DASH-ACTIVITY-05',
            'billing_month' => 5,
            'billing_year' => 2026,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'previous_debt_amount' => 0.00,
            'total_amount' => 1000000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 1000000.00,
            'status' => Invoice::STATUS_OVERDUE,
            'due_date' => '2026-05-10',
        ]);

        $juneInvoice = Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $contract->id,
            'invoice_code' => 'DASH-ACTIVITY-06',
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => 1000000.00,
            'total_amount' => 3000000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 3000000.00,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-06-10',
        ]);

        InvoiceDebtRollover::create([
            'source_invoice_id' => $mayInvoice->id,
            'target_invoice_id' => $juneInvoice->id,
            'amount' => 1000000.00,
            'settled_amount' => 0.00,
            'status' => InvoiceDebtRollover::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/dashboard/overview?year=2026&month_from=6&month_to=6');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $invoiceActivities = collect($response->json('result.recent_activities'))
            ->where('type', 'invoice')
            ->values();

        $this->assertCount(1, $invoiceActivities);
        $this->assertStringContainsString('DASH-ACTIVITY-06', $invoiceActivities->first()['title']);
        $this->assertEquals(3000000.00, (float) $response->json('result.kpis.outstanding_debt.value'));

        Carbon::setTestNow();
    }

    public function test_dashboard_recent_debt_activity_still_shows_collectible_invoice_after_many_rolled_sources(): void
    {
        Carbon::setTestNow('2026-07-20 10:00:00');

        $contract = Contract::create([
            'contract_code' => 'HD-DASH-ACTIVITY-LIMIT',
            'room_id' => $this->room->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-12-01',
            'room_price' => 5000000.00,
            'deposit_amount' => 5000000.00,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        for ($index = 1; $index <= 6; $index++) {
            $sourceInvoice = Invoice::create([
                'room_id' => $this->room->id,
                'contract_id' => $contract->id,
                'invoice_code' => sprintf('DASH-ROLLED-SRC-%02d', $index),
                'billing_month' => 5,
                'billing_year' => 2026,
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
                'previous_debt_amount' => 0.00,
                'total_amount' => 1000000.00,
                'paid_amount' => 0.00,
                'remaining_amount' => 1000000.00,
                'status' => Invoice::STATUS_OVERDUE,
                'due_date' => Carbon::create(2026, 7, 10 + $index)->toDateString(),
            ]);

            $targetInvoice = Invoice::create([
                'room_id' => $this->room->id,
                'contract_id' => $contract->id,
                'invoice_code' => sprintf('DASH-ROLLED-TGT-%02d', $index),
                'billing_month' => 7,
                'billing_year' => 2026,
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'previous_debt_amount' => 1000000.00,
                'total_amount' => 1000000.00,
                'paid_amount' => 0.00,
                'remaining_amount' => 1000000.00,
                'status' => Invoice::STATUS_UNPAID,
                'due_date' => '2026-08-10',
            ]);

            InvoiceDebtRollover::create([
                'source_invoice_id' => $sourceInvoice->id,
                'target_invoice_id' => $targetInvoice->id,
                'amount' => 1000000.00,
                'settled_amount' => 0.00,
                'status' => InvoiceDebtRollover::STATUS_ACTIVE,
            ]);
        }

        Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $contract->id,
            'invoice_code' => 'DASH-COLLECTIBLE-AFTER-ROLLED',
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => 0.00,
            'total_amount' => 2000000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 2000000.00,
            'status' => Invoice::STATUS_OVERDUE,
            'due_date' => '2026-06-01',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/dashboard/overview?year=2026&month_from=7&month_to=7');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $invoiceActivities = collect($response->json('result.recent_activities'))
            ->where('type', 'invoice')
            ->values();

        $this->assertTrue(
            $invoiceActivities->contains(fn (array $activity): bool => str_contains($activity['title'], 'DASH-COLLECTIBLE-AFTER-ROLLED'))
        );

        Carbon::setTestNow();
    }

    public function test_superadmin_can_access_price_history_without_building_id(): void
    {
        // Seed some initial service prices
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => 3500.00,
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => 15000.00,
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/dashboard/utility-price-history');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(6, 'result');

        $result = $response->json('result');
        $this->assertEquals(3500.00, $result[5]['electric_price']);
        $this->assertEquals(15000.00, $result[5]['water_price']);
    }

    public function test_price_history_returns_changing_prices(): void
    {
        // Seed price active from Jan 1
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => 3500.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-05-31',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // New price from June 1
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => 4500.00,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Seed water price active from Jan 1
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => 15000.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-05-31',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // New water price from June 1
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => 20000.00,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Freeze time to June 2026 to ensure consistent test months count
        Carbon::setTestNow('2026-06-15');

        $response = $this->actingAs($this->managerAdmin, 'admin')
            ->getJson("/api/v1/admin/dashboard/utility-price-history?building_id={$this->building->id}&months=6");

        $response->assertStatus(200);
        $result = $response->json('result');

        // result array will have months: 01/2026, 02/2026, 03/2026, 04/2026, 05/2026, 06/2026
        $this->assertEquals('01/2026', $result[0]['month']);
        $this->assertEquals(3500.00, $result[0]['electric_price']);
        $this->assertEquals(15000.00, $result[0]['water_price']);

        $this->assertEquals('05/2026', $result[4]['month']);
        $this->assertEquals(3500.00, $result[4]['electric_price']);
        $this->assertEquals(15000.00, $result[4]['water_price']);

        $this->assertEquals('06/2026', $result[5]['month']);
        $this->assertEquals(4500.00, $result[5]['electric_price']);
        $this->assertEquals(20000.00, $result[5]['water_price']);

        Carbon::setTestNow(); // Reset test time
    }
}
