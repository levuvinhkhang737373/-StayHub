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

class MeterReadingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Building $building;
    private Room $room;
    private MeterDevice $meterDevice;

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

        $service = Service::create([
            'name' => 'Điện',
            'slug' => 'electric',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'kWh',
            'is_active' => true,
        ]);

        $this->meterDevice = MeterDevice::create([
            'room_id' => $this->room->id,
            'service_id' => $service->id,
            'meter_type' => 1,
            'initial_reading' => 100,
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);
    }

    public function test_cannot_save_meter_reading_for_past_months(): void
    {
        $pastYear = now()->year;
        $pastMonth = now()->month - 1;
        if ($pastMonth < 1) {
            $pastMonth = 12;
            $pastYear -= 1;
        }

        $payload = [
            'meter_device_id' => $this->meterDevice->id,
            'billing_month' => $pastMonth,
            'billing_year' => $pastYear,
            'current_reading' => 150,
            'reading_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/admin/meter-readings', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'Không thể chốt chỉ số cho tháng cũ.');
    }

    public function test_can_save_meter_reading_for_current_or_future_months(): void
    {
        $year = now()->year;
        $month = now()->month;

        $payload = [
            'meter_device_id' => $this->meterDevice->id,
            'billing_month' => $month,
            'billing_year' => $year,
            'current_reading' => 150,
            'reading_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/admin/meter-readings', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Chốt số đồng hồ thành công');
    }
}
