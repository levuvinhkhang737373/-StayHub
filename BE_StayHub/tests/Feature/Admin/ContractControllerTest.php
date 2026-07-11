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
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ContractControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    private Building $building;

    private Room $room;

    private Tenant $tenant1;

    private Tenant $tenant2;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Super Admin
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

        // 2. Create Region
        $region = Region::create([
            'name' => 'Region Test',
            'code' => 'REG_TEST',
            'created_by' => $this->superAdmin->id,
        ]);

        // 3. Create Building
        $this->building = Building::create([
            'name' => 'Building A',
            'slug' => 'building-a',
            'address' => '123 Test St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        // 4. Create Room Type
        $roomType = RoomType::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // 5. Create Room (max occupants = 5)
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

        // 6. Create Tenants
        $this->tenant1 = Tenant::create([
            'username' => 'tenant1',
            'full_name' => 'Tenant One',
            'email' => 'tenant1@stayhub.local',
            'phone' => '0911111111',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789012',
            'identity_date' => '2020-01-01',
            'identity_place' => 'Cục Cảnh sát quản lý hành chính về trật tự xã hội',
            'permanent_address' => '123 Test Permanent Address',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        $this->tenant2 = Tenant::create([
            'username' => 'tenant2',
            'full_name' => 'Tenant Two',
            'email' => 'tenant2@stayhub.local',
            'phone' => '0922222222',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_FEMALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789013',
            'identity_date' => '2020-01-01',
            'identity_place' => 'Cục Cảnh sát quản lý hành chính về trật tự xã hội',
            'permanent_address' => '456 Test Permanent Address',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        // 7. Create Vehicle for Tenant 1
        $this->vehicle = Vehicle::create([
            'tenant_id' => $this->tenant1->id,
            'vehicle_type' => 1,
            'license_plate' => '29-A1 123.45',
            'brand' => 'Honda',
            'color' => 'Black',
            'is_active' => true,
        ]);
    }

    public function test_create_contract_successfully_generates_code_and_deposit_transaction()
    {
        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'is_deposit_paid' => true,
            'deposit_payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
                [
                    'tenant_id' => $this->tenant2->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
            ],
            'vehicles' => [
                [
                    'vehicle_id' => $this->vehicle->id,
                    'started_at' => '2026-06-01',
                    'charge_policy' => 1,
                    'monthly_fee' => '100000.00',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $payload);

        $response->assertStatus(201);
        $data = $response->json('result');

        $this->assertDatabaseHas('contracts', [
            'id' => $data['id'],
            'room_id' => $this->room->id,
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
        ]);

        $this->assertEquals('HD-BUILDING-A-101', $data['contract_code']);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $data['id'],
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '4000000.00',
        ]);

        $contractVehicle = ContractVehicle::where([
            'contract_id' => $data['id'],
            'vehicle_id' => $this->vehicle->id,
        ])->first();
        $this->assertNotNull($contractVehicle);
        $this->assertEquals('2026-06-01', $contractVehicle->started_at->toDateString());
    }

    public function test_create_contract_ignores_leave_and_end_dates_for_tenants_and_vehicles()
    {
        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-06-01',
                    'leave_date' => '2026-07-01',
                    'is_staying' => false,
                ],
            ],
            'vehicles' => [
                [
                    'vehicle_id' => $this->vehicle->id,
                    'started_at' => '2026-06-01',
                    'ended_at' => '2026-07-01',
                    'charge_policy' => 1,
                    'monthly_fee' => '100000.00',
                    'is_active' => false,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $payload);

        $response->assertStatus(201);
        $data = $response->json('result');

        $contractTenant = ContractTenant::where([
            'contract_id' => $data['id'],
            'tenant_id' => $this->tenant1->id,
        ])->first();

        $this->assertNotNull($contractTenant);
        $this->assertNull($contractTenant->leave_date);
        $this->assertNull($contractTenant->billing_end_date);
        $this->assertTrue((bool) $contractTenant->is_staying);

        $contractVehicle = ContractVehicle::where([
            'contract_id' => $data['id'],
            'vehicle_id' => $this->vehicle->id,
        ])->first();

        $this->assertNotNull($contractVehicle);
        $this->assertNull($contractVehicle->ended_at);
        $this->assertNull($contractVehicle->billing_end_date);
        $this->assertTrue((bool) $contractVehicle->is_active);
    }

    public function test_create_contract_cannot_start_with_expired_status()
    {
        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_EXPIRED,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Khi tạo hợp đồng chỉ được chọn Chờ ký hoặc Đang hiệu lực.');
    }

    public function test_room_with_active_contract_is_not_available_for_new_contracts()
    {
        // 1. Create first contract for the room (max_occupants = 5)
        $contract1 = Contract::create([
            'contract_code' => 'HD-TEST-COLIV-1',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '2000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract1->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => '2026-06-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        // Update current occupants of room
        $this->room->update(['current_occupants' => 1]);

        // 2. Query available rooms - should NOT contain this room
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/contracts/available-rooms?building_id={$this->building->id}");

        $response->assertStatus(200);
        $rooms = $response->json('result');
        $roomIds = collect($rooms)->pluck('id')->all();
        $this->assertNotContains($this->room->id, $roomIds);

        // 3. Create second contract for the same room (with tenant2) - should fail
        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant2->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
            ],
        ];

        $createResponse = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $payload);

        $createResponse->assertStatus(422);
        $createResponse->assertJsonPath('message', 'Phòng này đã có hợp đồng chờ ký hoặc đang hiệu lực, không thể tạo thêm hợp đồng mới.');
    }

    public function test_room_tenant_and_vehicle_with_pending_contract_are_reserved_for_new_contracts()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-PENDING-ROOM',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '2000000.00',
            'status' => Contract::STATUS_PENDING_SIGN,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => '2026-06-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractVehicle::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $this->vehicle->id,
            'started_at' => '2026-06-01',
            'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
            'monthly_fee' => '100000.00',
            'is_active' => true,
        ]);

        $roomsResponse = $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/contracts/available-rooms?building_id={$this->building->id}");

        $roomsResponse->assertStatus(200);
        $this->assertNotContains($this->room->id, collect($roomsResponse->json('result'))->pluck('id')->all());

        $tenantsResponse = $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/tenants?building_id={$this->building->id}&without_reserved_contract=1&per_page=100");

        $tenantsResponse->assertStatus(200);
        $this->assertNotContains($this->tenant1->id, collect($tenantsResponse->json('result.data'))->pluck('id')->all());

        $vehiclesResponse = $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/vehicles?tenant_id={$this->tenant1->id}&is_active=1&without_reserved_contract=1&per_page=100");

        $vehiclesResponse->assertStatus(200);
        $this->assertNotContains($this->vehicle->id, collect($vehiclesResponse->json('result.data'))->pluck('id')->all());

        $createResponse = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', [
                'room_id' => $this->room->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-12-01',
                'room_price' => '3500000.00',
                'deposit_amount' => '4000000.00',
                'status' => Contract::STATUS_PENDING_SIGN,
                'tenants' => [
                    [
                        'tenant_id' => $this->tenant2->id,
                        'join_date' => '2026-06-01',
                        'is_staying' => true,
                    ],
                ],
            ]);

        $createResponse->assertStatus(422);
        $createResponse->assertJsonPath('message', 'Phòng này đã có hợp đồng chờ ký hoặc đang hiệu lực, không thể tạo thêm hợp đồng mới.');
    }

    public function test_create_contract_violates_room_capacity()
    {
        $this->room->update(['max_occupants' => 1]);

        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
                [
                    'tenant_id' => $this->tenant2->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'Số khách thuê đang ở vượt quá sức chứa tối đa của phòng.');
    }

    public function test_terminate_contract_syncs_tenant_leave_and_vehicle_ended_dates()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $contractTenant = ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => '2026-06-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $contractVehicle = ContractVehicle::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $this->vehicle->id,
            'started_at' => '2026-06-01',
            'monthly_fee' => '100000.00',
            'charge_policy' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$contract->id}/terminate", [
                'actual_end_date' => now()->toDateString(),
                'deduction_amount' => '0.00',
                'note' => 'Liquidation test',
            ]);

        $response->assertStatus(200);

        $updatedContract = Contract::find($contract->id);
        $this->assertEquals(Contract::STATUS_LIQUIDATED, $updatedContract->status);
        $this->assertEquals(now()->toDateString(), $updatedContract->actual_end_date->toDateString());

        $contractTenant = ContractTenant::where([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant1->id,
        ])->first();
        $this->assertNotNull($contractTenant);
        $this->assertEquals(now()->toDateString(), $contractTenant->leave_date->toDateString());
        $this->assertEquals(now()->toDateString(), $contractTenant->billing_end_date->toDateString());
        $this->assertFalse((bool) $contractTenant->is_staying);

        $contractVehicle = ContractVehicle::where([
            'contract_id' => $contract->id,
            'vehicle_id' => $this->vehicle->id,
        ])->first();
        $this->assertNotNull($contractVehicle);
        $this->assertEquals(now()->toDateString(), $contractVehicle->ended_at->toDateString());
        $this->assertEquals(now()->toDateString(), $contractVehicle->billing_end_date->toDateString());
        $this->assertFalse((bool) $contractVehicle->is_active);
    }

    public function test_active_contract_cannot_be_manually_changed_status()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-NO-MANUAL-EXPIRED',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/api/v1/admin/contracts/{$contract->id}/status", [
                'status' => Contract::STATUS_CANCELLED,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ được đổi trạng thái thủ công với hợp đồng chờ ký. Hợp đồng đang hiệu lực cần thanh lý bằng chức năng riêng; hợp đồng hết hạn do hệ thống tự cập nhật theo ngày kết thúc.');

        $this->assertEquals(Contract::STATUS_ACTIVE, $contract->fresh()->status);
    }

    public function test_expired_status_is_not_allowed_in_manual_status_api()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-NO-EXPIRED-OPTION',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_PENDING_SIGN,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/api/v1/admin/contracts/{$contract->id}/status", [
                'status' => Contract::STATUS_EXPIRED,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ được kích hoạt hoặc hủy hợp đồng chờ ký. Hợp đồng hết hạn do hệ thống tự cập nhật, hợp đồng đang hiệu lực cần thanh lý bằng chức năng riêng.');

        $this->assertEquals(Contract::STATUS_PENDING_SIGN, $contract->fresh()->status);
    }

    public function test_update_contract_cannot_set_actual_end_date_directly()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-NO-ACTUAL-END-UPDATE',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/contracts/{$contract->id}", [
                'actual_end_date' => '2026-10-15',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Ngày kết thúc thực tế chỉ được cập nhật bằng chức năng thanh lý hoặc hệ thống tự xử lý hết hạn.');

        $this->assertNull($contract->fresh()->actual_end_date);
    }

    public function test_cancel_pending_contract_deactivates_rows_without_fake_end_dates()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-CANCEL-PENDING',
            'room_id' => $this->room->id,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_PENDING_SIGN,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => '2026-08-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractVehicle::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $this->vehicle->id,
            'started_at' => '2026-08-01',
            'monthly_fee' => '100000.00',
            'charge_policy' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/api/v1/admin/contracts/{$contract->id}/status", [
                'status' => Contract::STATUS_CANCELLED,
            ]);

        $response->assertStatus(200);

        $updatedContract = $contract->fresh();
        $this->assertEquals(Contract::STATUS_CANCELLED, $updatedContract->status);
        $this->assertEquals(Contract::PAYMENT_STATUS_CANCELLED, $updatedContract->payment_status);

        $contractTenant = ContractTenant::where('contract_id', $contract->id)->where('tenant_id', $this->tenant1->id)->first();
        $this->assertFalse((bool) $contractTenant->is_staying);
        $this->assertNull($contractTenant->leave_date);
        $this->assertNull($contractTenant->billing_end_date);

        $contractVehicle = ContractVehicle::where('contract_id', $contract->id)->where('vehicle_id', $this->vehicle->id)->first();
        $this->assertFalse((bool) $contractVehicle->is_active);
        $this->assertNull($contractVehicle->ended_at);
        $this->assertNull($contractVehicle->billing_end_date);
    }

    public function test_terminate_contract_settles_deposit_and_creates_checkout_movement()
    {
        $actualEndDate = now()->toDateString();
        $startDate = now()->subMonth()->toDateString();
        $endDate = now()->addMonths(5)->toDateString();

        $contract = Contract::create([
            'contract_code' => 'HD-TERMINATE',
            'room_id' => $this->room->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => $startDate,
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractVehicle::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $this->vehicle->id,
            'started_at' => $startDate,
            'monthly_fee' => '100000.00',
            'charge_policy' => 1,
            'is_active' => true,
        ]);

        $contract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '4000000.00',
            'transaction_date' => $startDate,
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'note' => 'Thu cọc ban đầu',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->room->update(['current_occupants' => 1]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$contract->id}/terminate", [
                'actual_end_date' => $actualEndDate,
                'deduction_amount' => '500000.00',
                'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                'note' => 'Khấu trừ vệ sinh cuối kỳ',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('result.settlement.deposit_balance_before', '4000000.00')
            ->assertJsonPath('result.settlement.deduction_amount', '500000.00')
            ->assertJsonPath('result.settlement.refund_amount', '3500000.00')
            ->assertJsonPath('result.settlement.deposit_balance_after', '0.00');

        $updatedContract = Contract::find($contract->id);
        $this->assertEquals(Contract::STATUS_LIQUIDATED, $updatedContract->status);
        $this->assertEquals(Contract::PAYMENT_STATUS_SUCCESS, $updatedContract->payment_status);
        $this->assertEquals($actualEndDate, $updatedContract->actual_end_date->toDateString());

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT,
            'amount' => '500000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            'amount' => '3500000.00',
        ]);

        $this->assertDatabaseHas('expenses', [
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'title' => 'Hoàn cọc HD-TERMINATE',
            'amount' => '3500000.00',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'status' => Expense::STATUS_RECORDED,
            'created_by' => $this->superAdmin->id,
        ]);

        $contractTenant = ContractTenant::where('contract_id', $contract->id)->where('tenant_id', $this->tenant1->id)->first();
        $this->assertEquals($actualEndDate, $contractTenant->leave_date->toDateString());
        $this->assertEquals($actualEndDate, $contractTenant->billing_end_date->toDateString());
        $this->assertFalse((bool) $contractTenant->is_staying);

        $contractVehicle = ContractVehicle::where('contract_id', $contract->id)->where('vehicle_id', $this->vehicle->id)->first();
        $this->assertEquals($actualEndDate, $contractVehicle->ended_at->toDateString());
        $this->assertEquals($actualEndDate, $contractVehicle->billing_end_date->toDateString());
        $this->assertFalse((bool) $contractVehicle->is_active);

        $this->assertDatabaseHas('room_movements', [
            'tenant_id' => $this->tenant1->id,
            'contract_id' => $contract->id,
            'from_room_id' => $this->room->id,
            'to_room_id' => null,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_CHECKOUT,
            'deduction_amount' => '500000.00',
            'deposit_refund_amount' => '3500000.00',
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'current_occupants' => 0,
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant1->id,
            'title' => 'Hợp đồng đã thanh lý',
        ]);
    }

    public function test_terminate_contract_allows_custom_deduction_without_final_invoice()
    {
        $actualEndDate = now()->toDateString();
        $startDate = now()->subDays(2)->toDateString();
        $endDate = now()->addMonths(6)->toDateString();

        $contract = Contract::create([
            'contract_code' => 'HD-NO-FINAL-INVOICE',
            'room_id' => $this->room->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => $startDate,
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $contract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '4000000.00',
            'transaction_date' => $startDate,
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'note' => 'Thu cọc ban đầu',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertFalse(Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('billing_month', now()->month)
            ->where('billing_year', now()->year)
            ->exists());

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$contract->id}/terminate", [
                'actual_end_date' => $actualEndDate,
                'deduction_amount' => '1234567.00',
                'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                'note' => 'Khấu trừ đền bù theo thỏa thuận',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('result.settlement.deposit_balance_before', '4000000.00')
            ->assertJsonPath('result.settlement.deduction_amount', '1234567.00')
            ->assertJsonPath('result.settlement.refund_amount', '2765433.00')
            ->assertJsonPath('result.settlement.has_final_period_invoice', false);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT,
            'amount' => '1234567.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            'amount' => '2765433.00',
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
        ]);
    }

    public function test_renew_contract_transfers_deposit_and_closes_old_contract()
    {
        $oldContract = Contract::create([
            'contract_code' => 'HD-OLD',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $oldContract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '4000000.00',
            'transaction_date' => '2026-01-01',
            'payment_method' => 1,
            'created_by' => $this->superAdmin->id,
        ]);

        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-07-01',
            'end_date' => '2027-01-01',
            'room_price' => '3600000.00',
            'deposit_amount' => '4500000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-07-01',
                    'is_staying' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$oldContract->id}/renew", $payload);

        $response->assertStatus(201);
        $data = $response->json('result');

        $updatedOldContract = Contract::find($oldContract->id);
        $this->assertEquals(Contract::STATUS_EXPIRED, $updatedOldContract->status);
        $this->assertEquals('2026-06-30', $updatedOldContract->actual_end_date->toDateString());

        $this->assertDatabaseHas('contracts', [
            'id' => $data['id'],
            'parent_contract_id' => $oldContract->id,
            'renew_from_contract_id' => $oldContract->id,
            'status' => Contract::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $oldContract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT,
            'amount' => '4000000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $data['id'],
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER,
            'amount' => '4000000.00',
        ]);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $data['id'],
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '500000.00',
        ]);
    }

    public function test_add_tenant_blocks_tenant_with_pending_room_transfer()
    {
        $sourceContract = Contract::create([
            'contract_code' => 'HD-SOURCE-TRANSFER',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $sourceContract->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => '2026-06-01',
            'billing_start_date' => '2026-06-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $targetRoom = Room::create([
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

        $targetContract = Contract::create([
            'contract_code' => 'HD-TARGET-TRANSFER',
            'room_id' => $targetRoom->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        RoomMovement::create([
            'transfer_code' => 'TRF-2026-06-0001',
            'tenant_id' => $this->tenant1->id,
            'contract_id' => $sourceContract->id,
            'source_contract_id' => $sourceContract->id,
            'destination_contract_id' => null,
            'from_room_id' => $this->room->id,
            'to_room_id' => $targetRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_PENDING,
            'movement_date' => '2026-06-15 00:00:00',
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
            'settlement_payment_references' => [],
            'scheduled_payload' => [],
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$targetContract->id}/tenants", [
                'tenant_id' => $this->tenant1->id,
                'join_date' => '2026-06-16',
                'billing_start_date' => '2026-06-16',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Khách thuê đang có lịch chuyển phòng chưa hoàn tất, không thể thêm vào hợp đồng khác.');

        $this->assertDatabaseMissing('contract_tenants', [
            'contract_id' => $targetContract->id,
            'tenant_id' => $this->tenant1->id,
        ]);
    }

    public function test_add_deposit_transaction_successfully()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-TEST-DEP',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $contract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '2000000.00',
            'transaction_date' => '2026-06-01',
            'payment_method' => 1,
            'created_by' => $this->superAdmin->id,
        ]);

        $payload = [
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '1000000.00',
            'transaction_date' => '2026-06-02',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'note' => 'Đóng thêm cọc',
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$contract->id}/deposit-transactions", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '1000000.00',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'note' => 'Đóng thêm cọc',
        ]);
    }

    public function test_add_refund_deposit_transaction_creates_expense()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-TEST-REFUND',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $contract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '2000000.00',
            'transaction_date' => '2026-06-01',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_CASH,
            'created_by' => $this->superAdmin->id,
        ]);

        $payload = [
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            'amount' => '500000.00',
            'transaction_date' => '2026-06-03',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'note' => 'Hoàn trả một phần',
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$contract->id}/deposit-transactions", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            'amount' => '500000.00',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'note' => 'Hoàn trả một phần',
        ]);

        $this->assertDatabaseHas('expenses', [
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'title' => 'Hoàn cọc HD-TEST-REFUND',
            'amount' => '500000.00',
            'payment_method' => Expense::PAYMENT_METHOD_BANK_TRANSFER,
            'status' => Expense::STATUS_RECORDED,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_expired_contract_notification_triggers_for_active_tenants()
    {
        Event::fake([
            NotificationSent::class,
        ]);

        $contract = Contract::create([
            'contract_code' => 'HD-TEST-EXPIRED-NOTIF',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => '2026-06-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant2->id,
            'join_date' => '2026-06-01',
            'is_staying' => false,
            'created_by' => $this->superAdmin->id,
        ]);

        $contract->status = Contract::STATUS_EXPIRED;
        $contract->save();

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant1->id,
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'room_id' => $this->room->id,
            'title' => 'Hợp đồng hết hạn',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'tenant_id' => $this->tenant2->id,
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng hết hạn',
        ]);

        Event::assertDispatched(NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant1->id &&
                   $event->notification->title === 'Hợp đồng hết hạn';
        });
    }

    public function test_create_contract_sends_notification_to_tenants()
    {
        Event::fake([
            NotificationSent::class,
        ]);

        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
                [
                    'tenant_id' => $this->tenant2->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant1->id,
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng mới được tạo',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant2->id,
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng mới được tạo',
        ]);

        Event::assertDispatched(NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant1->id &&
                   $event->notification->title === 'Hợp đồng mới được tạo';
        });

        Event::assertDispatched(NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant2->id &&
                   $event->notification->title === 'Hợp đồng mới được tạo';
        });
    }

    public function test_renew_contract_sends_notification_to_tenants()
    {
        Event::fake([
            NotificationSent::class,
        ]);

        $oldContract = Contract::create([
            'contract_code' => 'HD-OLD',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $oldContract->id,
            'tenant_id' => $this->tenant1->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-02',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-06-02',
                    'is_staying' => true,
                ],
                [
                    'tenant_id' => $this->tenant2->id,
                    'join_date' => '2026-06-02',
                    'is_staying' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$oldContract->id}/renew", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant1->id,
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng mới được tạo',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant2->id,
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng mới được tạo',
        ]);

        Event::assertDispatched(NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant1->id &&
                   $event->notification->title === 'Hợp đồng mới được tạo';
        });

        Event::assertDispatched(NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant2->id &&
                   $event->notification->title === 'Hợp đồng mới được tạo';
        });
    }

    public function test_create_contract_successfully_syncs_custom_service_prices()
    {
        $service = \App\Models\Service::create([
            'name' => 'Custom Internet',
            'slug' => 'custom-internet',
            'charge_method' => 2,
            'unit_name' => 'tháng',
            'is_required' => false,
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        RoomService::create([
            'room_id' => $this->room->id,
            'service_id' => $service->id,
        ]);

        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'is_deposit_paid' => true,
            'deposit_payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ],
            ],
            'services' => [
                [
                    'service_id' => $service->id,
                    'price' => '80000.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('room_services', [
            'room_id' => $this->room->id,
            'service_id' => $service->id,
        ]);

        $roomService = RoomService::query()
            ->where('room_id', $this->room->id)
            ->where('service_id', $service->id)
            ->firstOrFail();

        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $roomService->id,
            'contract_id' => $response->json('result.id'),
            'price' => '80000.00',
            'effective_from' => '2026-06-01 00:00:00',
            'effective_to' => '2026-12-01 00:00:00',
        ]);
    }
}
