<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantGenderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Region $region;
    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = Admin::create([
            'username' => 'gender_policy_admin',
            'full_name' => 'Gender Policy Admin',
            'email' => 'gender-policy-admin@stayhub.local',
            'phone' => '0900000001',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
        ]);

        $this->region = Region::create([
            'code' => 'GENDER-POLICY',
            'name' => 'Gender Policy Region',
            'created_by' => $this->admin->id,
        ]);

        $this->roomType = RoomType::create([
            'name' => 'Standard Gender Room',
            'slug' => 'standard-gender-room',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_tenant_create_and_update_respect_building_gender_policy(): void
    {
        $femaleBuilding = $this->createBuilding('female-building', Building::GENDER_POLICY_FEMALE);
        $mixedBuilding = $this->createBuilding('mixed-building', Building::GENDER_POLICY_MIXED);

        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/tenants', $this->tenantPayload($femaleBuilding, Tenant::GENDER_MALE, 'male_blocked'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.');

        $femaleResponse = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/tenants', $this->tenantPayload($femaleBuilding, Tenant::GENDER_FEMALE, 'female_allowed'));

        $femaleResponse->assertStatus(201);
        $femaleTenantId = $femaleResponse->json('result.id');

        $this->actingAs($this->admin, 'admin')
            ->putJson("/api/v1/admin/tenants/{$femaleTenantId}", ['gender' => Tenant::GENDER_MALE])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.');

        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/tenants', $this->tenantPayload($mixedBuilding, Tenant::GENDER_MALE, 'mixed_male'))
            ->assertStatus(201);

        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/tenants', $this->tenantPayload($mixedBuilding, Tenant::GENDER_FEMALE, 'mixed_female'))
            ->assertStatus(201);
    }

    public function test_available_tenant_options_follow_building_gender_policy(): void
    {
        $maleBuilding = $this->createBuilding('male-options', Building::GENDER_POLICY_MALE);
        $femaleBuilding = $this->createBuilding('female-options', Building::GENDER_POLICY_FEMALE);
        $mixedBuilding = $this->createBuilding('mixed-options', Building::GENDER_POLICY_MIXED);

        $maleTenant = $this->createTenant('option_male', Tenant::GENDER_MALE, $maleBuilding);
        $femaleTenant = $this->createTenant('option_female', Tenant::GENDER_FEMALE, $femaleBuilding);
        $unassignedFemaleTenant = $this->createTenant('option_unassigned_female', Tenant::GENDER_FEMALE);
        $unassignedMaleTenant = $this->createTenant('option_unassigned_male', Tenant::GENDER_MALE);

        $maleResponse = $this->actingAs($this->admin, 'admin')
            ->getJson("/api/v1/admin/tenants?building_id={$maleBuilding->id}&without_active_contract=1&per_page=100");
        $maleResponse->assertStatus(200);
        $maleIds = collect($maleResponse->json('result.data'))->pluck('id')->all();
        $this->assertContains($maleTenant->id, $maleIds);
        $this->assertContains($unassignedMaleTenant->id, $maleIds);
        $this->assertNotContains($femaleTenant->id, $maleIds);
        $this->assertNotContains($unassignedFemaleTenant->id, $maleIds);

        $femaleResponse = $this->actingAs($this->admin, 'admin')
            ->getJson("/api/v1/admin/tenants?building_id={$femaleBuilding->id}&without_active_contract=1&per_page=100");
        $femaleResponse->assertStatus(200);
        $femaleIds = collect($femaleResponse->json('result.data'))->pluck('id')->all();
        $this->assertContains($femaleTenant->id, $femaleIds);
        $this->assertContains($unassignedFemaleTenant->id, $femaleIds);
        $this->assertNotContains($maleTenant->id, $femaleIds);
        $this->assertNotContains($unassignedMaleTenant->id, $femaleIds);

        $mixedResponse = $this->actingAs($this->admin, 'admin')
            ->getJson("/api/v1/admin/tenants?building_id={$mixedBuilding->id}&without_active_contract=1&per_page=100");
        $mixedResponse->assertStatus(200);
        $mixedIds = collect($mixedResponse->json('result.data'))->pluck('id')->all();
        $this->assertContains($unassignedMaleTenant->id, $mixedIds);
        $this->assertContains($unassignedFemaleTenant->id, $mixedIds);
    }

    public function test_contract_create_renew_and_activate_reject_staying_tenant_with_invalid_gender(): void
    {
        $femaleBuilding = $this->createBuilding('female-contracts', Building::GENDER_POLICY_FEMALE);
        $room = $this->createRoom($femaleBuilding, 'F101');
        $maleTenant = $this->createTenant('contract_male', Tenant::GENDER_MALE, $femaleBuilding);
        $femaleTenant = $this->createTenant('contract_female', Tenant::GENDER_FEMALE, $femaleBuilding);

        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/contracts', $this->contractPayload($room, $maleTenant, Contract::STATUS_ACTIVE))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.');

        $activeContractResponse = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/contracts', $this->contractPayload($room, $femaleTenant, Contract::STATUS_ACTIVE, '2026-01-01', '2026-12-31'));
        $activeContractResponse->assertStatus(201);

        $renewRoom = $this->createRoom($femaleBuilding, 'F102');
        $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$activeContractResponse->json('result.id')}/renew", $this->contractPayload($renewRoom, $maleTenant, Contract::STATUS_ACTIVE, '2027-01-01', '2027-12-31'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.');

        $pendingRoom = $this->createRoom($femaleBuilding, 'F103');
        $pendingContract = Contract::create([
            'contract_code' => 'HD-GENDER-PENDING',
            'room_id' => $pendingRoom->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3500000.00',
            'deposit_amount' => '1000000.00',
            'status' => Contract::STATUS_PENDING_SIGN,
            'created_by' => $this->admin->id,
        ]);
        ContractTenant::create([
            'contract_id' => $pendingContract->id,
            'tenant_id' => $maleTenant->id,
            'join_date' => '2026-02-01',
            'is_staying' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patchJson("/api/v1/admin/contracts/{$pendingContract->id}/status", ['status' => Contract::STATUS_ACTIVE])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.');
    }

    public function test_room_transfer_respects_destination_building_gender_policy(): void
    {
        $sourceBuilding = $this->createBuilding('source-mixed', Building::GENDER_POLICY_MIXED);
        $femaleBuilding = $this->createBuilding('transfer-female', Building::GENDER_POLICY_FEMALE);
        $mixedBuilding = $this->createBuilding('transfer-mixed', Building::GENDER_POLICY_MIXED);
        $sourceRoom = $this->createRoom($sourceBuilding, 'S101', 1);
        $femaleRoom = $this->createRoom($femaleBuilding, 'TF101');
        $mixedRoom = $this->createRoom($mixedBuilding, 'TM101');
        $maleTenant = $this->createTenant('transfer_male', Tenant::GENDER_MALE, $sourceBuilding);
        $contract = $this->createActiveContract($sourceRoom, $maleTenant);
        $movementDate = now('Asia/Ho_Chi_Minh')->addMonthNoOverflow()->startOfMonth()->toDateString();

        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/room-transfers/tenant', [
                'tenant_id' => $maleTenant->id,
                'to_room_id' => $femaleRoom->id,
                'movement_date' => $movementDate,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.');

        $this->assertDatabaseHas('contract_tenants', [
            'contract_id' => $contract->id,
            'tenant_id' => $maleTenant->id,
            'is_staying' => true,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/admin/room-transfers/tenant', [
                'tenant_id' => $maleTenant->id,
                'to_room_id' => $mixedRoom->id,
                'movement_date' => $movementDate,
            ])
            ->assertStatus(201);
    }

    private function createBuilding(string $slug, int $genderPolicy): Building
    {
        return Building::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'address' => '123 Gender Policy Street',
            'region_id' => $this->region->id,
            'manager_admin_id' => $this->admin->id,
            'gender_policy' => $genderPolicy,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }

    private function createRoom(Building $building, string $roomNumber, int $currentOccupants = 0): Room
    {
        return Room::create([
            'building_id' => $building->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => $roomNumber,
            'slug' => strtolower($roomNumber),
            'floor' => 1,
            'base_price' => '3500000.00',
            'max_occupants' => 5,
            'current_occupants' => $currentOccupants,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }

    private function createTenant(string $suffix, int $gender, ?Building $building = null): Tenant
    {
        return Tenant::create(array_merge($this->tenantPayload($building, $gender, $suffix), [
            'password' => bcrypt('password'),
            'created_by' => $this->admin->id,
            'building_id' => $building?->id,
        ]));
    }

    private function tenantPayload(?Building $building, int $gender, string $suffix): array
    {
        $digits = substr(str_pad((string) abs(crc32($suffix)), 10, '0', STR_PAD_LEFT), 0, 10);

        return [
            'building_id' => $building?->id,
            'username' => $suffix,
            'full_name' => 'Tenant '.$suffix,
            'email' => $suffix.'@stayhub.local',
            'phone' => '09'.$digits,
            'date_of_birth' => '2000-01-01',
            'gender' => $gender,
            'status' => Tenant::STATUS_RENTING,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => substr(str_pad((string) abs(crc32('id-'.$suffix)), 12, '0', STR_PAD_LEFT), 0, 12),
            'permanent_address' => 'Gender Policy Permanent Address',
            'current_address' => 'Gender Policy Current Address',
        ];
    }

    private function contractPayload(Room $room, Tenant $tenant, int $status, string $startDate = '2026-01-01', string $endDate = '2026-12-31'): array
    {
        return [
            'room_id' => $room->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'billing_cycle_day' => 5,
            'room_price' => '3500000.00',
            'deposit_amount' => '1000000.00',
            'status' => $status,
            'tenants' => [[
                'tenant_id' => $tenant->id,
                'join_date' => $startDate,
                'is_staying' => true,
            ]],
        ];
    }

    private function createActiveContract(Room $room, Tenant $tenant): Contract
    {
        $contract = Contract::create([
            'contract_code' => 'HD-GENDER-'.strtoupper($room->room_number),
            'room_id' => $room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'billing_cycle_day' => 5,
            'room_price' => '3500000.00',
            'deposit_amount' => '1000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $tenant->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->admin->id,
        ]);

        return $contract;
    }
}
