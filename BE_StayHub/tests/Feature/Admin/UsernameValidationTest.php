<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsernameValidationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Building $building;

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
    }

    /**
     * Test admin username validation on registration.
     */
    public function test_admin_username_validation(): void
    {
        // Valid username (alpha_dash, 3-20 chars)
        $payload = [
            'username' => 'admin_123',
            'full_name' => 'Test Admin',
            'email' => 'admin123@stayhub.local',
            'phone' => '0900000001',
            'role' => Admin::ROLE_BUILDING_MANAGER,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/accounts', $payload);

        $response->assertStatus(201);

        // Invalid: too short
        $payload['username'] = 'ad';
        $payload['email'] = 'admin2@stayhub.local';
        $payload['phone'] = '0900000002';
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/accounts', $payload);
        $response->assertStatus(422);

        // Invalid: too long (21 chars)
        $payload['username'] = 'admin_username_too_long';
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/accounts', $payload);
        $response->assertStatus(422);

        // Invalid: contains invalid characters (dot is not allowed in alpha_dash in laravel: alpha_dash allows alpha-numeric characters, dashes, and underscores)
        $payload['username'] = 'admin.dot';
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/accounts', $payload);
        $response->assertStatus(422);
    }

    /**
     * Test tenant username validation on registration.
     */
    public function test_tenant_username_validation_on_registration(): void
    {
        // Valid username (alpha_dash, 3-20 chars)
        $payload = [
            'building_id' => $this->building->id,
            'full_name' => 'Test Tenant',
            'date_of_birth' => '2000-01-01',
            'phone' => '0910000001',
            'email' => 'tenant1@stayhub.local',
            'username' => 'tenant_123',
            'identity_number' => '123456789012',
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/tenants', $payload);

        $response->assertStatus(201);

        // Invalid: too short
        $payload['username'] = 'te';
        $payload['email'] = 'tenant2@stayhub.local';
        $payload['phone'] = '0910000002';
        $payload['identity_number'] = '123456789013';
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/tenants', $payload);
        $response->assertStatus(422);

        // Invalid: too long
        $payload['username'] = 'tenant_username_too_long';
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/tenants', $payload);
        $response->assertStatus(422);

        // Invalid: contains invalid characters (dot)
        $payload['username'] = 'tenant.dot';
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/tenants', $payload);
        $response->assertStatus(422);
    }

    /**
     * Test tenant username validation on update.
     */
    public function test_tenant_username_validation_on_update(): void
    {
        $tenant = Tenant::create([
            'building_id' => $this->building->id,
            'full_name' => 'Existing Tenant',
            'date_of_birth' => '2000-01-01',
            'phone' => '0920000001',
            'email' => 'tenant_exist@stayhub.local',
            'username' => 'tenant_exist',
            'password' => bcrypt('password'),
            'identity_number' => '123456789014',
            'created_by' => $this->superAdmin->id,
        ]);

        // Update attempt with username should fail because username is prohibited on update
        $payload = [
            'username' => 'tenant_updated',
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson('/api/v1/admin/tenants/' . $tenant->id, $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('result.username.0', 'Không được thay đổi tên đăng nhập khách thuê.');

        // Update other valid fields should succeed
        $payload = [
            'full_name' => 'Updated Tenant Name',
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson('/api/v1/admin/tenants/' . $tenant->id, $payload);

        $response->assertStatus(200);
        $this->assertEquals('Updated Tenant Name', $tenant->fresh()->full_name);
    }
}
