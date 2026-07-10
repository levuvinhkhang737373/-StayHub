<?php

namespace Tests\Feature\Tenant;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Invoice;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\RoomService;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantContractSigningTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Building $building;
    private Room $room;
    private Tenant $tenant;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create Admin
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

        // Create Region
        $region = Region::create([
            'name' => 'Region Test',
            'code' => 'REG_TEST',
            'created_by' => $this->superAdmin->id,
        ]);

        // Create Building
        $this->building = Building::create([
            'name' => 'Building A',
            'slug' => 'building-a',
            'address' => '123 Test St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        // Create Room Type
        $roomType = RoomType::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create Room
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

        // Create Tenant
        $this->tenant = Tenant::create([
            'username' => 'tenant_test',
            'full_name' => 'Tenant Test Name',
            'email' => 'tenant@stayhub.local',
            'phone' => '0911111111',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789012',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        // Create Contract in draft/pending status
        $this->contract = Contract::create([
            'contract_code' => 'HD-PENDING-SIGN',
            'room_id' => $this->room->id,
            'tenant_name' => $this->tenant->full_name,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_PENDING_SIGN,
            'tenant_signature_url' => 'signatures/placeholder.png',
            'created_by' => $this->superAdmin->id,
        ]);

        // Add Contract Tenant
        ContractTenant::create([
            'contract_id' => $this->contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-06-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_tenant_can_successfully_sign_contract_and_update_profile()
    {
        $signatureFile = UploadedFile::fake()->image('signature.png', 100, 100);

        $payload = [
            'full_name' => 'Tenant Test Updated Name',
            'identity_number' => '987654321012',
            'identity_type' => 1, // CCCD
            'identity_date' => '2022-03-15',
            'identity_place' => 'Cuc Canh sat QLHC',
            'permanent_address' => '456 Alternate St, Hanoi',
            'signature_file' => $signatureFile,
        ];

        $response = $this->actingAs($this->tenant, 'tenant')
            ->postJson("/api/v1/tenant/contracts/{$this->contract->id}/sign", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Ký hợp đồng thành công');

        // Verify contract was updated
        $this->contract->refresh();
        $this->assertEquals(Contract::STATUS_ACTIVE, $this->contract->status);
        $this->assertEquals($this->tenant->id, $this->contract->representative_tenant_id);
        $this->assertNotNull($this->contract->tenant_signed_at);
        $this->assertNotEquals('signatures/placeholder.png', $this->contract->tenant_signature_url);
        $this->assertFileExists(public_path($this->contract->tenant_signature_url));
        @unlink(public_path($this->contract->tenant_signature_url));

        // Verify tenant profile updated in database
        $this->tenant->refresh();
        $this->assertEquals('Tenant Test Updated Name', $this->tenant->full_name);
        $this->assertEquals('987654321012', $this->tenant->identity_number);
        $this->assertEquals('2022-03-15', $this->tenant->identity_date->toDateString());
        $this->assertEquals('Cuc Canh sat QLHC', $this->tenant->identity_place);
        $this->assertEquals('456 Alternate St, Hanoi', $this->tenant->permanent_address);

        // Verify room occupants recalculated
        $this->room->refresh();
        $this->assertEquals(1, $this->room->current_occupants);

        // Verify database notification was created for admin
        $this->assertDatabaseHas('notifications', [
            'title' => 'Hợp đồng đã được ký',
            'target_type' => \App\Models\Notification::TARGET_TYPE_ADMIN,
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_tenant_contract_services_fallback_to_building_price_when_room_price_missing(): void
    {
        $internetService = Service::create([
            'name' => 'Internet tốc độ cao khu A',
            'slug' => 'internet-toc-do-cao-khu-a',
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_active' => true,
        ]);

        RoomService::create([
            'room_id' => $this->room->id,
            'service_id' => $internetService->id,
        ]);

        ServicePrice::create([
            'service_id' => $internetService->id,
            'building_id' => $this->building->id,
            'price' => '150000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->tenant, 'tenant')
            ->getJson('/api/v1/tenant/contracts');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.0.room_services.0.price', '150000.00')
            ->assertJsonPath('result.0.room_services.0.price_source', 'building')
            ->assertJsonPath('result.0.room_services.0.price_source_label', 'Giá theo tòa nhà');
    }

    public function test_tenant_signing_transfer_contract_does_not_issue_separate_new_room_invoice(): void
    {
        $oldRoom = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->room->room_type_id,
            'room_number' => '100',
            'slug' => '100-transfer-old',
            'floor' => 1,
            'base_price' => '3000000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $oldContract = Contract::create([
            'contract_code' => 'HD-TRANSFER-OLD',
            'room_id' => $oldRoom->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'actual_end_date' => '2026-06-15',
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_LIQUIDATED,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->contract->forceFill([
            'contract_code' => 'HD-TRANSFER-NEW',
            'start_date' => '2026-06-16',
            'end_date' => '2026-12-31',
            'room_price' => '3600000.00',
            'parent_contract_id' => $oldContract->id,
        ])->save();

        ContractTenant::query()
            ->where('contract_id', $this->contract->id)
            ->where('tenant_id', $this->tenant->id)
            ->update([
                'join_date' => '2026-06-16',
                'billing_start_date' => '2026-06-16',
            ]);

        RoomMovement::create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'from_room_id' => $oldRoom->id,
            'to_room_id' => $this->room->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'movement_date' => '2026-06-16 00:00:00',
            'old_room_final_amount' => '0.00',
            'transfer_fee' => '0.00',
            'deposit_transfer_amount' => '3000000.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => '0.00',
            'created_by' => $this->superAdmin->id,
        ]);

        $signatureFile = UploadedFile::fake()->image('transfer-signature.png', 100, 100);

        $response = $this->actingAs($this->tenant, 'tenant')
            ->postJson("/api/v1/tenant/contracts/{$this->contract->id}/sign", [
                'full_name' => 'Tenant Transfer Signed',
                'identity_number' => '987654321012',
                'identity_type' => 1,
                'identity_date' => '2022-03-15',
                'identity_place' => 'Cuc Canh sat QLHC',
                'permanent_address' => '456 Alternate St, Hanoi',
                'signature_file' => $signatureFile,
            ]);

        $response->assertStatus(200);

        $this->assertSame(0, Invoice::query()->where('contract_id', $this->contract->id)->count());

        $this->assertDatabaseMissing('notifications', [
            'title' => 'Hóa đơn phòng mới đã được phát hành',
            'room_id' => $this->room->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->contract->refresh();
        $this->assertEquals($this->tenant->id, $this->contract->representative_tenant_id);
        @unlink(public_path($this->contract->tenant_signature_url));
    }

    public function test_tenant_cannot_sign_contract_if_not_draft()
    {
        // Change contract status to Active
        $this->contract->update(['status' => Contract::STATUS_ACTIVE]);

        $signatureFile = UploadedFile::fake()->image('signature.png', 100, 100);

        $payload = [
            'full_name' => 'Tenant Test Name',
            'identity_number' => '123456789012',
            'identity_type' => 1,
            'identity_date' => '2022-03-15',
            'identity_place' => 'Cuc Canh sat QLHC',
            'permanent_address' => '456 Alternate St, Hanoi',
            'signature_file' => $signatureFile,
        ];

        $response = $this->actingAs($this->tenant, 'tenant')
            ->postJson("/api/v1/tenant/contracts/{$this->contract->id}/sign", $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'Hợp đồng này không ở trạng thái chờ ký hoặc không thuộc về bạn.');
    }

    public function test_unauthorized_user_cannot_sign_contract()
    {
        $signatureFile = UploadedFile::fake()->image('signature.png', 100, 100);

        $payload = [
            'full_name' => 'Tenant Test Name',
            'identity_number' => '123456789012',
            'identity_type' => 1,
            'identity_date' => '2022-03-15',
            'identity_place' => 'Cuc Canh sat QLHC',
            'permanent_address' => '456 Alternate St, Hanoi',
            'signature_file' => $signatureFile,
        ];

        // Access without authentication
        $response = $this->postJson("/api/v1/tenant/contracts/{$this->contract->id}/sign", $payload);
        $response->assertStatus(401);
    }

    public function test_tenant_can_update_profile_successfully()
    {
        $payload = [
            'full_name' => 'Updated Tenant Name',
            'identity_number' => '888888888888',
            'identity_type' => 1, // CCCD
            'identity_date' => '2023-01-01',
            'identity_place' => 'Cong an TP.HCM',
            'permanent_address' => '789 District 1, TP.HCM',
        ];

        $response = $this->actingAs($this->tenant, 'tenant')
            ->patchJson("/api/v1/tenant/profile", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Cập nhật thông tin thành công');

        // Verify database state
        $this->tenant->refresh();
        $this->assertEquals('Updated Tenant Name', $this->tenant->full_name);
        $this->assertEquals('888888888888', $this->tenant->identity_number);
        $this->assertEquals('2023-01-01', $this->tenant->identity_date->toDateString());
        $this->assertEquals('Cong an TP.HCM', $this->tenant->identity_place);
        $this->assertEquals('789 District 1, TP.HCM', $this->tenant->permanent_address);
    }

    public function test_tenant_profile_update_requires_fields()
    {
        $payload = [
            'full_name' => '', // blank
            'identity_number' => '', // blank
        ];

        $response = $this->actingAs($this->tenant, 'tenant')
            ->patchJson("/api/v1/tenant/profile", $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
    }
}
