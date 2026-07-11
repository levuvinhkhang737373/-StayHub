<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperationalStateGuardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Building $building;
    private RoomType $roomType;
    private Room $room;
    private Tenant $tenant;
    private Service $electricService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = Admin::query()->create([
            'username' => 'super_state_guard',
            'full_name' => 'Super State Guard',
            'email' => 'super-state-guard@stayhub.local',
            'phone' => '0909000001',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        $region = Region::query()->create([
            'name' => 'State Guard Region',
            'code' => 'STATE_GUARD_REGION',
            'created_by' => $this->admin->id,
        ]);

        $this->building = Building::query()->create([
            'region_id' => $region->id,
            'manager_admin_id' => $this->admin->id,
            'name' => 'State Guard Building',
            'slug' => 'state-guard-building',
            'address' => '123 State Guard',
            'total_floors' => 5,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $this->roomType = RoomType::query()->create([
            'name' => 'State Guard Room Type',
            'slug' => 'state-guard-room-type',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $this->room = Room::query()->create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'SG-101',
            'slug' => 'sg-101',
            'floor' => 1,
            'area_m2' => '25.00',
            'base_price' => '3000000.00',
            'max_occupants' => 3,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $this->tenant = Tenant::query()->create([
            'building_id' => $this->building->id,
            'created_by' => $this->admin->id,
            'full_name' => 'Tenant State Guard',
            'gender' => Tenant::GENDER_MALE,
            'date_of_birth' => '2000-01-01',
            'phone' => '0919000001',
            'email' => 'tenant-state-guard@stayhub.local',
            'username' => 'tenant_state_guard',
            'password' => bcrypt('password'),
            'status' => Tenant::STATUS_RENTING,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '079200000001',
        ]);

        $this->electricService = Service::query()->create([
            'name' => 'Điện State Guard',
            'slug' => 'dien-state-guard',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'kWh',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_building_update_and_status_reject_maintenance_when_room_has_reserved_contract(): void
    {
        $this->createContract(Contract::STATUS_PENDING_SIGN);

        $payload = $this->buildingPayload(['status' => Building::STATUS_MAINTENANCE]);

        $updateResponse = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building->id}", $payload);

        $updateResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $statusResponse = $this->actingAs($this->admin, 'admin')
            ->patchJson("/api/v1/admin/buildings/{$this->building->id}/status", [
                'status' => Building::STATUS_INACTIVE,
            ]);

        $statusResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->assertSame(Building::STATUS_ACTIVE, (int) $this->building->fresh()->status);
    }

    public function test_available_rooms_excludes_rooms_inside_inactive_building(): void
    {
        $this->building->update(['status' => Building::STATUS_INACTIVE]);

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson("/api/v1/admin/contracts/available-rooms?building_id={$this->building->id}");

        $response->assertOk()
            ->assertJsonPath('status', true);

        $roomIds = collect($response->json('result'))->pluck('id')->all();
        $this->assertNotContains($this->room->id, $roomIds);
    }

    public function test_contract_creation_rejects_inactive_building_even_if_room_is_active(): void
    {
        $this->building->update(['status' => Building::STATUS_MAINTENANCE]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/contracts', $this->contractPayload());

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
        $this->assertStringContainsString('tòa nhà', (string) $response->json('message'));

        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_tenant_create_rejects_inactive_building(): void
    {
        $this->building->update(['status' => Building::STATUS_MAINTENANCE]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/tenants', $this->tenantPayload([
                'phone' => '0919000002',
                'email' => 'tenant-inactive-building@stayhub.local',
                'username' => 'tenant_inactive_bld',
                'identity_number' => '079200000002',
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
        $this->assertStringContainsString('tòa nhà', (string) $response->json('message'));

        $this->assertDatabaseMissing('tenants', ['username' => 'tenant_inactive_bld']);
    }

    public function test_tenant_update_and_status_reject_stopping_active_contract_tenant(): void
    {
        $contract = $this->createContract(Contract::STATUS_ACTIVE);
        ContractTenant::query()->create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->admin->id,
        ]);

        $updateResponse = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/tenants/{$this->tenant->id}", $this->tenantPayload([
                'status' => Tenant::STATUS_STOPPED_RENTING,
            ]));

        $updateResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $statusResponse = $this->actingAs($this->admin, 'admin')
            ->patchJson("/api/v1/admin/tenants/{$this->tenant->id}/status", [
                'status' => Tenant::STATUS_STOPPED_RENTING,
            ]);

        $statusResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->assertSame(Tenant::STATUS_RENTING, (int) $this->tenant->fresh()->status);
    }

    public function test_tenant_gender_update_uses_active_contract_building_policy_not_tenant_building_id(): void
    {
        $this->building->update(['gender_policy' => Building::GENDER_POLICY_MALE]);
        $otherBuilding = $this->createBuilding('Other Tenant Building', 'other-tenant-building', Building::GENDER_POLICY_MIXED);
        $this->tenant->update(['building_id' => $otherBuilding->id]);

        $contract = $this->createContract(Contract::STATUS_ACTIVE);
        ContractTenant::query()->create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/tenants/{$this->tenant->id}", $this->tenantPayload([
                'building_id' => $otherBuilding->id,
                'gender' => Tenant::GENDER_FEMALE,
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->assertSame(Tenant::GENDER_MALE, (int) $this->tenant->fresh()->gender);
    }

    public function test_vehicle_update_and_status_reject_mutating_vehicle_attached_to_active_contract(): void
    {
        $contract = $this->createContract(Contract::STATUS_ACTIVE);
        $vehicle = Vehicle::query()->create([
            'tenant_id' => $this->tenant->id,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
            'license_plate' => '59-SG1',
            'brand' => 'Honda',
            'color' => 'Đen',
            'is_active' => true,
        ]);
        ContractVehicle::query()->create([
            'contract_id' => $contract->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'monthly_fee' => '100000.00',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'is_active' => true,
        ]);

        $updateResponse = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/vehicles/{$vehicle->id}", [
                'brand' => 'Yamaha',
                'is_active' => true,
            ]);

        $updateResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $statusResponse = $this->actingAs($this->admin, 'admin')
            ->patchJson("/api/v1/admin/vehicles/{$vehicle->id}/status", [
                'status' => false,
            ]);

        $statusResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->assertSame('Honda', $vehicle->fresh()->brand);
        $this->assertTrue((bool) $vehicle->fresh()->is_active);
    }

    public function test_meter_creation_rejects_inactive_room_or_building_or_service(): void
    {
        $this->room->update(['status' => Room::STATUS_MAINTENANCE]);

        $roomResponse = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/meter-devices', $this->meterPayload());

        $roomResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->room->update(['status' => Room::STATUS_ACTIVE]);
        $this->building->update(['status' => Building::STATUS_INACTIVE]);

        $buildingResponse = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/meter-devices', $this->meterPayload(['meter_code' => 'MTR-BUILDING-INACTIVE']));

        $buildingResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->building->update(['status' => Building::STATUS_ACTIVE]);
        $this->electricService->update(['is_active' => false]);

        $serviceResponse = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/meter-devices', $this->meterPayload(['meter_code' => 'MTR-SERVICE-INACTIVE']));

        $serviceResponse->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_meter_creation_allows_new_meter_when_existing_meter_is_inactive_or_broken(): void
    {
        MeterDevice::query()->create([
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'meter_code' => 'MTR-OLD-INACTIVE',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => '0.00',
            'status' => MeterDevice::STATUS_INACTIVE,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/meter-devices', $this->meterPayload());

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('meter_devices', ['meter_code' => 'MTR-NEW-ACTIVE']);
    }

    public function test_meter_reading_rejects_inactive_meter_or_invoiced_existing_reading(): void
    {
        $meter = MeterDevice::query()->create([
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'meter_code' => 'MTR-READING',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => '100.00',
            'status' => MeterDevice::STATUS_ACTIVE,
        ]);

        MeterReading::query()->create([
            'meter_device_id' => $meter->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'previous_reading' => '100.00',
            'current_reading' => '150.00',
            'consumption' => '50.00',
            'reading_date' => '2026-07-31',
            'status' => MeterReading::STATUS_INVOICED,
            'created_by' => $this->admin->id,
        ]);

        $invoicedResponse = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/meter-readings', [
                'meter_device_id' => $meter->id,
                'billing_month' => 7,
                'billing_year' => 2026,
                'current_reading' => '160.00',
                'reading_date' => '2026-07-31',
            ]);

        $invoicedResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $meter->update(['status' => MeterDevice::STATUS_INACTIVE]);

        $inactiveResponse = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/meter-readings', [
                'meter_device_id' => $meter->id,
                'billing_month' => 8,
                'billing_year' => 2026,
                'current_reading' => '170.00',
                'reading_date' => '2026-08-31',
            ]);

        $inactiveResponse->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_room_service_price_update_rejects_inactive_room_or_building(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11 10:00:00'));

        $internet = Service::query()->create([
            'name' => 'Internet State Guard',
            'slug' => 'internet-state-guard',
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        RoomService::query()->create([
            'room_id' => $this->room->id,
            'service_id' => $internet->id,
            'price' => '100000.00',
            'is_active' => true,
        ]);

        $this->room->update(['status' => Room::STATUS_MAINTENANCE]);

        $roomResponse = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    ['room_service_id' => $this->room->roomServices()->where('service_id', $internet->id)->first()->id, 'price' => '120000.00'],
                ],
            ]);

        $roomResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->room->update(['status' => Room::STATUS_ACTIVE]);
        $this->building->update(['status' => Building::STATUS_INACTIVE]);

        $buildingResponse = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    ['room_service_id' => $this->room->roomServices()->where('service_id', $internet->id)->first()->id, 'price' => '130000.00'],
                ],
            ]);

        $buildingResponse->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_room_service_price_update_allows_inactive_building_when_reserved_contract_covers_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11 10:00:00'));

        $internet = Service::query()->create([
            'name' => 'Internet Running Contract',
            'slug' => 'internet-running-contract',
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $roomService = RoomService::query()->create([
            'room_id' => $this->room->id,
            'service_id' => $internet->id,
            'price' => '100000.00',
            'is_active' => true,
        ]);

        $this->createContract(Contract::STATUS_ACTIVE)->update(['end_date' => '2026-08-31']);
        $this->building->update(['status' => Building::STATUS_INACTIVE]);

        $response = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    ['room_service_id' => $roomService->id, 'price' => '120000.00'],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        Carbon::setTestNow();
    }

    public function test_contract_update_rejects_active_room_inside_inactive_building(): void
    {
        $contract = $this->createContract(Contract::STATUS_ACTIVE);
        ContractTenant::query()->create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->admin->id,
        ]);
        $this->building->update(['status' => Building::STATUS_INACTIVE]);

        $response = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/contracts/{$contract->id}", [
                'room_id' => $this->room->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'room_price' => '3200000.00',
                'deposit_amount' => '3500000.00',
                'status' => Contract::STATUS_ACTIVE,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    private function buildingPayload(array $overrides = []): array
    {
        return [
            'region_id' => $this->building->region_id,
            'manager_admin_id' => $this->building->manager_admin_id,
            'name' => $this->building->name,
            'address' => $this->building->address,
            'total_floors' => $this->building->total_floors,
            'gender_policy' => $this->building->gender_policy,
            'description' => $this->building->description,
            'status' => $this->building->status,
            ...$overrides,
        ];
    }

    private function tenantPayload(array $overrides = []): array
    {
        return [
            'building_id' => $this->building->id,
            'full_name' => 'Tenant State Guard',
            'gender' => Tenant::GENDER_MALE,
            'date_of_birth' => '2000-01-01',
            'phone' => $this->tenant->phone,
            'email' => $this->tenant->email,
            'username' => $this->tenant->username,
            'permanent_address' => 'Địa chỉ thường trú',
            'current_address' => 'Địa chỉ hiện tại',
            'status' => Tenant::STATUS_RENTING,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => $this->tenant->identity_number,
            ...$overrides,
        ];
    }

    private function contractPayload(array $overrides = []): array
    {
        return [
            'room_id' => $this->room->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'room_price' => '3000000.00',
            'deposit_amount' => '3500000.00',
            'status' => Contract::STATUS_ACTIVE,
            'contract_code' => 'HD-SHOULD-NOT-CREATE',
            'tenants' => [
                [
                    'tenant_id' => $this->tenant->id,
                    'join_date' => '2026-08-01',
                    'is_staying' => true,
                ],
            ],
            ...$overrides,
        ];
    }

    private function meterPayload(array $overrides = []): array
    {
        return [
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'meter_code' => 'MTR-NEW-ACTIVE',
            'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
            'initial_reading' => '0.00',
            'status' => MeterDevice::STATUS_ACTIVE,
            ...$overrides,
        ];
    }

    private function createContract(int $status): Contract
    {
        return Contract::query()->create([
            'contract_code' => 'HD-STATE-GUARD-' . $status . '-' . Contract::query()->count(),
            'room_id' => $this->room->id,
            'representative_tenant_id' => $this->tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'room_price' => '3000000.00',
            'deposit_amount' => '3500000.00',
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    private function createBuilding(string $name, string $slug, int $genderPolicy = Building::GENDER_POLICY_MIXED): Building
    {
        return Building::query()->create([
            'region_id' => $this->building->region_id,
            'manager_admin_id' => $this->admin->id,
            'name' => $name,
            'slug' => $slug,
            'address' => 'Other address',
            'total_floors' => 5,
            'gender_policy' => $genderPolicy,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }
}
