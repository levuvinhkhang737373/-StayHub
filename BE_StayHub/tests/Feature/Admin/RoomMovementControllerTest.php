<?php

namespace Tests\Feature\Admin;

use App\Events\NotificationSent;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\RoomService;
use App\Models\RoomServicePrice;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RoomMovementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_transfer_tenant_schedules_then_executes_single_room_transfer_with_manual_refund(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_room_move', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa chuyển phòng', 'toa-chuyen-phong');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'A101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'A102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_move');

        $oldContract = Contract::create([
            'contract_code' => 'HD-OLD-MOVE',
            'room_id' => $fromRoom->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'room_price' => '3500000.00',
            'deposit_amount' => '5000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'payment_status' => Contract::PAYMENT_STATUS_SUCCESS,
            'created_by' => $admin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $oldContract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        $oldContract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '5000000.00',
            'transaction_date' => '2026-01-01',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'created_by' => $admin->id,
        ]);

        $movementDate = now('Asia/Ho_Chi_Minh')->addMonthNoOverflow()->startOfMonth()->toDateString();

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/room-transfers/tenant', [
            'tenant_id' => $tenant->id,
            'to_room_id' => $toRoom->id,
            'movement_date' => $movementDate,
            'deposit_deduction_amount' => 500000,
            'transfer_fee' => 100000,
            'note' => 'Chuyển sang phòng A102',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.status', RoomMovement::STATUS_PENDING)
            ->assertJsonPath('result.movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->assertJsonPath('result.deposit_transfer_amount', '0.00');

        $transferCode = (string) $response->json('result.transfer_code');
        $this->assertStringStartsWith('TRF-2026-07-', $transferCode);

        $movement = RoomMovement::query()->where('transfer_code', $transferCode)->firstOrFail();
        $this->assertSame(RoomMovement::STATUS_PENDING, (int) $movement->status);
        $this->assertSame($oldContract->id, (int) $movement->contract_id);
        $this->assertSame($oldContract->id, (int) $movement->source_contract_id);
        $this->assertNull($movement->destination_contract_id);
        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseMissing('contract_deposit_transactions', [
            'contract_id' => $oldContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_OUT,
        ]);

        $this->createPaidFinalInvoice($oldContract, $fromRoom, $admin, Carbon::parse($movementDate)->subDay()->toDateString(), 'INV-FINAL-OLD-MOVE');

        $this->artisan('room-transfers:execute-scheduled', ['--date' => $movementDate, '--code' => $transferCode])
            ->assertExitCode(0);

        $movement->refresh();
        $destinationContract = Contract::query()->findOrFail($movement->destination_contract_id);

        $this->assertSame(Contract::STATUS_PENDING_SIGN, (int) $destinationContract->status);
        $this->assertSame(RoomMovement::STATUS_EXECUTED, (int) $movement->status);
        $this->assertSame($destinationContract->id, (int) $movement->contract_id);
        $this->assertSame('3500000.00', (string) $movement->deposit_transfer_amount);
        $this->assertSame('0.00', (string) $movement->deposit_refund_amount);
        $this->assertSame('600000.00', (string) $movement->deduction_amount);
        $this->assertSame('900000.00', (string) $movement->manual_refund_amount);
        $this->assertSame('0.00', (string) $movement->settlement_due_amount);
        $this->assertSame(RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID, (int) $movement->settlement_payment_status);

        $this->assertDatabaseHas('room_movements', [
            'tenant_id' => $tenant->id,
            'contract_id' => $destinationContract->id,
            'from_room_id' => $fromRoom->id,
            'to_room_id' => $toRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'deposit_transfer_amount' => '3500000.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '600000.00',
            'manual_refund_amount' => '900000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $oldContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT,
            'amount' => '600000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $oldContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            'amount' => '900000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $oldContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_OUT,
            'amount' => '3500000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $destinationContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_IN,
            'amount' => '3500000.00',
        ]);

        $this->assertSame('0.00', $oldContract->fresh()->deposit_balance);
        $this->assertSame('3500000.00', $destinationContract->fresh()->deposit_balance);
        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Hoàn cọc hợp đồng',
        ]);
        $this->assertDatabaseHas('expenses', [
            'building_id' => $building->id,
            'room_id' => $fromRoom->id,
            'title' => 'Hoàn cọc HD-OLD-MOVE — Hoàn cọc dư khi chuyển phòng',
            'amount' => '900000.00',
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
            'status' => Expense::STATUS_RECORDED,
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('rooms', ['id' => $fromRoom->id, 'current_occupants' => 0]);
        $this->assertDatabaseHas('rooms', ['id' => $toRoom->id, 'current_occupants' => 0]);
        $this->assertDatabaseHas('contract_tenants', [
            'contract_id' => $destinationContract->id,
            'tenant_id' => $tenant->id,
            'is_staying' => true,
        ]);
    }

    public function test_execute_scheduled_transfer_blocks_when_old_contract_has_unpaid_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_room_move_debt', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa nợ cũ', 'toa-no-cu');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'D101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'D102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_move_debt');

        $oldContract = $this->createContract($fromRoom, $admin, 'HD-DEBT-MOVE');
        ContractTenant::create([
            'contract_id' => $oldContract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        $movementDate = now('Asia/Ho_Chi_Minh')->addMonthNoOverflow()->startOfMonth()->toDateString();

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/room-transfers/tenant', [
            'tenant_ids' => [$tenant->id],
            'to_room_id' => $toRoom->id,
            'movement_date' => $movementDate,
        ]);

        $response->assertStatus(201);
        $transferCode = (string) $response->json('result.transfer_code');

        Invoice::create([
            'invoice_code' => 'INV-DEBT-MOVE-001',
            'contract_id' => $oldContract->id,
            'room_id' => $fromRoom->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'previous_debt_amount' => '0.00',
            'total_amount' => '1000000.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '1000000.00',
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $admin->id,
        ]);

        $this->artisan('room-transfers:execute-scheduled', ['--date' => $movementDate, '--code' => $transferCode])
            ->assertExitCode(0);

        $movement = RoomMovement::query()->where('transfer_code', $transferCode)->firstOrFail();
        $this->assertSame(RoomMovement::STATUS_BLOCKED, (int) $movement->status);
        $this->assertStringContainsString('còn hóa đơn chưa thanh toán', (string) $movement->failure_reason);
        $this->assertNull($movement->destination_contract_id);
        $this->assertDatabaseCount('contracts', 1);
    }

    public function test_transfer_tenant_requires_new_deposit_greater_than_destination_room_price(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_room_move_deposit', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa cọc chuyển phòng', 'toa-coc-chuyen-phong');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'C101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'C102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_move_deposit');
        $oldContract = $this->createContract($fromRoom, $admin, 'HD-DEPOSIT-MOVE');

        ContractTenant::create([
            'contract_id' => $oldContract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/room-transfers/tenant', [
            'tenant_ids' => [$tenant->id],
            'to_room_id' => $toRoom->id,
            'movement_date' => now('Asia/Ho_Chi_Minh')->addMonthNoOverflow()->startOfMonth()->toDateString(),
            'new_deposit_amount' => 1000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tiền cọc yêu cầu phòng mới phải lớn hơn tiền phòng mới.');

        $this->assertDatabaseCount('room_movements', 0);
    }

    public function test_room_movement_index_filters_and_scopes_by_managed_building(): void
    {
        $superAdmin = $this->createAdmin('super_history', Admin::ROLE_SUPER_ADMIN);
        $managerA = $this->createAdmin('manager_a_history', Admin::ROLE_BUILDING_MANAGER);
        $managerB = $this->createAdmin('manager_b_history', Admin::ROLE_BUILDING_MANAGER);
        $buildingA = $this->createBuilding($managerA, 'Tòa A History', 'toa-a-history');
        $buildingB = $this->createBuilding($managerB, 'Tòa B History', 'toa-b-history');
        $roomType = $this->createRoomType($superAdmin);
        $roomA1 = $this->createRoom($buildingA, $roomType, $managerA, 'A201', 0);
        $roomA2 = $this->createRoom($buildingA, $roomType, $managerA, 'A202', 0);
        $roomB1 = $this->createRoom($buildingB, $roomType, $managerB, 'B201', 0);
        $tenantA = $this->createTenant($managerA, $buildingA, 'tenant_history_a');
        $tenantB = $this->createTenant($managerB, $buildingB, 'tenant_history_b');
        $contractA = $this->createContract($roomA2, $managerA, 'HD-HISTORY-A');
        $contractB = $this->createContract($roomB1, $managerB, 'HD-HISTORY-B');

        RoomMovement::create([
            'tenant_id' => $tenantA->id,
            'contract_id' => $contractA->id,
            'from_room_id' => $roomA1->id,
            'to_room_id' => $roomA2->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'movement_date' => '2026-06-01 09:00:00',
            'old_room_final_amount' => '5000000.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '3500000.00',
            'deposit_refund_amount' => '1000000.00',
            'deduction_amount' => '500000.00',
            'note' => 'Lịch sử của tòa A',
            'created_by' => $managerA->id,
        ]);

        RoomMovement::create([
            'tenant_id' => $tenantB->id,
            'contract_id' => $contractB->id,
            'from_room_id' => $roomB1->id,
            'to_room_id' => null,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_CHECKOUT,
            'movement_date' => '2026-06-02 09:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '1000000.00',
            'deduction_amount' => '0.00',
            'note' => 'Lịch sử của tòa B',
            'created_by' => $managerB->id,
        ]);

        $this->actingAs($managerA, 'admin')
            ->getJson('/api/v1/admin/room-movements')
            ->assertOk()
            ->assertJsonPath('result.meta.total', 1)
            ->assertJsonPath('result.data.0.tenant.username', 'tenant_history_a')
            ->assertJsonPath('result.data.0.from_room.room_number', 'A201')
            ->assertJsonPath('result.data.0.to_room.room_number', 'A202');

        $this->actingAs($managerA, 'admin')
            ->getJson("/api/v1/admin/room-movements?building_id={$buildingB->id}")
            ->assertForbidden();

        $this->actingAs($superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-movements?keyword=tenant_history_b&movement_type='.RoomMovement::MOVEMENT_TYPE_CHECKOUT)
            ->assertOk()
            ->assertJsonPath('result.meta.total', 1)
            ->assertJsonPath('result.data.0.tenant.username', 'tenant_history_b')
            ->assertJsonPath('result.data.0.movement_type_label', 'Trả phòng');
    }

    public function test_room_movement_index_search_without_match_returns_empty_list(): void
    {
        $admin = $this->createAdmin('super_history_empty_search', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa tìm rỗng', 'toa-tim-rong');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'E101', 0);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'E102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_empty_history');
        $contract = $this->createContract($fromRoom, $admin, 'HD-EMPTY-HISTORY');

        RoomMovement::create([
            'tenant_id' => $tenant->id,
            'contract_id' => $contract->id,
            'from_room_id' => $fromRoom->id,
            'to_room_id' => $toRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'movement_date' => '2026-06-01 09:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '0.00',
            'note' => 'Dữ liệu không khớp từ khóa lạ',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/room-movements?keyword=khong-co-ket-qua-nao')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.meta.total', 0)
            ->assertJsonCount(0, 'result.data');
    }

    public function test_transfer_tenant_executes_immediately_when_movement_date_is_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_room_date_guard', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa chặn ngày', 'toa-chan-ngay');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'M101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'M102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_date_guard');

        $oldContract = Contract::create([
            'contract_code' => 'HD-METER-GUARD',
            'room_id' => $fromRoom->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'room_price' => '3500000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $oldContract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        $this->createPaidFinalInvoice($oldContract, $fromRoom, $admin, now('Asia/Ho_Chi_Minh')->copy()->subDay()->toDateString(), 'INV-FINAL-METER-GUARD');

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/api/v1/admin/room-transfers/tenant', [
                'tenant_id' => $tenant->id,
                'to_room_id' => $toRoom->id,
                'movement_date' => now('Asia/Ho_Chi_Minh')->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.status', RoomMovement::STATUS_EXECUTED)
            ->assertJsonPath('result.executed_immediately', true);

        $transferCode = (string) $response->json('result.transfer_code');
        $movement = RoomMovement::query()->where('transfer_code', $transferCode)->firstOrFail();

        $this->assertSame(RoomMovement::STATUS_EXECUTED, (int) $movement->status);
        $this->assertSame(now('Asia/Ho_Chi_Minh')->toDateString(), $movement->movement_date?->toDateString());
        $this->assertNotNull($movement->destination_contract_id);
        $this->assertDatabaseHas('contract_tenants', [
            'contract_id' => $movement->destination_contract_id,
            'tenant_id' => $tenant->id,
            'is_staying' => true,
        ]);
    }

    public function test_execute_mid_month_transfer_moves_vehicle_billing_windows_without_double_charge(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_room_vehicle_move', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa xe chuyển phòng', 'toa-xe-chuyen-phong');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'V101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'V102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_vehicle_move');
        $oldContract = $this->createContract($fromRoom, $admin, 'HD-VEHICLE-MOVE');

        ContractTenant::create([
            'contract_id' => $oldContract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        $vehicle = Vehicle::create([
            'tenant_id' => $tenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => '30-V1 123.45',
            'brand' => 'Honda',
            'color' => 'Đen',
            'is_active' => true,
        ]);

        ContractVehicle::create([
            'contract_id' => $oldContract->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        $movementDate = '2026-07-15';

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/room-transfers/tenant', [
            'tenant_id' => $tenant->id,
            'to_room_id' => $toRoom->id,
            'movement_date' => $movementDate,
        ]);

        $response->assertStatus(201);
        $transferCode = (string) $response->json('result.transfer_code');

        $this->createPaidFinalInvoice($oldContract, $fromRoom, $admin, Carbon::parse($movementDate)->subDay()->toDateString(), 'INV-FINAL-VEHICLE-MOVE');

        $this->artisan('room-transfers:execute-scheduled', ['--date' => $movementDate, '--code' => $transferCode])
            ->assertExitCode(0);

        $movement = RoomMovement::query()->where('transfer_code', $transferCode)->firstOrFail();
        $newContractId = (int) $movement->destination_contract_id;

        $oldContractVehicle = ContractVehicle::query()
            ->where('contract_id', $oldContract->id)
            ->where('vehicle_id', $vehicle->id)
            ->firstOrFail();
        $newContractVehicle = ContractVehicle::query()
            ->where('contract_id', $newContractId)
            ->where('vehicle_id', $vehicle->id)
            ->firstOrFail();

        $this->assertSame('2026-07-14', $oldContractVehicle->ended_at?->toDateString());
        $this->assertSame('2026-07-14', $oldContractVehicle->billing_end_date?->toDateString());
        $this->assertFalse((bool) $oldContractVehicle->is_active);
        $this->assertSame('2026-07-15', $newContractVehicle->started_at?->toDateString());
        $this->assertSame('2026-07-15', $newContractVehicle->billing_start_date?->toDateString());
        $this->assertSame('100000.00', (string) $newContractVehicle->monthly_fee);
        $this->assertTrue((bool) $newContractVehicle->is_active);
    }

    public function test_executed_full_room_transfer_deactivates_old_room_services_and_cancels_future_room_price_schedules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_room_service_move', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa dịch vụ chuyển phòng', 'toa-dich-vu-chuyen-phong');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'S101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'S102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_service_move');
        $oldContract = $this->createContract($fromRoom, $admin, 'HD-SERVICE-MOVE');

        ContractTenant::create([
            'contract_id' => $oldContract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        $internet = Service::create([
            'name' => 'Internet chuyển phòng',
            'slug' => 'internet-chuyen-phong',
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $internet->id,
            'building_id' => $building->id,
            'price' => '100000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $oldRoomService = RoomService::create([
            'room_id' => $fromRoom->id,
            'service_id' => $internet->id,
        ]);

        RoomServicePrice::create([
            'room_service_id' => $oldRoomService->id,
            'contract_id' => null,
            'price' => '120000.00',
            'effective_from' => '2026-01-01',
            'created_by' => $admin->id,
        ]);

        RoomServicePrice::create([
            'room_service_id' => $oldRoomService->id,
            'contract_id' => null,
            'price' => '150000.00',
            'effective_from' => '2026-08-01',
            'created_by' => $admin->id,
        ]);

        $movementDate = '2026-07-15';

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/room-transfers/tenant', [
            'tenant_id' => $tenant->id,
            'to_room_id' => $toRoom->id,
            'movement_date' => $movementDate,
        ]);

        $response->assertStatus(201);
        $transferCode = (string) $response->json('result.transfer_code');

        $this->createPaidFinalInvoice($oldContract, $fromRoom, $admin, Carbon::parse($movementDate)->subDay()->toDateString(), 'INV-FINAL-SERVICE-MOVE');

        $this->artisan('room-transfers:execute-scheduled', ['--date' => $movementDate, '--code' => $transferCode])
            ->assertExitCode(0);

        $oldRoomService->refresh();
        $this->assertFalse((bool) $oldRoomService->is_active);
        $this->assertSame('2026-07-14', $oldRoomService->ended_at?->toDateString());

        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $oldRoomService->id,
            'contract_id' => null,
            'price' => '120000.00',
            'effective_to' => '2026-07-14',
        ]);

        $this->assertDatabaseMissing('room_service_prices', [
            'room_service_id' => $oldRoomService->id,
            'contract_id' => null,
            'price' => '150000.00',
            'effective_from' => '2026-08-01 00:00:00',
        ]);
    }

    public function test_executed_partial_room_transfer_keeps_old_room_services_active_for_remaining_tenants(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_partial_service_move', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa chuyển một phần', 'toa-chuyen-mot-phan');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'P101', 2);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'P102', 0);
        $movingTenant = $this->createTenant($admin, $building, 'tenant_partial_move');
        $remainingTenant = $this->createTenant($admin, $building, 'tenant_partial_stay');
        $oldContract = $this->createContract($fromRoom, $admin, 'HD-PARTIAL-SERVICE-MOVE');

        foreach ([$movingTenant, $remainingTenant] as $tenant) {
            ContractTenant::create([
                'contract_id' => $oldContract->id,
                'tenant_id' => $tenant->id,
                'join_date' => '2026-01-01',
                'billing_start_date' => '2026-01-01',
                'is_staying' => true,
                'created_by' => $admin->id,
            ]);
        }

        $internet = Service::create([
            'name' => 'Internet còn người ở',
            'slug' => 'internet-con-nguoi-o',
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $internet->id,
            'building_id' => $building->id,
            'price' => '100000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $oldRoomService = RoomService::create([
            'room_id' => $fromRoom->id,
            'service_id' => $internet->id,
        ]);

        RoomServicePrice::create([
            'room_service_id' => $oldRoomService->id,
            'contract_id' => null,
            'price' => '120000.00',
            'effective_from' => '2026-01-01',
            'created_by' => $admin->id,
        ]);

        $movementDate = '2026-07-15';

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/room-transfers/tenant', [
            'tenant_id' => $movingTenant->id,
            'to_room_id' => $toRoom->id,
            'movement_date' => $movementDate,
        ]);

        $response->assertStatus(201);
        $transferCode = (string) $response->json('result.transfer_code');

        $this->createPaidFinalInvoice($oldContract, $fromRoom, $admin, Carbon::parse($movementDate)->subDay()->toDateString(), 'INV-FINAL-PARTIAL-SERVICE');

        $this->artisan('room-transfers:execute-scheduled', ['--date' => $movementDate, '--code' => $transferCode])
            ->assertExitCode(0);

        $oldRoomService->refresh();
        $this->assertTrue((bool) $oldRoomService->is_active);
        $this->assertNull($oldRoomService->ended_at);
    }

    public function test_update_transfer_date_updates_group_and_broadcasts_notifications(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Ho_Chi_Minh'));
        Event::fake([NotificationSent::class]);

        $admin = $this->createAdmin('super_reschedule_transfer', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa đổi lịch', 'toa-doi-lich');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'R101', 2);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'R202', 0);
        $tenantA = $this->createTenant($admin, $building, 'tenant_reschedule_a');
        $tenantB = $this->createTenant($admin, $building, 'tenant_reschedule_b');
        $contract = $this->createContract($fromRoom, $admin, 'HD-RESCHEDULE');

        $transferCode = 'TRF-2026-07-RESCHEDULE';
        $movementA = RoomMovement::create($this->movementPayload($tenantA, $contract, $fromRoom, $toRoom, $admin, $transferCode, '2026-07-20', RoomMovement::STATUS_PENDING));
        $movementB = RoomMovement::create($this->movementPayload($tenantB, $contract, $fromRoom, $toRoom, $admin, $transferCode, '2026-07-20', RoomMovement::STATUS_BLOCKED, 'Hóa đơn cũ chưa thanh toán'));

        $response = $this->actingAs($admin, 'admin')->patchJson("/api/v1/admin/room-movements/{$movementA->id}/transfer-date", [
            'movement_date' => '2026-07-25',
            'note' => 'Tenant xin đổi lịch',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.transfer_code', $transferCode)
            ->assertJsonPath('result.movement.movement_date', '2026-07-25 00:00:00')
            ->assertJsonPath('result.movements.0.scheduled_payload.movement_date', '2026-07-25')
            ->assertJsonPath('result.movements.1.status', RoomMovement::STATUS_PENDING);

        foreach ([$movementA, $movementB] as $movement) {
            $movement->refresh();

            $this->assertSame('2026-07-25', $movement->movement_date?->toDateString());
            $this->assertSame(RoomMovement::STATUS_PENDING, (int) $movement->status);
            $this->assertNull($movement->failure_reason);
            $this->assertSame('2026-07-25', $movement->scheduled_payload['movement_date'] ?? null);
            $this->assertSame('Tenant xin đổi lịch', $movement->scheduled_payload['reschedule_note'] ?? null);
        }

        $this->assertDatabaseHas('notifications', [
            'title' => 'Ngày chuyển phòng đã thay đổi',
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'tenant_id' => $tenantA->id,
            'status' => Notification::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Ngày chuyển phòng đã thay đổi',
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'tenant_id' => $tenantB->id,
            'status' => Notification::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Lịch chuyển phòng đã đổi ngày',
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'target_admin_id' => null,
            'status' => Notification::STATUS_SENT,
        ]);

        Event::assertDispatched(NotificationSent::class, 3);
    }

    public function test_update_transfer_date_to_today_executes_immediately(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_reschedule_today', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa đổi lịch hôm nay', 'toa-doi-lich-hom-nay');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'T101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'T102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_reschedule_today');
        $contract = $this->createContract($fromRoom, $admin, 'HD-RESCHEDULE-TODAY');
        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);
        $movement = RoomMovement::create($this->movementPayload($tenant, $contract, $fromRoom, $toRoom, $admin, 'TRF-2026-07-TODAY', '2026-07-20', RoomMovement::STATUS_PENDING));

        $this->createPaidFinalInvoice($contract, $fromRoom, $admin, '2026-07-09', 'INV-FINAL-RESCHEDULE-TODAY');

        $response = $this->actingAs($admin, 'admin')->patchJson("/api/v1/admin/room-movements/{$movement->id}/transfer-date", [
            'movement_date' => '2026-07-10',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.executed_immediately', true)
            ->assertJsonPath('result.movement.status', RoomMovement::STATUS_EXECUTED);

        $movement->refresh();

        $this->assertSame(RoomMovement::STATUS_EXECUTED, (int) $movement->status);
        $this->assertSame('2026-07-10', $movement->movement_date?->toDateString());
        $this->assertNotNull($movement->destination_contract_id);
        $this->assertDatabaseHas('contract_tenants', [
            'contract_id' => $movement->destination_contract_id,
            'tenant_id' => $tenant->id,
            'is_staying' => true,
        ]);
    }

    public function test_update_transfer_date_rejects_executed_and_past_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_reschedule_invalid', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa chặn đổi lịch', 'toa-chan-doi-lich');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'R301', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'R302', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_reschedule_invalid');
        $contract = $this->createContract($fromRoom, $admin, 'HD-RESCHEDULE-INVALID');
        $movement = RoomMovement::create($this->movementPayload($tenant, $contract, $fromRoom, $toRoom, $admin, 'TRF-2026-07-INVALID', '2026-07-20', RoomMovement::STATUS_EXECUTED));

        $this->actingAs($admin, 'admin')->patchJson("/api/v1/admin/room-movements/{$movement->id}/transfer-date", [
            'movement_date' => '2026-07-25',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ được đổi ngày cho lịch chuyển phòng chưa thực thi');

        $movement->forceFill(['status' => RoomMovement::STATUS_PENDING])->save();

        $this->actingAs($admin, 'admin')->patchJson("/api/v1/admin/room-movements/{$movement->id}/transfer-date", [
            'movement_date' => '2026-07-09',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Ngày chuyển phòng không được nhỏ hơn ngày hiện tại.');
    }

    public function test_admin_can_record_full_cash_payment_for_transfer_extra_charge_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Ho_Chi_Minh'));
        Event::fake([NotificationSent::class]);

        $admin = $this->createAdmin('super_cash_extra', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa thu tiền mặt', 'toa-thu-tien-mat');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'C101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'C102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_cash_extra');
        $sourceContract = $this->createContract($fromRoom, $admin, 'HD-CASH-EXTRA-SOURCE');
        $destinationContract = $this->createContract($toRoom, $admin, 'HD-CASH-EXTRA-DEST');

        $movement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $sourceContract, $fromRoom, $toRoom, $admin, 'TRF-CASH-EXTRA-001', '2026-07-10', RoomMovement::STATUS_EXECUTED),
            [
                'contract_id' => $destinationContract->id,
                'destination_contract_id' => $destinationContract->id,
                'transfer_fee' => '200000.00',
                'deduction_amount' => '300000.00',
                'extra_charge_amount' => '500000.00',
                'settlement_due_amount' => '500000.00',
                'settlement_paid_amount' => '0.00',
                'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
                'settlement_payment_references' => [],
                'executed_at' => '2026-07-10 09:30:00',
            ]
        ));

        $response = $this->actingAs($admin, 'admin')->postJson("/api/v1/admin/room-movements/{$movement->id}/settlement-cash-payment", [
            'note' => 'Thu đủ tại quầy lễ tân',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.movement.settlement_payment_status', RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID)
            ->assertJsonPath('result.reference.amount', '500000.00')
            ->assertJsonPath('result.reference.deposit_amount', '0.00')
            ->assertJsonPath('result.reference.extra_amount', '500000.00')
            ->assertJsonPath('result.reference.payment_method', ContractDepositTransaction::PAYMENT_METHOD_CASH)
            ->assertJsonPath('result.reference.collected_by', $admin->id)
            ->assertJsonPath('result.reference.note', 'Thu đủ tại quầy lễ tân');

        $movement->refresh();
        $this->assertSame('500000.00', (string) $movement->settlement_paid_amount);
        $this->assertSame(RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID, (int) $movement->settlement_payment_status);
        $this->assertSame('500000.00', $movement->settlement_payment_references[0]['extra_amount']);
        $this->assertSame(ContractDepositTransaction::PAYMENT_METHOD_CASH, $movement->settlement_payment_references[0]['payment_method']);

        $this->assertDatabaseMissing('contract_deposit_transactions', [
            'contract_id' => $destinationContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
        ]);
        Event::assertDispatched(NotificationSent::class, 2);
    }

    public function test_admin_cash_transfer_payment_splits_deposit_and_extra_charge(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', 'Asia/Ho_Chi_Minh'));
        Event::fake([NotificationSent::class]);

        $admin = $this->createAdmin('super_cash_split', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa thu tách khoản', 'toa-thu-tach-khoan');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'S101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'S102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_cash_split');
        $sourceContract = $this->createContract($fromRoom, $admin, 'HD-CASH-SPLIT-SOURCE');
        $destinationContract = Contract::create([
            'contract_code' => 'HD-CASH-SPLIT-DEST',
            'room_id' => $toRoom->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-12-31',
            'room_price' => '3500000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        $movement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $sourceContract, $fromRoom, $toRoom, $admin, 'TRF-CASH-SPLIT-001', '2026-07-10', RoomMovement::STATUS_EXECUTED),
            [
                'contract_id' => $destinationContract->id,
                'destination_contract_id' => $destinationContract->id,
                'transfer_fee' => '200000.00',
                'deduction_amount' => '300000.00',
                'deposit_due_amount' => '3000000.00',
                'extra_charge_amount' => '500000.00',
                'settlement_due_amount' => '3500000.00',
                'settlement_paid_amount' => '0.00',
                'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
                'settlement_payment_references' => [],
                'executed_at' => '2026-07-10 10:30:00',
            ]
        ));

        $response = $this->actingAs($admin, 'admin')->postJson("/api/v1/admin/room-movements/{$movement->id}/settlement-cash-payment");

        $response->assertOk()
            ->assertJsonPath('result.reference.amount', '3500000.00')
            ->assertJsonPath('result.reference.deposit_amount', '3000000.00')
            ->assertJsonPath('result.reference.extra_amount', '500000.00')
            ->assertJsonPath('result.reference.payment_method', ContractDepositTransaction::PAYMENT_METHOD_CASH);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $destinationContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '3000000.00',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_CASH,
            'created_by' => $admin->id,
        ]);

        $movement->refresh();
        $this->assertSame('3500000.00', (string) $movement->settlement_paid_amount);
        $this->assertSame(RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID, (int) $movement->settlement_payment_status);
        $this->assertSame('3000000.00', $movement->settlement_payment_references[0]['deposit_amount']);
        $this->assertSame('500000.00', $movement->settlement_payment_references[0]['extra_amount']);
        Event::assertDispatched(NotificationSent::class, 2);
    }

    public function test_only_destination_building_admin_or_super_admin_can_collect_transfer_cash_payment(): void
    {
        $sourceAdmin = $this->createAdmin('source_cash_manager', Admin::ROLE_BUILDING_MANAGER);
        $destinationAdmin = $this->createAdmin('destination_cash_manager', Admin::ROLE_BUILDING_MANAGER);
        $sourceBuilding = $this->createBuilding($sourceAdmin, 'Tòa nguồn thu tiền', 'toa-nguon-thu-tien');
        $destinationBuilding = $this->createBuilding($destinationAdmin, 'Tòa đích thu tiền', 'toa-dich-thu-tien');
        $roomType = $this->createRoomType($sourceAdmin);
        $fromRoom = $this->createRoom($sourceBuilding, $roomType, $sourceAdmin, 'P101', 1);
        $toRoom = $this->createRoom($destinationBuilding, $roomType, $destinationAdmin, 'P201', 0);
        $tenant = $this->createTenant($sourceAdmin, $sourceBuilding, 'tenant_cash_permission');
        $sourceContract = $this->createContract($fromRoom, $sourceAdmin, 'HD-CASH-PERMISSION-SOURCE');
        $destinationContract = $this->createContract($toRoom, $destinationAdmin, 'HD-CASH-PERMISSION-DEST');

        $movement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $sourceContract, $fromRoom, $toRoom, $sourceAdmin, 'TRF-CASH-PERMISSION-001', '2026-07-10', RoomMovement::STATUS_EXECUTED),
            [
                'contract_id' => $destinationContract->id,
                'destination_contract_id' => $destinationContract->id,
                'extra_charge_amount' => '500000.00',
                'settlement_due_amount' => '500000.00',
                'settlement_paid_amount' => '0.00',
                'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
                'settlement_payment_references' => [],
                'executed_at' => '2026-07-10 09:00:00',
            ]
        ));

        $this->actingAs($sourceAdmin, 'admin')->postJson("/api/v1/admin/room-movements/{$movement->id}/settlement-cash-payment")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Chỉ admin của tòa nhà phòng đến hoặc super admin mới được thu tiền mặt chuyển phòng');

        $movement->refresh();
        $this->assertSame('0.00', (string) $movement->settlement_paid_amount);
        $this->assertSame(RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING, (int) $movement->settlement_payment_status);

        $this->actingAs($destinationAdmin, 'admin')->postJson("/api/v1/admin/room-movements/{$movement->id}/settlement-cash-payment")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.reference.amount', '500000.00')
            ->assertJsonPath('result.reference.collected_by', $destinationAdmin->id);
    }

    public function test_admin_cash_transfer_payment_rejects_client_amount_payload(): void
    {
        $admin = $this->createAdmin('super_cash_amount', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa chặn nhập số tiền', 'toa-chan-nhap-so-tien');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'A301', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'A302', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_cash_amount');
        $sourceContract = $this->createContract($fromRoom, $admin, 'HD-CASH-AMOUNT-SOURCE');
        $destinationContract = $this->createContract($toRoom, $admin, 'HD-CASH-AMOUNT-DEST');

        $movement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $sourceContract, $fromRoom, $toRoom, $admin, 'TRF-CASH-AMOUNT-001', '2026-07-10', RoomMovement::STATUS_EXECUTED),
            [
                'contract_id' => $destinationContract->id,
                'destination_contract_id' => $destinationContract->id,
                'extra_charge_amount' => '500000.00',
                'settlement_due_amount' => '500000.00',
                'settlement_paid_amount' => '0.00',
                'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
                'settlement_payment_references' => [],
                'executed_at' => '2026-07-10 09:00:00',
            ]
        ));

        $this->actingAs($admin, 'admin')->postJson("/api/v1/admin/room-movements/{$movement->id}/settlement-cash-payment", [
            'amount' => 100000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Số tiền thu chuyển phòng được hệ thống tự tính, không được gửi từ giao diện.');

        $movement->refresh();
        $this->assertSame('0.00', (string) $movement->settlement_paid_amount);
        $this->assertSame(RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING, (int) $movement->settlement_payment_status);
    }

    public function test_admin_cash_transfer_payment_rejects_invalid_transfer_states(): void
    {
        $admin = $this->createAdmin('super_cash_invalid', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa chặn thu tiền mặt', 'toa-chan-thu-tien-mat');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'I101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'I102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_cash_invalid');
        $sourceContract = $this->createContract($fromRoom, $admin, 'HD-CASH-INVALID-SOURCE');
        $destinationContract = $this->createContract($toRoom, $admin, 'HD-CASH-INVALID-DEST');

        $pendingMovement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $sourceContract, $fromRoom, $toRoom, $admin, 'TRF-CASH-PENDING-001', '2026-07-20', RoomMovement::STATUS_PENDING),
            [
                'destination_contract_id' => $destinationContract->id,
                'settlement_due_amount' => '500000.00',
                'settlement_paid_amount' => '0.00',
                'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
            ]
        ));

        $this->actingAs($admin, 'admin')->postJson("/api/v1/admin/room-movements/{$pendingMovement->id}/settlement-cash-payment")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ được thu tiền cho lịch chuyển phòng đã thực thi');

        $paidMovement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $sourceContract, $fromRoom, $toRoom, $admin, 'TRF-CASH-PAID-001', '2026-07-10', RoomMovement::STATUS_EXECUTED),
            [
                'destination_contract_id' => $destinationContract->id,
                'settlement_due_amount' => '500000.00',
                'settlement_paid_amount' => '500000.00',
                'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
                'executed_at' => '2026-07-10 09:00:00',
            ]
        ));

        $this->actingAs($admin, 'admin')->postJson("/api/v1/admin/room-movements/{$paidMovement->id}/settlement-cash-payment")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Khoản chuyển phòng đã được thanh toán đủ');

        $missingDestinationMovement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $sourceContract, $fromRoom, $toRoom, $admin, 'TRF-CASH-NO-DEST-001', '2026-07-10', RoomMovement::STATUS_EXECUTED),
            [
                'destination_contract_id' => null,
                'deposit_due_amount' => '3000000.00',
                'settlement_due_amount' => '3000000.00',
                'settlement_paid_amount' => '0.00',
                'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PENDING,
                'executed_at' => '2026-07-10 09:00:00',
            ]
        ));

        $this->actingAs($admin, 'admin')->postJson("/api/v1/admin/room-movements/{$missingDestinationMovement->id}/settlement-cash-payment")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Không tìm thấy hợp đồng đích để ghi nhận cọc chuyển phòng');
    }

    private function createAdmin(string $username, int $role): Admin
    {
        return Admin::create([
            'username' => $username,
            'full_name' => ucfirst(str_replace('_', ' ', $username)),
            'email' => "{$username}@stayhub.local",
            'phone' => '090'.random_int(1000000, 9999999),
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);
    }

    private function createBuilding(Admin $manager, string $name, string $slug): Building
    {
        $region = Region::create([
            'name' => "Region {$slug}",
            'code' => strtoupper(str_replace('-', '_', $slug)),
            'created_by' => $manager->id,
        ]);

        return Building::create([
            'name' => $name,
            'slug' => $slug,
            'address' => '123 Test Street',
            'region_id' => $region->id,
            'manager_admin_id' => $manager->id,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
            'created_by' => $manager->id,
            'status' => Building::STATUS_ACTIVE,
        ]);
    }

    private function createRoomType(Admin $admin): RoomType
    {
        return RoomType::create([
            'name' => 'Standard History',
            'slug' => 'standard-history-'.random_int(1000, 9999),
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);
    }

    private function createRoom(Building $building, RoomType $roomType, Admin $admin, string $roomNumber, int $currentOccupants): Room
    {
        return Room::create([
            'building_id' => $building->id,
            'room_type_id' => $roomType->id,
            'room_number' => $roomNumber,
            'slug' => strtolower($roomNumber),
            'floor' => 1,
            'base_price' => '3500000.00',
            'max_occupants' => 4,
            'current_occupants' => $currentOccupants,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);
    }

    private function createTenant(Admin $admin, Building $building, string $username): Tenant
    {
        return Tenant::create([
            'username' => $username,
            'full_name' => ucfirst(str_replace('_', ' ', $username)),
            'email' => "{$username}@stayhub.local",
            'phone' => '091'.random_int(1000000, 9999999),
            'password' => bcrypt('password'),
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => (string) random_int(100000000000, 999999999999),
            'permanent_address' => 'Test Permanent Address',
            'date_of_birth' => '2000-01-01',
            'building_id' => $building->id,
            'created_by' => $admin->id,
        ]);
    }

    private function createContract(Room $room, Admin $admin, string $contractCode): Contract
    {
        return Contract::create([
            'contract_code' => $contractCode,
            'room_id' => $room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'room_price' => '3500000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);
    }

    private function createPaidFinalInvoice(Contract $contract, Room $room, Admin $admin, string $cutoffDate, string $invoiceCode): Invoice
    {
        $cutoff = Carbon::parse($cutoffDate)->startOfDay();

        return Invoice::create([
            'invoice_code' => $invoiceCode,
            'contract_id' => $contract->id,
            'room_id' => $room->id,
            'billing_month' => $cutoff->month,
            'billing_year' => $cutoff->year,
            'period_start' => $cutoff->copy()->startOfMonth()->toDateString(),
            'period_end' => $cutoff->toDateString(),
            'previous_debt_amount' => '0.00',
            'total_amount' => '0.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '0.00',
            'status' => Invoice::STATUS_PAID,
            'created_by' => $admin->id,
        ]);
    }

    private function movementPayload(
        Tenant $tenant,
        Contract $contract,
        Room $fromRoom,
        Room $toRoom,
        Admin $admin,
        string $transferCode,
        string $movementDate,
        int $status,
        ?string $failureReason = null,
    ): array {
        return [
            'transfer_code' => $transferCode,
            'tenant_id' => $tenant->id,
            'contract_id' => $contract->id,
            'source_contract_id' => $contract->id,
            'destination_contract_id' => null,
            'from_room_id' => $fromRoom->id,
            'to_room_id' => $toRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => $status,
            'movement_date' => "{$movementDate} 09:00:00",
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
            'scheduled_payload' => [
                'tenant_ids' => [$tenant->id],
                'to_room_id' => $toRoom->id,
                'movement_date' => $movementDate,
                'deposit_deduction_amount' => '0.00',
                'transfer_fee' => '0.00',
                'new_deposit_amount' => (string) $toRoom->base_price,
                'note' => null,
                'deduction_items' => [],
            ],
            'failure_reason' => $failureReason,
            'created_by' => $admin->id,
        ];
    }


    public function test_transfer_tenant_rejects_destination_room_in_inactive_building(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_room_move_inactive_building', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa chuyển phòng inactive', 'toa-chuyen-phong-inactive');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'IB101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'IB102', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_move_inactive_building');
        $oldContract = $this->createContract($fromRoom, $admin, 'HD-INACTIVE-BUILDING-MOVE');

        ContractTenant::create([
            'contract_id' => $oldContract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        $building->update(['status' => Building::STATUS_MAINTENANCE]);

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/room-transfers/tenant', [
            'tenant_ids' => [$tenant->id],
            'to_room_id' => $toRoom->id,
            'movement_date' => now('Asia/Ho_Chi_Minh')->addMonthNoOverflow()->startOfMonth()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->assertDatabaseCount('room_movements', 0);
    }

}
