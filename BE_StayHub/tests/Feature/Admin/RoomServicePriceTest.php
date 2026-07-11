<?php

namespace Tests\Feature\Admin;

use App\Events\NotificationSent;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\InvoiceItem;
use App\Models\Notification;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\RoomServicePrice;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoomServicePriceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $managerAdmin;
    private Admin $otherManager;
    private Building $building;
    private Building $otherBuilding;
    private Room $room;
    private Room $otherRoom;
    private Service $internetService;
    private Service $trashService;
    private Service $electricService;
    private RoomService $internetRoomService;
    private RoomService $trashRoomService;
    private RoomService $electricRoomService;
    private Tenant $tenant;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00'));

        $this->superAdmin = $this->makeAdmin('super-room-service', Admin::ROLE_SUPER_ADMIN, '0901000001');
        $this->managerAdmin = $this->makeAdmin('manager-room-service', Admin::ROLE_BUILDING_MANAGER, '0901000002');
        $this->otherManager = $this->makeAdmin('other-room-service', Admin::ROLE_BUILDING_MANAGER, '0901000003');

        $region = Region::create([
            'name' => 'Khu Test',
            'code' => 'ROOM_SERVICE_REGION',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->building = Building::create([
            'name' => 'Tòa A',
            'slug' => 'toa-a',
            'address' => '123 Test',
            'region_id' => $region->id,
            'manager_admin_id' => $this->managerAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $this->otherBuilding = Building::create([
            'name' => 'Tòa B',
            'slug' => 'toa-b',
            'address' => '456 Test',
            'region_id' => $region->id,
            'manager_admin_id' => $this->otherManager->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $roomType = RoomType::create([
            'name' => 'Standard',
            'slug' => 'standard-room-service',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->room = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'slug' => '101',
            'floor' => 1,
            'base_price' => '3000000.00',
            'max_occupants' => 4,
            'current_occupants' => 1,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->otherRoom = Room::create([
            'building_id' => $this->otherBuilding->id,
            'room_type_id' => $roomType->id,
            'room_number' => '201',
            'slug' => '201',
            'floor' => 2,
            'base_price' => '3200000.00',
            'max_occupants' => 4,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->internetService = Service::create([
            'name' => 'Internet',
            'slug' => 'internet',
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_active' => true,
        ]);

        $this->trashService = Service::create([
            'name' => 'Rác',
            'slug' => 'rac',
            'charge_method' => Service::CHARGE_METHOD_BY_PERSON,
            'unit_name' => 'người',
            'is_active' => true,
        ]);

        $this->electricService = Service::create([
            'name' => 'Điện sinh hoạt',
            'slug' => 'dien-sinh-hoat',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'kWh',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'service_id' => $this->internetService->id,
            'building_id' => $this->building->id,
            'price' => '100000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        ServicePrice::create([
            'service_id' => $this->trashService->id,
            'building_id' => $this->building->id,
            'price' => '30000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $this->internetRoomService = RoomService::create([
            'room_id' => $this->room->id,
            'service_id' => $this->internetService->id,
            'price' => '100000.00',
        ]);

        $this->trashRoomService = RoomService::create([
            'room_id' => $this->room->id,
            'service_id' => $this->trashService->id,
            'price' => '30000.00',
        ]);

        $this->electricRoomService = RoomService::create([
            'room_id' => $this->room->id,
            'service_id' => $this->electricService->id,
            'price' => '4000.00',
        ]);

        RoomService::create([
            'room_id' => $this->otherRoom->id,
            'service_id' => $this->internetService->id,
            'price' => '120000.00',
        ]);

        $this->tenant = Tenant::create([
            'username' => 'tenant-room-service',
            'full_name' => 'Tenant Room Service',
            'email' => 'tenant-room-service@stayhub.local',
            'phone' => '0911000000',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789099',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        $this->contract = Contract::create([
            'contract_code' => 'HD-RSP-001',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $this->tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ContractTenant::create([
            'contract_id' => $this->contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_returns_only_non_meter_room_services_for_accessible_rooms(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonMissing(['service_id' => $this->electricService->id]);

        $rooms = collect($response->json('result.data'));
        $room = $rooms->firstWhere('id', $this->room->id);

        $this->assertNotNull($room);
        $this->assertCount(2, $room['services']);
        $this->assertEqualsCanonicalizing(
            [$this->internetService->id, $this->trashService->id],
            collect($room['services'])->pluck('service_id')->all()
        );
    }

    public function test_room_service_prices_table_does_not_store_status_column(): void
    {
        $this->assertFalse(Schema::hasColumn('room_service_prices', 'status'));
    }

    public function test_room_services_table_does_not_store_price_column(): void
    {
        $this->assertFalse(Schema::hasColumn('room_services', 'price'));
    }

    public function test_room_services_table_tracks_lifecycle_state(): void
    {
        $this->assertTrue(Schema::hasColumn('room_services', 'is_active'));
        $this->assertTrue(Schema::hasColumn('room_services', 'ended_at'));
    }

    public function test_index_uses_room_service_prices_as_default_price_source(): void
    {
        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => null,
            'price' => '125000.00',
            'effective_from' => '2026-07-01',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

        $response->assertStatus(200);

        $room = collect($response->json('result.data'))->firstWhere('id', $this->room->id);
        $internet = collect($room['services'])->firstWhere('service_id', $this->internetService->id);

        $this->assertSame('125000.00', $internet['base_price']);
        $this->assertSame('125000.00', $internet['effective_price']);
    }

    public function test_room_service_prices_allows_default_and_contract_price_on_same_date(): void
    {
        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => null,
            'price' => '100000.00',
            'effective_from' => '2026-08-01',
            'created_by' => $this->superAdmin->id,
        ]);

        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => $this->contract->id,
            'price' => '80000.00',
            'effective_from' => '2026-08-01',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertSame(2, RoomServicePrice::query()
            ->where('room_service_id', $this->internetRoomService->id)
            ->whereDate('effective_from', '2026-08-01')
            ->count());
    }

    public function test_index_returns_one_room_contract_code_for_contract_scoped_service_prices(): void
    {
        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => $this->contract->id,
            'price' => '120000.00',
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-12-31',
            'created_by' => $this->superAdmin->id,
        ]);

        RoomServicePrice::query()->create([
            'room_service_id' => $this->trashRoomService->id,
            'contract_id' => $this->contract->id,
            'price' => '30000.00',
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-12-31',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

        $response->assertStatus(200);

        $room = collect($response->json('result.data'))->firstWhere('id', $this->room->id);

        $this->assertSame($this->contract->id, $room['active_contract_id']);
        $this->assertSame('HD-RSP-001', $room['active_contract_code']);
        $this->assertFalse($room['contract_is_ended']);
        $this->assertSame(
            ['HD-RSP-001'],
            collect($room['services'])->pluck('active_contract_code')->filter()->unique()->values()->all()
        );
    }

    public function test_index_returns_contract_code_when_contract_ends_inside_selected_month(): void
    {
        $this->contract->forceFill([
            'status' => Contract::STATUS_LIQUIDATED,
            'actual_end_date' => '2026-08-10',
        ])->save();

        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => $this->contract->id,
            'price' => '120000.00',
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-08-10',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

        $response->assertStatus(200);

        $room = collect($response->json('result.data'))->firstWhere('id', $this->room->id);
        $internet = collect($room['services'])->firstWhere('service_id', $this->internetService->id);

        $this->assertSame('HD-RSP-001', $room['active_contract_code']);
        $this->assertTrue($room['contract_is_ended']);
        $this->assertSame('HD-RSP-001', $internet['active_contract_code']);
        $this->assertTrue($internet['contract_is_ended']);
        $this->assertSame('120000.00', $internet['display_price']);
    }

    public function test_index_marks_all_services_in_ended_contract_context_as_ended(): void
    {
        $this->contract->forceFill([
            'status' => Contract::STATUS_LIQUIDATED,
            'actual_end_date' => '2026-08-10',
        ])->save();

        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => $this->contract->id,
            'price' => '120000.00',
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-08-10',
            'created_by' => $this->superAdmin->id,
        ]);

        RoomServicePrice::query()->create([
            'room_service_id' => $this->trashRoomService->id,
            'contract_id' => null,
            'price' => '30000.00',
            'effective_from' => '2026-01-01',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

        $response->assertStatus(200);

        $room = collect($response->json('result.data'))->firstWhere('id', $this->room->id);
        $trash = collect($room['services'])->firstWhere('service_id', $this->trashService->id);

        $this->assertSame('HD-RSP-001', $trash['active_contract_code']);
        $this->assertTrue($trash['contract_is_ended']);
        $this->assertSame('Hết hiệu lực', $trash['status_label']);
        $this->assertSame('30000.00', $trash['display_price']);
        $this->assertSame('room', $trash['display_price_source']);
    }

    public function test_index_returns_latest_old_contract_when_room_has_no_contract_in_selected_period(): void
    {
        $this->contract->forceFill([
            'status' => Contract::STATUS_LIQUIDATED,
            'actual_end_date' => '2026-07-14',
        ])->save();

        $this->internetRoomService->forceFill([
            'is_active' => false,
            'ended_at' => '2026-07-14',
        ])->save();

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

        $response->assertStatus(200);

        $room = collect($response->json('result.data'))->firstWhere('id', $this->room->id);

        $this->assertNull($room['active_contract_id']);
        $this->assertNull($room['active_contract_code']);
        $this->assertSame($this->contract->id, $room['latest_contract_id']);
        $this->assertSame('HD-RSP-001', $room['latest_contract_code']);
        $this->assertSame(Contract::STATUS_LIQUIDATED, $room['latest_contract_status']);
        $this->assertSame('Đã thanh lý', $room['latest_contract_status_label']);
        $this->assertSame('2026-07-14', $room['latest_contract_actual_end_date']);
    }

    public function test_index_does_not_leak_ended_contract_service_price_into_new_contract_context(): void
    {
        $this->contract->forceFill([
            'status' => Contract::STATUS_LIQUIDATED,
            'actual_end_date' => '2026-08-10',
        ])->save();

        $newTenant = Tenant::create([
            'username' => 'tenant-new-contract-context',
            'full_name' => 'Tenant New Contract Context',
            'email' => 'tenant-new-contract-context@stayhub.local',
            'phone' => '0911000009',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789209',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        $newContract = Contract::create([
            'contract_code' => 'HD-RSP-NEW',
            'room_id' => $this->room->id,
            'representative_tenant_id' => $newTenant->id,
            'start_date' => '2026-08-11',
            'end_date' => '2026-12-31',
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        RoomServicePrice::query()->create([
            'room_service_id' => $this->trashRoomService->id,
            'contract_id' => $this->contract->id,
            'price' => '45000.00',
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-08-10',
            'created_by' => $this->superAdmin->id,
        ]);

        RoomServicePrice::query()->create([
            'room_service_id' => $this->trashRoomService->id,
            'contract_id' => null,
            'price' => '30000.00',
            'effective_from' => '2026-01-01',
            'created_by' => $this->superAdmin->id,
        ]);

        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => $newContract->id,
            'price' => '90000.00',
            'effective_from' => '2026-08-11',
            'effective_to' => '2026-12-31',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

        $response->assertStatus(200);

        $room = collect($response->json('result.data'))->firstWhere('id', $this->room->id);
        $internet = collect($room['services'])->firstWhere('service_id', $this->internetService->id);
        $trash = collect($room['services'])->firstWhere('service_id', $this->trashService->id);

        $this->assertSame('HD-RSP-NEW', $room['active_contract_code']);
        $this->assertFalse($room['contract_is_ended']);
        $this->assertSame('HD-RSP-NEW', $internet['active_contract_code']);
        $this->assertSame('90000.00', $internet['display_price']);
        $this->assertSame('HD-RSP-NEW', $trash['active_contract_code']);
        $this->assertFalse($trash['contract_is_ended']);
        $this->assertNull($trash['contract_price']);
        $this->assertSame('30000.00', $trash['display_price']);
        $this->assertSame('room', $trash['display_price_source']);
    }

    public function test_building_manager_cannot_update_room_outside_managed_building(): void
    {
        $roomService = RoomService::where('room_id', $this->otherRoom->id)->firstOrFail();

        $response = $this->actingAs($this->managerAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->otherRoom->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    ['room_service_id' => $roomService->id, 'price' => 150000],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_schedule_room_service_price_for_current_or_past_month(): void
    {
        $payload = [
            'billing_month' => 7,
            'billing_year' => 2026,
            'prices' => [
                ['room_service_id' => $this->internetRoomService->id, 'price' => 150000],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Chỉ được lên lịch giá dịch vụ phòng cho tháng sau hoặc tương lai.');
    }

    public function test_cannot_schedule_metered_service_room_price(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    ['room_service_id' => $this->electricRoomService->id, 'price' => 5000],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Không thể lên lịch giá điện/nước theo từng phòng.');
    }

    public function test_cannot_schedule_inactive_room_service_price(): void
    {
        $this->internetRoomService->forceFill([
            'is_active' => false,
            'ended_at' => '2026-07-10',
        ])->save();

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    ['room_service_id' => $this->internetRoomService->id, 'price' => 150000],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Dịch vụ phòng đã ngừng hoạt động, không thể lên lịch thay đổi giá.');
    }

    public function test_index_marks_inactive_room_services_and_hides_future_schedules(): void
    {
        $this->internetRoomService->forceFill([
            'is_active' => false,
            'ended_at' => '2026-07-10',
        ])->save();

        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => null,
            'price' => '150000.00',
            'effective_from' => '2026-08-01',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

        $response->assertStatus(200);
        $room = collect($response->json('result.data'))->firstWhere('id', $this->room->id);
        $internet = collect($room['services'])->firstWhere('service_id', $this->internetService->id);

        $this->assertFalse($internet['is_active']);
        $this->assertFalse($internet['can_schedule_price']);
        $this->assertSame('Ngừng hoạt động', $internet['status_label']);
        $this->assertNull($internet['scheduled_price']);
        $this->assertNull($internet['new_price']);
        $this->assertSame('Dịch vụ phòng đã ngừng hoạt động.', $internet['schedule_block_reason']);
    }

    public function test_can_schedule_next_month_price_and_notify_current_tenants(): void
    {
        Event::fake([NotificationSent::class]);

        $response = $this->actingAs($this->managerAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    ['room_service_id' => $this->internetRoomService->id, 'price' => 150000],
                    ['room_service_id' => $this->trashRoomService->id, 'price' => 45000],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $this->internetRoomService->id,
            'price' => '150000.00',
            'effective_from' => '2026-08-01 00:00:00',
            'effective_to' => null,
            'created_by' => $this->managerAdmin->id,
        ]);

        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $this->trashRoomService->id,
            'price' => '45000.00',
            'effective_from' => '2026-08-01 00:00:00',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'room_id' => $this->room->id,
            'building_id' => $this->building->id,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'title' => 'Lên lịch thay đổi giá dịch vụ phòng',
        ]);

        Event::assertDispatched(NotificationSent::class, function (NotificationSent $event): bool {
            return (int) $event->notification->tenant_id === (int) $this->tenant->id
                && $event->notification->title === 'Lên lịch thay đổi giá dịch vụ phòng';
        });
    }

    public function test_can_schedule_contract_scoped_room_service_price_for_selected_contract(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    [
                        'room_service_id' => $this->internetRoomService->id,
                        'contract_id' => $this->contract->id,
                        'price' => 85000,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.active_contract_code', 'HD-RSP-001');

        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => $this->contract->id,
            'price' => '85000.00',
            'effective_from' => '2026-08-01 00:00:00',
            'effective_to' => '2026-12-31 00:00:00',
        ]);

        $this->assertDatabaseMissing('room_service_prices', [
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => null,
            'price' => '85000.00',
            'effective_from' => '2026-08-01 00:00:00',
        ]);
    }

    public function test_cannot_schedule_contract_scoped_price_for_other_room_contract(): void
    {
        $otherTenant = Tenant::create([
            'username' => 'tenant-other-room-contract-price',
            'full_name' => 'Tenant Other Room Contract Price',
            'email' => 'tenant-other-room-contract-price@stayhub.local',
            'phone' => '0911000002',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789199',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->otherBuilding->id,
        ]);

        $otherContract = Contract::create([
            'contract_code' => 'HD-RSP-OTHER',
            'room_id' => $this->otherRoom->id,
            'representative_tenant_id' => $otherTenant->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'room_price' => '3200000.00',
            'deposit_amount' => '3200000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    [
                        'room_service_id' => $this->internetRoomService->id,
                        'contract_id' => $otherContract->id,
                        'price' => 85000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Hợp đồng áp dụng giá không thuộc phòng đang chọn.');
    }

    public function test_rescheduling_same_month_updates_existing_price_without_duplicate(): void
    {
        $payload = [
            'billing_month' => 8,
            'billing_year' => 2026,
            'prices' => [
                ['room_service_id' => $this->internetRoomService->id, 'price' => 150000],
            ],
        ];

        $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", $payload)
            ->assertStatus(200);

        $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}/service-prices", [
                'billing_month' => 8,
                'billing_year' => 2026,
                'prices' => [
                    ['room_service_id' => $this->internetRoomService->id, 'price' => 175000],
                ],
            ])
            ->assertStatus(200);

        $this->assertSame(1, \App\Models\RoomServicePrice::query()
            ->where('room_service_id', $this->internetRoomService->id)
            ->whereDate('effective_from', '2026-08-01')
            ->count());

        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $this->internetRoomService->id,
            'price' => '175000.00',
            'effective_from' => '2026-08-01 00:00:00',
        ]);
    }

    public function test_invoice_uses_scheduled_room_service_price_for_billing_period(): void
    {
        RoomServicePrice::create([
            'room_service_id' => $this->internetRoomService->id,
            'price' => '150000.00',
            'effective_from' => '2026-08-01',
            'created_by' => $this->superAdmin->id,
        ]);

        RoomServicePrice::create([
            'room_service_id' => $this->trashRoomService->id,
            'price' => '45000.00',
            'effective_from' => '2026-08-01',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/invoices/preview', [
                'contract_id' => $this->contract->id,
                'billing_month' => 8,
                'billing_year' => 2026,
            ]);

        $response->assertStatus(200);
        $items = collect($response->json('result.items'));

        $internet = $items->firstWhere('service_id', $this->internetService->id);
        $trash = $items->firstWhere('service_id', $this->trashService->id);

        $this->assertEquals('150000.00', $internet['unit_price']);
        $this->assertEquals('150000.00', $internet['amount']);
        $this->assertEquals('45000.00', $trash['unit_price']);
        $this->assertEquals('45000.00', $trash['amount']);
    }

    public function test_contract_deal_creates_contract_scoped_room_service_price_and_blocks_utilities(): void
    {
        $trashRoomService = RoomService::query()->create([
            'room_id' => $this->otherRoom->id,
            'service_id' => $this->trashService->id,
        ]);

        $tenant = Tenant::create([
            'username' => 'tenant-contract-deal',
            'full_name' => 'Tenant Contract Deal',
            'email' => 'tenant-contract-deal@stayhub.local',
            'phone' => '0911000001',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789198',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->otherBuilding->id,
        ]);

        $blocked = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $this->contractPayload($this->otherRoom->id, $tenant->id, [
                ['service_id' => $this->electricService->id, 'price' => '4000.00'],
            ]));

        $blocked->assertStatus(422)
            ->assertJsonPath('message', 'Điện/nước là giá cấp tòa nhà, không được deal trong dịch vụ phòng của hợp đồng.');

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/contracts', $this->contractPayload($this->otherRoom->id, $tenant->id, [
                ['service_id' => $this->internetService->id, 'price' => '135000.00'],
                ['service_id' => $this->trashService->id, 'price' => '30000.00'],
            ]));

        $response->assertStatus(201);
        $contractId = (int) $response->json('result.id');
        $roomService = RoomService::query()
            ->where('room_id', $this->otherRoom->id)
            ->where('service_id', $this->internetService->id)
            ->firstOrFail();

        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $roomService->id,
            'contract_id' => $contractId,
            'price' => '135000.00',
            'effective_from' => '2026-08-01 00:00:00',
            'effective_to' => '2026-12-31 00:00:00',
        ]);

        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $trashRoomService->id,
            'contract_id' => $contractId,
            'price' => '30000.00',
            'effective_from' => '2026-08-01 00:00:00',
            'effective_to' => '2026-12-31 00:00:00',
        ]);

        $this->assertSame(2, RoomServicePrice::query()
            ->where('contract_id', $contractId)
            ->whereIn('room_service_id', [$roomService->id, $trashRoomService->id])
            ->count());
    }

    public function test_terminating_contract_closes_contract_scoped_room_service_price(): void
    {
        RoomServicePrice::query()->create([
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => $this->contract->id,
            'price' => '150000.00',
            'effective_from' => '2026-01-01',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/contracts/{$this->contract->id}/terminate", [
                'actual_end_date' => '2026-07-10',
                'deduction_amount' => '0.00',
                'payment_method' => 1,
                'note' => 'Thanh lý test giá dịch vụ',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('room_service_prices', [
            'room_service_id' => $this->internetRoomService->id,
            'contract_id' => $this->contract->id,
            'effective_to' => '2026-07-10',
        ]);
    }

    private function contractPayload(int $roomId, int $tenantId, array $services): array
    {
        return [
            'room_id' => $roomId,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'room_price' => '3200000.00',
            'deposit_amount' => '3300000.00',
            'status' => Contract::STATUS_ACTIVE,
            'tenants' => [
                [
                    'tenant_id' => $tenantId,
                    'join_date' => '2026-08-01',
                    'billing_start_date' => '2026-08-01',
                    'is_staying' => true,
                ],
            ],
            'services' => $services,
        ];
    }

    private function makeAdmin(string $username, int $role, string $phone): Admin
    {
        return Admin::create([
            'username' => $username,
            'full_name' => $username,
            'email' => $username.'@stayhub.local',
            'phone' => $phone,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);
    }
}
