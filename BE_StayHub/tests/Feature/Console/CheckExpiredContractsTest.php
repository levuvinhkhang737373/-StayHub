<?php

namespace Tests\Feature\Console;

use App\Events\ContractExpired;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Notification;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CheckExpiredContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_due_active_contracts_expired_and_closes_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-02 08:00:00', 'Asia/Ho_Chi_Minh'));
        Event::fake([ContractExpired::class]);

        $admin = Admin::query()->create([
            'username' => 'expired_admin',
            'full_name' => 'Expired Admin',
            'email' => 'expired_admin@stayhub.local',
            'phone' => '0909000000',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        $region = Region::query()->create([
            'name' => 'Expired Region',
            'code' => 'EXPIRED_REGION',
            'created_by' => $admin->id,
        ]);

        $building = Building::query()->create([
            'name' => 'Expired Building',
            'slug' => 'expired-building',
            'region_id' => $region->id,
            'manager_admin_id' => $admin->id,
            'created_by' => $admin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $roomType = RoomType::query()->create([
            'name' => 'Expired Standard',
            'slug' => 'expired-standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        $room = Room::query()->create([
            'building_id' => $building->id,
            'room_type_id' => $roomType->id,
            'room_number' => 'E101',
            'slug' => 'e101',
            'floor' => 1,
            'base_price' => '3500000.00',
            'max_occupants' => 4,
            'current_occupants' => 1,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        $tenant = Tenant::query()->create([
            'username' => 'expired_tenant',
            'full_name' => 'Expired Tenant',
            'email' => 'expired_tenant@stayhub.local',
            'phone' => '0919000000',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '900000000001',
            'identity_date' => '2020-01-01',
            'identity_place' => 'Test',
            'permanent_address' => 'Test Address',
            'date_of_birth' => '2000-01-01',
            'created_by' => $admin->id,
            'building_id' => $building->id,
        ]);

        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => '59-A1 999.99',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);

        $contract = Contract::query()->create([
            'contract_code' => 'HD-AUTO-EXPIRED',
            'room_id' => $room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-07-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'payment_status' => Contract::PAYMENT_STATUS_SUCCESS,
            'created_by' => $admin->id,
        ]);

        ContractTenant::query()->create([
            'contract_id' => $contract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        ContractVehicle::query()->create([
            'contract_id' => $contract->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-01-01',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        $this->artisan('contracts:check-expired')->assertExitCode(0);

        $updatedContract = $contract->fresh();
        $this->assertEquals(Contract::STATUS_EXPIRED, $updatedContract->status);
        $this->assertNull($updatedContract->actual_end_date);

        $contractTenant = ContractTenant::query()->where('contract_id', $contract->id)->where('tenant_id', $tenant->id)->first();
        $this->assertFalse((bool) $contractTenant->is_staying);
        $this->assertEquals('2026-07-01', $contractTenant->leave_date->toDateString());
        $this->assertEquals('2026-07-01', $contractTenant->billing_end_date->toDateString());

        $contractVehicle = ContractVehicle::query()->where('contract_id', $contract->id)->where('vehicle_id', $vehicle->id)->first();
        $this->assertFalse((bool) $contractVehicle->is_active);
        $this->assertEquals('2026-07-01', $contractVehicle->ended_at->toDateString());
        $this->assertEquals('2026-07-01', $contractVehicle->billing_end_date->toDateString());

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'current_occupants' => 0,
        ]);

        $this->assertDatabaseHas('notifications', [
            'title' => 'Hợp đồng hết hạn',
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'room_id' => $room->id,
        ]);

        Event::assertDispatched(ContractExpired::class);

        Carbon::setTestNow();
    }
}
