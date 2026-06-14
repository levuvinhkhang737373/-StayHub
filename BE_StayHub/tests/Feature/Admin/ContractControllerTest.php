<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'billing_cycle_day' => 5,
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
                ]
            ],
            'vehicles' => [
                [
                    'vehicle_id' => $this->vehicle->id,
                    'started_at' => '2026-06-01',
                    'charge_policy' => 1,
                    'monthly_fee' => '100000.00',
                    'is_active' => true,
                ]
            ]
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/admin/contracts', $payload);

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

        $contractVehicle = \App\Models\ContractVehicle::where([
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
            'billing_cycle_day' => 5,
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-06-01',
                    'leave_date' => '2026-07-01',
                    'is_staying' => false,
                ]
            ],
            'vehicles' => [
                [
                    'vehicle_id' => $this->vehicle->id,
                    'started_at' => '2026-06-01',
                    'ended_at' => '2026-07-01',
                    'charge_policy' => 1,
                    'monthly_fee' => '100000.00',
                    'is_active' => false,
                ]
            ]
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/admin/contracts', $payload);

        $response->assertStatus(201);
        $data = $response->json('result');

        $contractTenant = \App\Models\ContractTenant::where([
            'contract_id' => $data['id'],
            'tenant_id' => $this->tenant1->id,
        ])->first();

        $this->assertNotNull($contractTenant);
        $this->assertNull($contractTenant->leave_date);
        $this->assertNull($contractTenant->billing_end_date);
        $this->assertTrue((bool)$contractTenant->is_staying);

        $contractVehicle = \App\Models\ContractVehicle::where([
            'contract_id' => $data['id'],
            'vehicle_id' => $this->vehicle->id,
        ])->first();

        $this->assertNotNull($contractVehicle);
        $this->assertNull($contractVehicle->ended_at);
        $this->assertNull($contractVehicle->billing_end_date);
        $this->assertTrue((bool)$contractVehicle->is_active);
    }

    public function test_room_with_active_contract_is_not_available_for_new_contracts()
    {
        // 1. Create first contract for the room (max_occupants = 5)
        $contract1 = Contract::create([
            'contract_code' => 'HD-TEST-COLIV-1',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
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
            ->getJson("/api/admin/contracts/available-rooms?building_id={$this->building->id}");

        $response->assertStatus(200);
        $rooms = $response->json('result');
        $roomIds = collect($rooms)->pluck('id')->all();
        $this->assertNotContains($this->room->id, $roomIds);

        // 3. Create second contract for the same room (with tenant2) - should fail
        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
            'room_price' => '3500000.00',
            'deposit_amount' => '2000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant2->id,
                    'join_date' => '2026-06-01',
                    'is_staying' => true,
                ]
            ]
        ];

        $createResponse = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/admin/contracts', $payload);

        $createResponse->assertStatus(422);
        $createResponse->assertJsonPath('message', 'Phòng này đã có hợp đồng đang hiệu lực, không thể tạo thêm hợp đồng mới.');
    }

    public function test_create_contract_violates_room_capacity()
    {
        $this->room->update(['max_occupants' => 1]);

        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
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
                ]
            ]
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/admin/contracts', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'Số khách thuê đang ở vượt quá sức chứa tối đa của phòng.');
    }

    public function test_liquidate_contract_syncs_tenant_leave_and_vehicle_ended_dates()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
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
            ->patchJson("/api/admin/contracts/{$contract->id}/status", [
                'status' => Contract::STATUS_LIQUIDATED,
                'actual_end_date' => '2026-10-15',
                'note' => 'Liquidation test',
            ]);

        $response->assertStatus(200);

        $updatedContract = Contract::find($contract->id);
        $this->assertEquals(Contract::STATUS_LIQUIDATED, $updatedContract->status);
        $this->assertEquals('2026-10-15', $updatedContract->actual_end_date->toDateString());

        $contractTenant = \App\Models\ContractTenant::where([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant1->id,
        ])->first();
        $this->assertNotNull($contractTenant);
        $this->assertEquals('2026-10-15', $contractTenant->leave_date->toDateString());
        $this->assertEquals('2026-10-15', $contractTenant->billing_end_date->toDateString());
        $this->assertFalse((bool) $contractTenant->is_staying);

        $contractVehicle = \App\Models\ContractVehicle::where([
            'contract_id' => $contract->id,
            'vehicle_id' => $this->vehicle->id,
        ])->first();
        $this->assertNotNull($contractVehicle);
        $this->assertEquals('2026-10-15', $contractVehicle->ended_at->toDateString());
        $this->assertEquals('2026-10-15', $contractVehicle->billing_end_date->toDateString());
        $this->assertFalse((bool) $contractVehicle->is_active);
    }

    public function test_renew_contract_transfers_deposit_and_closes_old_contract()
    {
        $oldContract = Contract::create([
            'contract_code' => 'HD-OLD',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'billing_cycle_day' => 5,
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
            'billing_cycle_day' => 5,
            'room_price' => '3600000.00',
            'deposit_amount' => '4500000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $this->tenant1->id,
                    'join_date' => '2026-07-01',
                    'is_staying' => true,
                ]
            ]
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/admin/contracts/{$oldContract->id}/renew", $payload);

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

    public function test_add_deposit_transaction_successfully()
    {
        $contract = Contract::create([
            'contract_code' => 'HD-TEST-DEP',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
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
            ->postJson("/api/admin/contracts/{$contract->id}/deposit-transactions", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => '1000000.00',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'note' => 'Đóng thêm cọc',
        ]);
    }

    public function test_expired_contract_notification_triggers_for_active_tenants()
    {
        \Illuminate\Support\Facades\Event::fake([
            \App\Events\NotificationSent::class
        ]);

        $contract = Contract::create([
            'contract_code' => 'HD-TEST-EXPIRED-NOTIF',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
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
            'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
            'room_id' => $this->room->id,
            'title' => 'Hợp đồng hết hạn',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'tenant_id' => $this->tenant2->id,
            'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng hết hạn',
        ]);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant1->id &&
                   $event->notification->title === 'Hợp đồng hết hạn';
        });
    }

    public function test_create_contract_sends_notification_to_tenants()
    {
        \Illuminate\Support\Facades\Event::fake([
            \App\Events\NotificationSent::class
        ]);

        $payload = [
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
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
                ]
            ]
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/admin/contracts', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant1->id,
            'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng mới được tạo',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant2->id,
            'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng mới được tạo',
        ]);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant1->id &&
                   $event->notification->title === 'Hợp đồng mới được tạo';
        });

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant2->id &&
                   $event->notification->title === 'Hợp đồng mới được tạo';
        });
    }

    public function test_renew_contract_sends_notification_to_tenants()
    {
        \Illuminate\Support\Facades\Event::fake([
            \App\Events\NotificationSent::class
        ]);

        $oldContract = Contract::create([
            'contract_code' => 'HD-OLD',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
            'billing_cycle_day' => 5,
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
            'billing_cycle_day' => 5,
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
                ]
            ]
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/admin/contracts/{$oldContract->id}/renew", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant1->id,
            'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng mới được tạo',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant2->id,
            'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
            'title' => 'Hợp đồng mới được tạo',
        ]);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant1->id &&
                   $event->notification->title === 'Hợp đồng mới được tạo';
        });

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant2->id &&
                   $event->notification->title === 'Hợp đồng mới được tạo';
        });
    }
}

