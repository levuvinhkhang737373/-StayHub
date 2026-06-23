<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomMovementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_tenant_writes_correct_room_movement_and_deposit_ledger(): void
    {
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
            'billing_cycle_day' => 5,
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

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/room-transfers/tenant', [
            'tenant_id' => $tenant->id,
            'to_room_id' => $toRoom->id,
            'movement_date' => now()->toDateString(),
            'deposit_settlement_amount' => 5000000,
            'deposit_deduction_amount' => 500000,
            'deposit_refund_amount' => 1000000,
            'transfer_fee' => 100000,
            'note' => 'Chuyển sang phòng A102',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->assertJsonPath('result.deposit_transfer_amount', '3500000.00');

        $destinationContractId = (int) $response->json('result.contract_id');
        $destinationContract = Contract::findOrFail($destinationContractId);

        $this->assertDatabaseHas('room_movements', [
            'tenant_id' => $tenant->id,
            'contract_id' => $destinationContractId,
            'from_room_id' => $fromRoom->id,
            'to_room_id' => $toRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'deposit_transfer_amount' => '3500000.00',
            'deposit_refund_amount' => '1000000.00',
            'deduction_amount' => '500000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $oldContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT,
            'amount' => '500000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $oldContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            'amount' => '1000000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $oldContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_OUT,
            'amount' => '3500000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $destinationContractId,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER_IN,
            'amount' => '3500000.00',
        ]);

        $this->assertSame('0.00', $oldContract->fresh()->deposit_balance);
        $this->assertSame('3500000.00', $destinationContract->fresh()->deposit_balance);
        $this->assertDatabaseHas('rooms', ['id' => $fromRoom->id, 'current_occupants' => 0]);
        $this->assertDatabaseHas('rooms', ['id' => $toRoom->id, 'current_occupants' => 1]);
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
            'billing_cycle_day' => 5,
            'room_price' => '3500000.00',
            'deposit_amount' => '0.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);
    }
}
