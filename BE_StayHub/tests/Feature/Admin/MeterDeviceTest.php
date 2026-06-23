<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\Service;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\MeterDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterDeviceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Building $building;
    private Room $room;
    private Service $electricService;
    private Service $waterService;

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

        $this->building = Building::create([
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

        $this->room = Room::create([
            'building_id' => $this->building->id,
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

        $this->electricService = Service::create([
            'name' => 'Điện',
            'slug' => 'electric',
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
    }

    public function test_meter_code_is_automatically_generated_on_creation(): void
    {
        $payload = [
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100,
            'status' => MeterDevice::STATUS_ACTIVE,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/meter-devices', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        
        $expectedCode = 'BUA-101-ĐHĐ-0';
        $response->assertJsonPath('result.meter_code', $expectedCode);

        $this->assertDatabaseHas('meter_devices', [
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'meter_code' => $expectedCode,
        ]);
    }

    public function test_meter_code_index_increments_for_replacement_meters(): void
    {
        // First meter (index 0)
        $meter1 = MeterDevice::create([
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 100,
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);
        $this->assertEquals('BUA-101-ĐHĐ-0', $meter1->meter_code);

        // Second meter (index 1) via API
        $payload = [
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => 200,
            'status' => MeterDevice::STATUS_ACTIVE,
            'replaced_by_meter_id' => $meter1->id,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/meter-devices', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('result.meter_code', 'BUA-101-ĐHĐ-1');

        $this->assertDatabaseHas('meter_devices', [
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'meter_code' => 'BUA-101-ĐHĐ-1',
        ]);
    }
}
