<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $manager1;
    private Admin $manager2;
    private Region $region;
    private Building $building1;

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

        $this->manager1 = Admin::create([
            'username' => 'manager1',
            'full_name' => 'Manager One',
            'email' => 'manager1@stayhub.local',
            'phone' => '0900000001',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address 1',
        ]);

        $this->manager2 = Admin::create([
            'username' => 'manager2',
            'full_name' => 'Manager Two',
            'email' => 'manager2@stayhub.local',
            'phone' => '0900000002',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address 2',
        ]);

        $this->region = Region::create([
            'name' => 'Region Test',
            'code' => 'REG_TEST',
            'created_by' => $this->superAdmin->id,
        ]);

        // Manager1 already manages Building 1
        $this->building1 = Building::create([
            'name' => 'Building 1',
            'slug' => 'building-1',
            'address' => '123 Test St 1',
            'region_id' => $this->region->id,
            'manager_admin_id' => $this->manager1->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);
    }

    /**
     * Test building creation with manager checks.
     */
    public function test_cannot_create_building_with_already_assigned_manager(): void
    {
        $payload = [
            'region_id' => $this->region->id,
            'name' => 'Building 2',
            'manager_admin_id' => $this->manager1->id, // Already manages Building 1
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/buildings', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('result.manager_admin_id.0', 'Quản lý này đã được phân công quản lý tòa nhà khác.');
    }

    public function test_can_create_building_with_unassigned_manager(): void
    {
        $payload = [
            'region_id' => $this->region->id,
            'name' => 'Building 2',
            'manager_admin_id' => $this->manager2->id, // Unassigned
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/buildings', $payload);

        $response->assertStatus(201);
    }

    /**
     * Test building update with manager checks.
     */
    public function test_cannot_update_building_with_already_assigned_manager(): void
    {
        // Let's create Building 2 managed by Manager 2 first
        $building2 = Building::create([
            'name' => 'Building 2',
            'slug' => 'building-2',
            'address' => '123 Test St 2',
            'region_id' => $this->region->id,
            'manager_admin_id' => $this->manager2->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        // Try to update Building 2 to be managed by Manager 1 (who manages Building 1)
        $payload = [
            'manager_admin_id' => $this->manager1->id,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$building2->id}", $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('result.manager_admin_id.0', 'Quản lý này đã được phân công quản lý tòa nhà khác.');
    }

    public function test_can_update_building_with_its_own_manager(): void
    {
        // Update Building 1 to keep Manager 1 as manager
        $payload = [
            'manager_admin_id' => $this->manager1->id,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building1->id}", $payload);

        $response->assertStatus(200);
    }

    public function test_can_update_building_with_unassigned_manager(): void
    {
        // Update Building 1 to be managed by Manager 2 (who is unassigned)
        $payload = [
            'manager_admin_id' => $this->manager2->id,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/buildings/{$this->building1->id}", $payload);

        $response->assertStatus(200);
        $this->assertEquals($this->manager2->id, $this->building1->fresh()->manager_admin_id);
    }
}
