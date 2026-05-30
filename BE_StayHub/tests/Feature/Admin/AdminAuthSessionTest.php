<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is not available.');
        }

        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_legacy_admin_id_session_does_not_authenticate_admin_me(): void
    {
        $manager = $this->admin(Admin::ROLE_BUILDING_MANAGER);

        $this->withSession(['admin_id' => $manager->id])
            ->getJson('/api/admin/me')
            ->assertUnauthorized()
            ->assertJsonPath('status', false);
    }

    public function test_legacy_admin_id_session_does_not_authenticate_facilities_apis(): void
    {
        $manager = $this->admin(Admin::ROLE_BUILDING_MANAGER);
        $region = $this->region($manager);
        $building = $this->building($manager, $region);

        $this->withSession(['admin_id' => $manager->id])
            ->getJson('/api/admin/regions')
            ->assertUnauthorized()
            ->assertJsonPath('status', false);

        $this->withSession(['admin_id' => $manager->id])
            ->getJson('/api/admin/buildings')
            ->assertUnauthorized()
            ->assertJsonPath('status', false);

        $this->withSession(['admin_id' => $manager->id])
            ->getJson("/api/admin/buildings/{$building->id}")
            ->assertUnauthorized()
            ->assertJsonPath('status', false);
    }

    public function test_logout_cleans_stale_legacy_session(): void
    {
        $manager = $this->admin(Admin::ROLE_BUILDING_MANAGER);

        $this->withSession(['admin_id' => $manager->id])
            ->postJson('/api/admin/logout')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->getJson('/api/admin/me')
            ->assertUnauthorized()
            ->assertJsonPath('status', false);
    }

    public function test_super_admin_login_logout_clears_authenticated_session(): void
    {
        $superAdmin = $this->admin(Admin::ROLE_SUPER_ADMIN, 'superadmin_session_test');

        $this->postJson('/api/admin/login', [
            'username' => $superAdmin->username,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.admin.id', $superAdmin->id)
            ->assertJsonPath('result.admin.role', Admin::ROLE_SUPER_ADMIN);

        $this->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.id', $superAdmin->id)
            ->assertJsonPath('result.role', Admin::ROLE_SUPER_ADMIN);

        $this->postJson('/api/admin/logout')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->getJson('/api/admin/me')
            ->assertUnauthorized()
            ->assertJsonPath('status', false);
    }

    public function test_building_manager_guard_session_still_accesses_allowed_building_api(): void
    {
        $manager = $this->admin(Admin::ROLE_BUILDING_MANAGER, 'manager_session_test');
        $region = $this->region($manager);
        $building = $this->building($manager, $region);

        $this->postJson('/api/admin/login', [
            'username' => $manager->username,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.admin.id', $manager->id)
            ->assertJsonPath('result.admin.role', Admin::ROLE_BUILDING_MANAGER);

        $this->getJson('/api/admin/buildings')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.0.id', $building->id);
    }

    private function admin(int $role, ?string $username = null): Admin
    {
        return Admin::query()->create([
            'username' => $username ?? 'admin_'.$role.'_'.uniqid(),
            'full_name' => 'Admin Session Test',
            'email' => ($username ?? 'admin_'.$role.'_'.uniqid()).'@example.com',
            'phone' => '090'.random_int(1000000, 9999999),
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
        ]);
    }

    private function region(Admin $admin): Region
    {
        return Region::query()->create([
            'code' => 'RG-'.uniqid(),
            'name' => 'Khu vực '.uniqid(),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }

    private function building(Admin $manager, Region $region): Building
    {
        return Building::query()->create([
            'region_id' => $region->id,
            'manager_admin_id' => $manager->id,
            'name' => 'Tòa kiểm thử '.uniqid(),
            'address' => 'Địa chỉ kiểm thử',
            'total_floors' => 5,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $manager->id,
        ]);
    }
}
