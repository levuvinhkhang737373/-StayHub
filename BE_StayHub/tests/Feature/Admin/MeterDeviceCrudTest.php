<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MeterDeviceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is not available.');
        }

        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_meter_devices_index_route_is_registered(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/meter-devices')
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_super_admin_can_crud_meter_device(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->building($admin);
        $roomType = $this->roomType($admin);
        $room = $this->room($admin, $building, $roomType);
        $service = $this->service();

        // 1. Create a meter device
        $createResponse = $this->actingAs($admin, 'admin')->postJson('/api/admin/meter-devices', [
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-001',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100.5,
            'installed_at' => '2026-06-01',
            'status' => MeterDevice::STATUS_ACTIVE,
            'note' => 'Mô tả test',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.meter_code', 'MTR-001')
            ->assertJsonPath('result.room_number', $room->room_number)
            ->assertJsonPath('result.service_name', $service->name);

        $meterId = $createResponse->json('result.id');

        $this->assertDatabaseHas('meter_devices', [
            'id' => $meterId,
            'meter_code' => 'MTR-001',
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        // 2. Index / List meter devices
        $this->actingAs($admin, 'admin')->getJson('/api/admin/meter-devices')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.data.0.id', $meterId);

        // 3. Show meter device
        $this->actingAs($admin, 'admin')->getJson("/api/admin/meter-devices/{$meterId}")
            ->assertOk()
            ->assertJsonPath('result.meter_code', 'MTR-001');

        // 4. Create another meter device for same room & service (should fail)
        $this->actingAs($admin, 'admin')->postJson('/api/admin/meter-devices', [
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-002',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 200,
            'status' => MeterDevice::STATUS_ACTIVE,
        ])->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Phòng này đã có đồng hồ cho dịch vụ này');

        // 5. Update meter device (change status to replaced, requiring replacement meter)
        // Let's create a new meter first that will serve as the replacement
        $replacementMeter = MeterDevice::query()->create([
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-003',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 0,
            'status' => MeterDevice::STATUS_REPLACED,
        ]);

        // If updating status to replaced, replacement meter is required
        $this->actingAs($admin, 'admin')->putJson("/api/admin/meter-devices/{$meterId}", [
            'status' => MeterDevice::STATUS_REPLACED,
        ])->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Khi trạng thái là đã thay thế phải chọn đồng hồ thay thế');

        // Successful update with replacement meter
        $this->actingAs($admin, 'admin')->putJson("/api/admin/meter-devices/{$meterId}", [
            'status' => MeterDevice::STATUS_REPLACED,
            'replaced_by_meter_id' => $replacementMeter->id,
        ])->assertOk()
            ->assertJsonPath('result.status', MeterDevice::STATUS_REPLACED)
            ->assertJsonPath('result.replaced_by_meter_id', $replacementMeter->id);

        // 6. Delete meter device
        $this->actingAs($admin, 'admin')->deleteJson("/api/admin/meter-devices/{$meterId}")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('meter_devices', ['id' => $meterId]);
    }

    public function test_building_manager_role_restrictions(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $manager = $this->admin(Admin::ROLE_BUILDING_MANAGER);
        $otherManager = $this->admin(Admin::ROLE_BUILDING_MANAGER);

        $building = $this->building($manager);
        $roomType = $this->roomType($admin);
        $room = $this->room($admin, $building, $roomType);
        $service = $this->service();

        // Building manager can create meter for their own room
        $this->actingAs($manager, 'admin')->postJson('/api/admin/meter-devices', [
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-MAN-1',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100,
            'status' => MeterDevice::STATUS_ACTIVE,
        ])->assertCreated();

        // Building manager cannot create meter for a room they do not manage
        $this->actingAs($otherManager, 'admin')->postJson('/api/admin/meter-devices', [
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-MAN-2',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100,
            'status' => MeterDevice::STATUS_ACTIVE,
        ])->assertForbidden();
    }

    public function test_cannot_delete_when_has_readings_or_replaced_meters(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->building($admin);
        $roomType = $this->roomType($admin);
        $room = $this->room($admin, $building, $roomType);
        $service = $this->service();

        $meter = MeterDevice::query()->create([
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-DEL-FAIL',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100,
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        // Create a reading
        MeterReading::query()->create([
            'meter_device_id' => $meter->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 120,
            'consumption' => 20,
            'reading_date' => '2026-06-03',
            'status' => 1,
        ]);

        // Attempting to delete should fail
        $this->actingAs($admin, 'admin')->deleteJson("/api/admin/meter-devices/{$meter->id}")
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Không thể xóa đồng hồ đang có dữ liệu liên quan');

        $this->assertDatabaseHas('meter_devices', ['id' => $meter->id]);
    }

    public function test_update_status_unique_non_replaced_validation(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->building($admin);
        $roomType = $this->roomType($admin);
        $room = $this->room($admin, $building, $roomType);
        $service = $this->service();

        // Meter 1 is active
        $meter1 = MeterDevice::query()->create([
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-1',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100,
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        // Meter 2 is replaced
        $meter2 = MeterDevice::query()->create([
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-2',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 50,
            'status' => MeterDevice::STATUS_REPLACED,
            'replaced_by_meter_id' => $meter1->id,
        ]);

        // Try to updateStatus of Meter 2 back to ACTIVE (status 1) using patch
        // Since Meter 1 is already active, this should be blocked
        $this->actingAs($admin, 'admin')->patchJson("/api/admin/meter-devices/{$meter2->id}/status", [
            'status' => MeterDevice::STATUS_ACTIVE,
        ])->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Phòng này đã có đồng hồ cho dịch vụ này');
    }
    
    public function test_can_create_and_update_meter_device_using_room_number(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->building($admin);
        $roomType = $this->roomType($admin);
        $room = $this->room($admin, $building, $roomType);
        $newRoom = $this->room($admin, $building, $roomType);
        $service = $this->service();

        // 1. Create using room_number
        $response = $this->actingAs($admin, 'admin')->postJson('/api/admin/meter-devices', [
            'room_number' => $room->room_number,
            'service_id' => $service->id,
            'meter_code' => 'MTR-AUTO-001',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 50,
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.room_number', $room->room_number);

        $meterId = $response->json('result.id');
        $this->assertDatabaseHas('meter_devices', [
            'id' => $meterId,
            'room_id' => $room->id,
        ]);

        // 2. Create using non-existent room_number should fail
        $this->actingAs($admin, 'admin')->postJson('/api/admin/meter-devices', [
            'room_number' => 'NON_EXISTENT_ROOM_123',
            'service_id' => $service->id,
            'meter_code' => 'MTR-AUTO-002',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 50,
            'status' => MeterDevice::STATUS_ACTIVE,
        ])->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Không tìm thấy phòng với mã số này');

        // 3. Update using room_number
        $this->actingAs($admin, 'admin')->putJson("/api/admin/meter-devices/{$meterId}", [
            'room_number' => $newRoom->room_number,
        ])->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('meter_devices', [
            'id' => $meterId,
            'room_id' => $newRoom->id,
        ]);
    }

    public function test_final_reading_must_be_larger_than_initial_reading(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->building($admin);
        $roomType = $this->roomType($admin);
        $room = $this->room($admin, $building, $roomType);
        $service = $this->service();

        // 1. Create with final_reading <= initial_reading should fail
        $this->actingAs($admin, 'admin')->postJson('/api/admin/meter-devices', [
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-VAL-001',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100,
            'final_reading' => 50,
            'status' => MeterDevice::STATUS_ACTIVE,
        ])->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Chỉ số cuối phải lớn hơn chỉ số khởi tạo');

        // Create a valid meter
        $meter = MeterDevice::query()->create([
            'room_id' => $room->id,
            'service_id' => $service->id,
            'meter_code' => 'MTR-VAL-002',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100,
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        // 2. Update with final_reading <= initial_reading should fail
        $this->actingAs($admin, 'admin')->putJson("/api/admin/meter-devices/{$meter->id}", [
            'final_reading' => 90,
        ])->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Chỉ số cuối phải lớn hơn chỉ số khởi tạo');

        // 3. Update status with final_reading <= initial_reading should fail
        $this->actingAs($admin, 'admin')->patchJson("/api/admin/meter-devices/{$meter->id}/status", [
            'status' => MeterDevice::STATUS_INACTIVE,
            'final_reading' => 99.9,
        ])->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Chỉ số cuối phải lớn hơn chỉ số khởi tạo');
    }

    private function admin(int $role): Admin
    {
        return Admin::query()->create([
            'username' => 'admin_'.$role.'_'.uniqid(),
            'full_name' => 'Admin Test',
            'email' => 'admin_'.$role.'_'.uniqid().'@example.com',
            'phone' => '090'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
        ]);
    }

    private function service(): Service
    {
        return Service::query()->create([
            'service_code' => 'SV-'.uniqid(),
            'name' => 'Dịch vụ '.uniqid(),
            'service_type' => Service::SERVICE_TYPE_OTHER,
            'charge_method' => Service::CHARGE_METHOD_FIXED,
            'unit_name' => 'lần',
            'is_required' => false,
            'is_active' => true,
        ]);
    }

    private function building(Admin $admin): Building
    {
        $region = Region::query()->create([
            'code' => 'RG-'.uniqid(),
            'name' => 'Khu vực kiểm thử',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return Building::query()->create([
            'region_id' => $region->id,
            'manager_admin_id' => $admin->id,
            'name' => 'Tòa nhà kiểm thử',
            'address' => 'Địa chỉ kiểm thử',
            'total_floors' => 5,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);
    }

    private function roomType(Admin $admin): RoomType
    {
        return RoomType::query()->create([
            'name' => 'Loại phòng ' . uniqid(),
            'created_by' => $admin->id,
        ]);
    }

    private function room(Admin $admin, Building $building, RoomType $roomType): Room
    {
        $num = random_int(100, 999);
        return Room::query()->create([
            'building_id' => $building->id,
            'room_type_id' => $roomType->id,
            'room_number' => 'Phòng ' . $num,
            'slug' => 'phong-' . $num . '-' . uniqid(),
            'base_price' => 2000000,
            'max_occupants' => 4,
            'created_by' => $admin->id,
        ]);
    }
}
