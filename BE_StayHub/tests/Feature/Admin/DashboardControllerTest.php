<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $managerAdmin;
    private Admin $unauthorizedAdmin;
    private Building $building;
    private Service $electricityService;
    private Service $waterService;

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
            'slug' => 'electric',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'kWh',
            'is_required' => true,
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->waterService = Service::create([
            'name' => 'Nước sinh hoạt',
            'slug' => 'water',
            'charge_method' => Service::CHARGE_METHOD_BY_METER,
            'unit_name' => 'm³',
            'is_required' => true,
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_unauthenticated_cannot_access_price_history(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/utility-price-history');
        $response->assertStatus(401);
    }

    public function test_unauthorized_manager_cannot_access_building_price_history(): void
    {
        $response = $this->actingAs($this->unauthorizedAdmin, 'admin')
            ->getJson("/api/v1/admin/dashboard/utility-price-history?building_id={$this->building->id}");

        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_price_history_without_building_id(): void
    {
        // Seed some initial service prices
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => 3500.00,
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => 15000.00,
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/dashboard/utility-price-history');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(6, 'result');

        $result = $response->json('result');
        $this->assertEquals(3500.00, $result[5]['electric_price']);
        $this->assertEquals(15000.00, $result[5]['water_price']);
    }

    public function test_price_history_returns_changing_prices(): void
    {
        // Seed price active from Jan 1
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => 3500.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-05-31',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // New price from June 1
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->electricityService->id,
            'price' => 4500.00,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Seed water price active from Jan 1
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => 15000.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-05-31',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // New water price from June 1
        ServicePrice::create([
            'building_id' => $this->building->id,
            'service_id' => $this->waterService->id,
            'price' => 20000.00,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        // Freeze time to June 2026 to ensure consistent test months count
        Carbon::setTestNow('2026-06-15');

        $response = $this->actingAs($this->managerAdmin, 'admin')
            ->getJson("/api/v1/admin/dashboard/utility-price-history?building_id={$this->building->id}&months=6");

        $response->assertStatus(200);
        $result = $response->json('result');

        // result array will have months: 01/2026, 02/2026, 03/2026, 04/2026, 05/2026, 06/2026
        $this->assertEquals('01/2026', $result[0]['month']);
        $this->assertEquals(3500.00, $result[0]['electric_price']);
        $this->assertEquals(15000.00, $result[0]['water_price']);

        $this->assertEquals('05/2026', $result[4]['month']);
        $this->assertEquals(3500.00, $result[4]['electric_price']);
        $this->assertEquals(15000.00, $result[4]['water_price']);

        $this->assertEquals('06/2026', $result[5]['month']);
        $this->assertEquals(4500.00, $result[5]['electric_price']);
        $this->assertEquals(20000.00, $result[5]['water_price']);

        Carbon::setTestNow(); // Reset test time
    }
}
