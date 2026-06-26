<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $managerAdmin;
    private Building $building;
    private Room $room;

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

        $roomType = RoomType::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create Room
        $this->room = Room::create([
            'room_number' => '101',
            'room_type_id' => $roomType->id,
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
            'billing_cycle_day' => 5,
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
            ->getJson('/api/v1/admin/financials/report?building_id=' . $otherBuilding->id);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Bạn không có quyền quản lý tòa nhà này');
    }
}
