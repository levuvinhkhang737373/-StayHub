<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Notification;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingUtilityPriceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $managerAdmin;
    private Admin $unauthorizedAdmin;
        private Building $building;
    private Service $electricityService;
    private Service $waterService;
        private Tenant $tenant;
    private Tenant $inactiveTenant;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin
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

        // Create Manager Admin
        $this->managerAdmin = Admin::create([
            'username' => 'manager_test',
            'full_name' => 'Manager Test',
            'email' => 'manager_test@stayhub.local',
            'phone' => '0901234568',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        // Create Unauthorized Admin
        $this->unauthorizedAdmin = Admin::create([
            'username' => 'unauth_test',
            'full_name' => 'Unauth Test',
            'email' => 'unauth_test@stayhub.local',
            'phone' => '0901234569',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
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

        // Create Building managed by managerAdmin
        $this->building = Building::create([
            'name' => 'Building A',
            'slug' => 'building-a',
            'address' => '123 Test St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->managerAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        // Create Services
        $this->electricityService = Service::create([
            'name' => 'Điện sinh hoạt',
            'slug' => 'dien-sinh-hoat',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'kWh',
            'is_active' => true,
        ]);

        $this->waterService = Service::create([
            'name' => 'Nước sinh hoạt',
            'slug' => 'nuoc-sinh-hoat',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'm3',
            'is_active' => true,
        ]);

        // Initial Service Prices
        ServicePrice::create([
            'service_id' => $this->electricityService->id,
            'building_id' => $this->building->id,
            'price' => '4000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

                ServicePrice::create([
            'service_id' => $this->waterService->id,
            'building_id' => $this->building->id,
            'price' => '18000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Create Room Type
        $roomType = RoomType::create([
            'name' => 'Standard Room Type',
            'slug' => 'standard-room-type',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create Room
        $room = Room::create([
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
            'full_name' => 'Tenant Test',
            'email' => 'tenant_test@stayhub.local',
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

        // Create active Contract
        $this->contract = Contract::create([
            'contract_code' => 'HD-TEST',
            'room_id' => $room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

                // Create ContractTenant relationship
        ContractTenant::create([
            'contract_id' => $this->contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-06-01',
            'is_staying' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create Tenant 2 (not staying/inactive)
        $this->inactiveTenant = Tenant::create([
            'username' => 'tenant_inactive',
            'full_name' => 'Tenant Inactive',
            'email' => 'tenant_inactive@stayhub.local',
            'phone' => '0911111112',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789014',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
            'building_id' => $this->building->id,
        ]);

        ContractTenant::create([
            'contract_id' => $this->contract->id,
            'tenant_id' => $this->inactiveTenant->id,
            'join_date' => '2026-06-01',
            'is_staying' => false,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_superadmin_can_update_utility_prices(): void
    {
        Event::fake([
            NotificationSent::class
        ]);

        $targetYear = now()->year;
        $targetMonth = now()->month + 1;
        if ($targetMonth > 12) {
            $targetMonth = 1;
            $targetYear += 1;
        }

        $payload = [
            'electric_price' => 4500,
            'water_price' => 20000,
            'billing_month' => $targetMonth,
            'billing_year' => $targetYear,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building->id}/utility-prices", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Cập nhật đơn giá dịch vụ thành công',
            'result' => [
                'dien-sinh-hoat' => 4500,
                'nuoc-sinh-hoat' => 20000,
            ]
        ]);

        $effectiveFromDate = \Carbon\Carbon::create($targetYear, $targetMonth, 1)->startOfDay()->toDateTimeString();
        $effectiveToDate = \Carbon\Carbon::create($targetYear, $targetMonth, 1)->subDay()->startOfDay()->toDateTimeString();

        // Assert database has new active prices
        $this->assertDatabaseHas('service_prices', [
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => '4500.00',
            'effective_from' => $effectiveFromDate,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('service_prices', [
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => '20000.00',
            'effective_from' => $effectiveFromDate,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Assert old prices ended
        $this->assertDatabaseHas('service_prices', [
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => '4000.00',
            'effective_to' => $effectiveToDate,
            'status' => ServicePrice::STATUS_EXPIRED,
        ]);

        // Assert database has notifications created for the active tenant
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'building_id' => $this->building->id,
            'title' => 'Thay đổi đơn giá dịch vụ điện/nước',
            'content' => "Tòa nhà {$this->building->name} áp dụng đơn giá dịch vụ mới từ tháng {$targetMonth}/{$targetYear}: Điện 4.500 đ/kWh, Nước 20.000 đ/m³.",
        ]);

        // Assert that the NotificationSent event was broadcasted
        Event::assertDispatched(NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->tenant->id &&
                   $event->notification->title === 'Thay đổi đơn giá dịch vụ điện/nước';
        });

        // Assert database does not have notifications created for the inactive tenant
        $this->assertDatabaseMissing('notifications', [
            'tenant_id' => $this->inactiveTenant->id,
            'title' => 'Thay đổi đơn giá dịch vụ điện/nước',
        ]);

        // Assert that the NotificationSent event was NOT broadcasted for Tenant 2
        Event::assertNotDispatched(NotificationSent::class, function ($event) {
            return $event->notification->tenant_id === $this->inactiveTenant->id;
        });
    }

    public function test_building_manager_can_update_utility_prices_for_their_building(): void
    {
        $targetYear = now()->year;
        $targetMonth = now()->month + 1;
        if ($targetMonth > 12) {
            $targetMonth = 1;
            $targetYear += 1;
        }

        $payload = [
            'electric_price' => 4200,
            'water_price' => 19500,
            'billing_month' => $targetMonth,
            'billing_year' => $targetYear,
        ];

        $response = $this->actingAs($this->managerAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building->id}/utility-prices", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
        ]);
    }

    public function test_unauthorized_building_manager_cannot_update_utility_prices(): void
    {
        $targetYear = now()->year;
        $targetMonth = now()->month + 1;
        if ($targetMonth > 12) {
            $targetMonth = 1;
            $targetYear += 1;
        }

        $payload = [
            'electric_price' => 4200,
            'water_price' => 19500,
            'billing_month' => $targetMonth,
            'billing_year' => $targetYear,
        ];

        $response = $this->actingAs($this->unauthorizedAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building->id}/utility-prices", $payload);

        $response->assertStatus(403);
    }
    public function test_cannot_update_utility_prices_for_past_months(): void
    {
        $pastYear = now()->year;
        $pastMonth = now()->month - 1;
        if ($pastMonth < 1) {
            $pastMonth = 12;
            $pastYear -= 1;
        }

        $payload = [
            'electric_price' => 4500,
            'water_price' => 20000,
            'billing_month' => $pastMonth,
            'billing_year' => $pastYear,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building->id}/utility-prices", $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'Không thể thay đổi đơn giá cho tháng cũ.');
    }

    public function test_updating_utility_price_multiple_times_creates_multiple_lines_and_expires_old_ones(): void
    {
        $targetYear = now()->year;
        $targetMonth = now()->month + 1;
        if ($targetMonth > 12) {
            $targetMonth = 1;
            $targetYear += 1;
        }

        // First update
        $payload1 = [
            'electric_price' => 4500,
            'water_price' => 20000,
            'billing_month' => $targetMonth,
            'billing_year' => $targetYear,
        ];

        $response1 = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building->id}/utility-prices", $payload1);

        $response1->assertStatus(200);

        // Verify first record exists
        $effectiveFromDate = \Carbon\Carbon::create($targetYear, $targetMonth, 1)->startOfDay()->toDateTimeString();
        $this->assertDatabaseHas('service_prices', [
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => '4500.00',
            'effective_from' => $effectiveFromDate,
            'effective_to' => null,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Second update for same billing cycle with different price
        $payload2 = [
            'electric_price' => 4600,
            'water_price' => 21000,
            'billing_month' => $targetMonth,
            'billing_year' => $targetYear,
        ];

        $response2 = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building->id}/utility-prices", $payload2);

        $response2->assertStatus(200);

        // Assert database has new active prices
        $this->assertDatabaseHas('service_prices', [
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => '4600.00',
            'effective_from' => $effectiveFromDate,
            'effective_to' => null,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('service_prices', [
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => '21000.00',
            'effective_from' => $effectiveFromDate,
            'effective_to' => null,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Assert the first update records are now expired
        $effectiveToDate = \Carbon\Carbon::create($targetYear, $targetMonth, 1)->subDay()->startOfDay()->toDateTimeString();
        $this->assertDatabaseHas('service_prices', [
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => '4500.00',
            'effective_from' => $effectiveFromDate,
            'effective_to' => $effectiveToDate,
            'status' => ServicePrice::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('service_prices', [
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => '20000.00',
            'effective_from' => $effectiveFromDate,
            'effective_to' => $effectiveToDate,
            'status' => ServicePrice::STATUS_EXPIRED,
        ]);
    }

    public function test_can_retrieve_utility_price_log_history(): void
    {
        $payload = [
            'electric_price' => 4500,
            'water_price' => 20000,
            'billing_month' => now()->month,
            'billing_year' => now()->year,
        ];

        $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building->id}/utility-prices", $payload)
            ->assertStatus(200);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/buildings/{$this->building->id}/utility-price-history");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'result' => [
                    '*' => [
                        'id',
                        'service_id',
                        'service_name',
                        'price',
                        'effective_from',
                        'effective_to',
                        'status',
                        'status_label',
                        'created_by',
                        'creator_name',
                        'created_at',
                    ]
                ]
            ]);

        $result = $response->json('result');
        $this->assertNotEmpty($result);
        $this->assertEquals('Super Admin Test', $result[0]['creator_name']);
        $this->assertEquals($this->superAdmin->id, $result[0]['created_by']);
    }
}
