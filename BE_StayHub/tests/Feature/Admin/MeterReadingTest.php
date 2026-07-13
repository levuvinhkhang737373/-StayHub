<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\Service;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Contract;
use Illuminate\Support\Carbon;
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
            ->postJson('/api/v1/admin/meter-readings', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'Không thể chốt chỉ số cho tháng cũ.');
    }

    public function test_can_save_meter_reading_for_current_or_future_months(): void
    {
        $year = now()->year;
        $month = now()->month;

        $contract = Contract::create([
            'contract_code' => 'HD-AUTO-CONTRACT',
            'room_id' => $this->room->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $payload = [
            'meter_device_id' => $this->meterDevice->id,
            'billing_month' => $month,
            'billing_year' => $year,
            'current_reading' => 150,
            'reading_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/meter-readings', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Chốt số đồng hồ thành công');

        $this->assertEquals(150, (float)$this->meterDevice->fresh()->initial_reading);
        $this->assertDatabaseHas('meter_readings', [
            'meter_device_id' => $this->meterDevice->id,
            'contract_id' => $contract->id,
            'billing_month' => $month,
            'billing_year' => $year,
            'current_reading' => '150.00',
        ]);
    }

    public function test_can_save_same_meter_period_for_different_contracts_without_overwriting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00'));

        $oldContract = Contract::create([
            'contract_code' => 'HD-METER-OLD',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'actual_end_date' => '2026-07-14',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_LIQUIDATED,
            'created_by' => $this->superAdmin->id,
        ]);

        $newContract = Contract::create([
            'contract_code' => 'HD-METER-NEW',
            'room_id' => $this->room->id,
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-31',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/meter-readings', [
                'meter_device_id' => $this->meterDevice->id,
                'contract_id' => $oldContract->id,
                'billing_month' => 7,
                'billing_year' => 2026,
                'current_reading' => '150.00',
                'reading_date' => '2026-07-14',
            ])
            ->assertStatus(200);

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/meter-readings', [
                'meter_device_id' => $this->meterDevice->id,
                'contract_id' => $newContract->id,
                'billing_month' => 7,
                'billing_year' => 2026,
                'current_reading' => '210.00',
                'reading_date' => '2026-07-31',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('meter_readings', [
            'meter_device_id' => $this->meterDevice->id,
            'contract_id' => $oldContract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'previous_reading' => '100.00',
            'current_reading' => '150.00',
            'consumption' => '50.00',
        ]);
        $this->assertDatabaseHas('meter_readings', [
            'meter_device_id' => $this->meterDevice->id,
            'contract_id' => $newContract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'previous_reading' => '150.00',
            'current_reading' => '210.00',
            'consumption' => '60.00',
        ]);
        $this->assertSame(2, MeterReading::query()->where('meter_device_id', $this->meterDevice->id)->where('billing_month', 7)->where('billing_year', 2026)->count());

        Carbon::setTestNow();
    }

    public function test_rejects_meter_reading_contract_from_another_room(): void
    {
        $otherRoom = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->room->room_type_id,
            'room_number' => '102',
            'slug' => '102',
            'floor' => 1,
            'base_price' => '3500000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $otherContract = Contract::create([
            'contract_code' => 'HD-WRONG-ROOM',
            'room_id' => $otherRoom->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/meter-readings', [
                'meter_device_id' => $this->meterDevice->id,
                'contract_id' => $otherContract->id,
                'billing_month' => now()->month,
                'billing_year' => now()->year,
                'current_reading' => '150.00',
                'reading_date' => now()->toDateString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
    }

    public function test_saved_meter_reading_returns_correct_previous_reading_in_init(): void
    {
        $year = now()->year;
        $month = now()->month;

        // Create an active contract for the room so it shows up in init
        Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // First save a reading (from 100 to 150)
        $payload = [
            'meter_device_id' => $this->meterDevice->id,
            'billing_month' => $month,
            'billing_year' => $year,
            'current_reading' => 150,
            'reading_date' => now()->toDateString(),
        ];

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/meter-readings', $payload)
            ->assertStatus(200);

        // Fetch using the init endpoint
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/meter-readings/init?building_id={$this->building->id}&billing_month={$month}&billing_year={$year}");

        $response->assertStatus(200);
        
        // Find the room and the meter inside result.rooms
        $rooms = $response->json('result.rooms');
        $this->assertNotEmpty($rooms);
        
        $roomData = collect($rooms)->firstWhere('room_id', $this->room->id);
        $this->assertNotNull($roomData);
        
        $meterData = collect($roomData['meters'])->firstWhere('id', $this->meterDevice->id);
        $this->assertNotNull($meterData);
        
        // Assert that previous_reading is 100 (which was the initial reading before update)
        // and NOT 150 (the newly saved current_reading/initial_reading)
        $this->assertEquals(100.0, (float)$meterData['previous_reading']);
        $this->assertEquals(150.0, (float)$meterData['existing_reading']['current_reading']);
    }

    public function test_updating_historical_reading_does_not_rewind_meter_initial_reading(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11 10:00:00'));

        MeterReading::query()->create([
            'meter_device_id' => $this->meterDevice->id,
            'billing_month' => 8,
            'billing_year' => 2026,
            'previous_reading' => '100.00',
            'current_reading' => '150.00',
            'consumption' => '50.00',
            'reading_date' => '2026-08-31',
            'status' => MeterReading::STATUS_CONFIRMED,
            'created_by' => $this->superAdmin->id,
        ]);

        MeterReading::query()->create([
            'meter_device_id' => $this->meterDevice->id,
            'billing_month' => 9,
            'billing_year' => 2026,
            'previous_reading' => '150.00',
            'current_reading' => '210.00',
            'consumption' => '60.00',
            'reading_date' => '2026-09-30',
            'status' => MeterReading::STATUS_CONFIRMED,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->meterDevice->forceFill(['initial_reading' => '210.00'])->save();

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/meter-readings', [
                'meter_device_id' => $this->meterDevice->id,
                'billing_month' => 8,
                'billing_year' => 2026,
                'current_reading' => '220.00',
                'reading_date' => '2026-08-31',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertSame(210.0, (float) $this->meterDevice->fresh()->initial_reading);
        $this->assertDatabaseHas('meter_readings', [
            'meter_device_id' => $this->meterDevice->id,
            'billing_month' => 8,
            'billing_year' => 2026,
            'current_reading' => '220.00',
            'consumption' => '120.00',
        ]);

        Carbon::setTestNow();
    }
}
