<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceDebtRollover;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    private Admin $managerAdmin;

    private Building $building;

    private Room $room;

    private RoomType $roomType;

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

        // Create Room
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
    }

    public function test_super_admin_can_retrieve_financial_report()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => 5000000.00,
            'deposit_amount' => 5000000.00,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // 1. Create Invoice & Items
        $invoice = Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $contract->id,
            'invoice_code' => 'HD101',
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => 0.00,
            'total_amount' => 5500000.00,
            'paid_amount' => 5500000.00,
            'remaining_amount' => 0.00,
            'status' => Invoice::STATUS_PAID,
            'due_date' => '2026-06-10',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => 'Tiền phòng tháng 6',
            'quantity' => 1,
            'unit_price' => 5000000.00,
            'amount' => 5000000.00,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => InvoiceItem::ITEM_TYPE_ELECTRIC,
            'description' => 'Tiền điện',
            'quantity' => 1,
            'unit_price' => 500000.00,
            'amount' => 500000.00,
        ]);

        // 2. Create Confirmed Payment (Revenue)
        Payment::create([
            'payment_code' => 'TXN10001',
            'invoice_id' => $invoice->id,
            'amount' => 5500000.00,
            'payment_date' => '2026-06-05 14:00:00',
            'status' => Payment::STATUS_CONFIRMED,
            'payment_method' => 1,
        ]);

        // 3. Create Expense Category & Expense
        $category = ExpenseCategory::create([
            'name' => 'Sửa chữa',
            'created_by' => $this->superAdmin->id,
        ]);

        Expense::create([
            'expense_code' => 'EXP10001',
            'building_id' => $this->building->id,
            'expense_category_id' => $category->id,
            'amount' => 1500000.00,
            'expense_date' => '2026-06-12',
            'status' => Expense::STATUS_RECORDED,
            'title' => 'Sửa vòi nước hỏng',
            'created_by' => $this->superAdmin->id,
        ]);

        // 4. Request Financial Report API
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/financials/report?year=2026&month_from=6&month_to=6');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        // Verify summary values
        $summary = $response->json('result.summary');
        $this->assertEquals(5500000.00, $summary['revenue']);
        $this->assertEquals(1500000.00, $summary['expenses']);
        $this->assertEquals(4000000.00, $summary['profit']);
        $this->assertEquals(72.7, $summary['profit_margin']);

        // Verify chart data
        $chart = $response->json('result.chart');
        $this->assertCount(1, $chart);
        $this->assertEquals('06/2026', $chart[0]['month']);
        $this->assertEquals(5500000.00, $chart[0]['revenue']);
        $this->assertEquals(1500000.00, $chart[0]['expenses']);
        $this->assertEquals(4000000.00, $chart[0]['profit']);

        // Verify revenue breakdown split
        $revenueBreakdown = $response->json('result.revenue_breakdown');
        $this->assertCount(2, $revenueBreakdown);
        $this->assertEquals('Tiền phòng', $revenueBreakdown[0]['label']);
        $this->assertEquals(5000000.00, $revenueBreakdown[0]['amount']);
        $this->assertEquals(90.9, $revenueBreakdown[0]['percentage']);

        $this->assertEquals('Tiền điện', $revenueBreakdown[1]['label']);
        $this->assertEquals(500000.00, $revenueBreakdown[1]['amount']);
        $this->assertEquals(9.1, $revenueBreakdown[1]['percentage']);

        // Verify expense breakdown
        $expenseBreakdown = $response->json('result.expense_breakdown');
        $this->assertCount(1, $expenseBreakdown);
        $this->assertEquals('Sửa chữa', $expenseBreakdown[0]['label']);
        $this->assertEquals(1500000.00, $expenseBreakdown[0]['amount']);
        $this->assertEquals(100.0, $expenseBreakdown[0]['percentage']);

        // Verify top buildings
        $topBuildings = $response->json('result.top_buildings');
        $this->assertCount(1, $topBuildings);
        $this->assertEquals($this->building->id, $topBuildings[0]['id']);
        $this->assertEquals('Building A', $topBuildings[0]['name']);
        $this->assertEquals(5500000.00, $topBuildings[0]['revenue']);
        $this->assertEquals(100.0, $topBuildings[0]['percentage']);
    }

    public function test_manager_cannot_retrieve_unauthorized_building_report()
    {
        // ManagerAdmin tries to access report of a non-managed building.
        $otherManager = Admin::create([
            'username' => 'other_manager',
            'full_name' => 'Other Manager',
            'email' => 'other@stayhub.local',
            'phone' => '0901234599',
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

        $response = $this->actingAs($this->managerAdmin, 'admin')
            ->getJson('/api/v1/admin/financials/report?building_id='.$otherBuilding->id);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Bạn không có quyền quản lý tòa nhà này');
    }

    public function test_report_includes_current_debt_and_rolled_debt_without_double_counting_revenue(): void
    {
        $contract = Contract::create([
            'contract_code' => 'HD-DEBT-TEST',
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
            'invoice_code' => 'HD-DEBT-05',
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
            'invoice_code' => 'HD-DEBT-06',
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
            'payment_code' => 'PAY-DEBT-001',
            'invoice_id' => $juneInvoice->id,
            'amount' => 500000.00,
            'payment_date' => '2026-06-15 09:00:00',
            'status' => Payment::STATUS_CONFIRMED,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/financials/report?year=2026&month_from=6&month_to=6');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.debt_breakdown.0.label', 'Nợ kỳ hiện tại')
            ->assertJsonPath('result.debt_breakdown.1.label', 'Nợ cũ chuyển sang')
            ->assertJsonPath('result.debt_breakdown.1.amount', 500000);

        $this->assertEquals(3000000.00, (float) $response->json('result.summary.revenue'));
        $this->assertEquals(500000.00, (float) $response->json('result.summary.collected_revenue'));
        $this->assertEquals(2500000.00, (float) $response->json('result.summary.debt'));
        $this->assertEquals(2500000.00, (float) $response->json('result.summary.outstanding_debt'));
        $this->assertEquals(3000000.00, (float) $response->json('result.chart.0.revenue'));
        $this->assertEquals(500000.00, (float) $response->json('result.chart.0.collected_revenue'));
        $this->assertEquals(2500000.00, (float) $response->json('result.chart.0.debt'));
        $this->assertEquals(3000000.00, (float) $response->json('result.chart.0.profit'));
        $this->assertEquals(2000000.00, (float) $response->json('result.debt_breakdown.0.amount'));
    }

    public function test_revenue_breakdown_splits_current_items_and_collected_rolled_debt(): void
    {
        $contract = Contract::create([
            'contract_code' => 'HD-BREAKDOWN-DEBT',
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
            'invoice_code' => 'HD-BREAKDOWN-05',
            'billing_month' => 5,
            'billing_year' => 2026,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'previous_debt_amount' => 0.00,
            'total_amount' => 1000000.00,
            'paid_amount' => 1000000.00,
            'remaining_amount' => 0.00,
            'status' => Invoice::STATUS_PAID,
            'due_date' => '2026-05-10',
        ]);

        $invoice = Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $contract->id,
            'invoice_code' => 'HD-BREAKDOWN-06',
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => 1000000.00,
            'total_amount' => 3000000.00,
            'paid_amount' => 1500000.00,
            'remaining_amount' => 1500000.00,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'due_date' => '2026-06-10',
        ]);

        InvoiceDebtRollover::create([
            'source_invoice_id' => $mayInvoice->id,
            'target_invoice_id' => $invoice->id,
            'amount' => 1000000.00,
            'settled_amount' => 1000000.00,
            'status' => InvoiceDebtRollover::STATUS_SETTLED,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => 'Tiền phòng tháng 6',
            'quantity' => 1,
            'unit_price' => 2000000.00,
            'amount' => 2000000.00,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => InvoiceItem::ITEM_TYPE_OLD_DEBT,
            'description' => 'Nợ cũ tháng 5',
            'quantity' => 1,
            'unit_price' => 1000000.00,
            'amount' => 1000000.00,
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-BREAKDOWN-001',
            'invoice_id' => $invoice->id,
            'amount' => 1500000.00,
            'payment_date' => '2026-06-15 09:00:00',
            'status' => Payment::STATUS_CONFIRMED,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
        ]);

        Payment::create([
            'payment_code' => 'PAY-BREAKDOWN-ALLOC-001',
            'invoice_id' => $mayInvoice->id,
            'allocated_from_payment_id' => $payment->id,
            'invoice_debt_rollover_id' => $invoice->debtRolloversIn()->first()->id,
            'is_internal_allocation' => true,
            'amount' => 1000000.00,
            'payment_date' => '2026-06-15 09:00:00',
            'status' => Payment::STATUS_CONFIRMED,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/financials/report?year=2026&month_from=6&month_to=6');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertEquals(3000000.00, (float) $response->json('result.summary.revenue'));
        $this->assertEquals(1500000.00, (float) $response->json('result.summary.collected_revenue'));
        $this->assertEquals(1500000.00, (float) $response->json('result.summary.debt'));
        $this->assertEquals('Thu nợ cũ', $response->json('result.revenue_breakdown.0.label'));
        $this->assertEquals(1000000.00, (float) $response->json('result.revenue_breakdown.0.amount'));
        $this->assertEquals('Tiền phòng', $response->json('result.revenue_breakdown.1.label'));
        $this->assertEquals(500000.00, (float) $response->json('result.revenue_breakdown.1.amount'));
        $this->assertCount(2, $response->json('result.revenue_breakdown'));
    }

    public function test_building_manager_report_only_contains_managed_building_debt(): void
    {
        $otherManager = Admin::create([
            'username' => 'other_manager_debt',
            'full_name' => 'Other Manager Debt',
            'email' => 'other_debt@stayhub.local',
            'phone' => '0901234588',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        $otherBuilding = Building::create([
            'name' => 'Building Other Debt',
            'slug' => 'building-other-debt',
            'address' => '789 Test St',
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
            'contract_code' => 'HD-MANAGED-DEBT',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => 5000000.00,
            'deposit_amount' => 5000000.00,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $otherContract = Contract::create([
            'contract_code' => 'HD-OTHER-DEBT',
            'room_id' => $otherRoom->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => 4000000.00,
            'deposit_amount' => 4000000.00,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        Invoice::create([
            'room_id' => $this->room->id,
            'contract_id' => $managedContract->id,
            'invoice_code' => 'HD-MANAGED-06',
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

        Invoice::create([
            'room_id' => $otherRoom->id,
            'contract_id' => $otherContract->id,
            'invoice_code' => 'HD-OTHER-06',
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

        $response = $this->actingAs($this->managerAdmin, 'admin')
            ->getJson('/api/v1/admin/financials/report?year=2026&month_from=6&month_to=6');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.top_buildings.0.id', $this->building->id);

        $this->assertEquals(1000000.00, (float) $response->json('result.summary.debt'));
        $this->assertEquals(1000000.00, (float) $response->json('result.top_buildings.0.debt'));

        $this->assertCount(1, $response->json('result.top_buildings'));
    }
}
