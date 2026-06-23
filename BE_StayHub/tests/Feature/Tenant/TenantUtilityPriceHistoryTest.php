<?php

namespace Tests\Feature\Tenant;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUtilityPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Building $building;
    private Tenant $tenant;
    private Service $electricityService;
    private Service $waterService;

    protected function setUp(): void
    {
        parent::setUp();

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

        // Service Prices (History)
        ServicePrice::create([
            'service_id' => $this->electricityService->id,
            'building_id' => $this->building->id,
            'price' => '4000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        ServicePrice::create([
            'service_id' => $this->waterService->id,
            'building_id' => $this->building->id,
            'price' => '18000.00',
            'effective_from' => '2026-01-01',
            'status' => ServicePrice::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_unauthenticated_tenant_cannot_access_price_history()
    {
        $response = $this->getJson('/api/v1/tenant/utility-price-history');
        $response->assertStatus(401);
    }

    public function test_authenticated_tenant_can_access_price_history()
    {
        $response = $this->actingAs($this->tenant, 'tenant')
            ->getJson('/api/v1/tenant/utility-price-history');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
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
        $this->assertCount(2, $result);
        
        // Assert sorting and services price logic matches
        $this->assertEquals(18000.0, $result[0]['price']);
        $this->assertEquals('Nước sinh hoạt', $result[0]['service_name']);
        $this->assertEquals(4000.0, $result[1]['price']);
        $this->assertEquals('Điện sinh hoạt', $result[1]['service_name']);
    }
}
