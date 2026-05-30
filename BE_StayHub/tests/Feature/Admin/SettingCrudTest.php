<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\Setting;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingCrudTest extends TestCase
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

    public function test_super_admin_can_create_update_show_and_delete_global_and_building_settings(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->building($admin, 'Tòa A');

        $globalResponse = $this->actingAs($admin, 'admin')->postJson('/api/admin/settings', [
            'setting_label' => 'Giờ đóng cổng',
            'setting_name' => 'gate.close_time',
            'setting_value' => '23:00',
            'description' => 'Áp dụng toàn hệ thống',
            'is_public' => true,
        ]);

        $globalResponse->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.setting_name', 'gate.close_time')
            ->assertJsonPath('result.building_id', null);

        $createResponse = $this->actingAs($admin, 'admin')->postJson('/api/admin/settings', [
            'building_id' => $building->id,
            'setting_label' => 'Tiền cọc mặc định',
            'setting_name' => 'contract.default_deposit',
            'setting_value' => '2000000',
            'description' => 'Áp dụng cho tòa A',
            'is_public' => false,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.setting_name', 'contract.default_deposit')
            ->assertJsonPath('result.building_name', $building->name);

        $settingId = $createResponse->json('result.id');

        $this->assertDatabaseHas('settings', [
            'id' => $settingId,
            'building_id' => $building->id,
            'setting_name' => 'contract.default_deposit',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')->putJson("/api/admin/settings/{$settingId}", [
            'building_id' => $building->id,
            'setting_label' => 'Tiền cọc cập nhật',
            'setting_name' => 'contract.default_deposit',
            'setting_value' => '2500000',
            'description' => 'Đã cập nhật',
            'is_public' => true,
        ])->assertOk()
            ->assertJsonPath('result.setting_label', 'Tiền cọc cập nhật')
            ->assertJsonPath('result.setting_value', '2500000')
            ->assertJsonPath('result.is_public', true);

        $this->actingAs($admin, 'admin')->getJson("/api/admin/settings/{$settingId}")
            ->assertOk()
            ->assertJsonPath('result.id', $settingId)
            ->assertJsonPath('result.setting_name', 'contract.default_deposit')
            ->assertJsonPath('result.building.name', $building->name);

        $this->actingAs($admin, 'admin')->deleteJson("/api/admin/settings/{$settingId}")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('settings', ['id' => $settingId]);
    }

    public function test_building_manager_can_manage_only_own_building_settings(): void
    {
        $managerA = $this->admin(Admin::ROLE_BUILDING_MANAGER);
        $managerB = $this->admin(Admin::ROLE_BUILDING_MANAGER);
        $buildingA = $this->building($managerA, 'Tòa A');
        $buildingB = $this->building($managerB, 'Tòa B');
        $otherSetting = $this->setting($managerB, $buildingB, 'other.key');

        $createResponse = $this->actingAs($managerA, 'admin')->postJson('/api/admin/settings', [
            'building_id' => $buildingA->id,
            'setting_label' => 'Nội quy tòa A',
            'setting_name' => 'rules.main',
            'setting_value' => 'Không gây ồn sau 22h',
            'is_public' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('result.building_id', $buildingA->id)
            ->assertJsonPath('result.created_by', $managerA->id);

        $settingId = $createResponse->json('result.id');

        $this->actingAs($managerA, 'admin')->postJson('/api/admin/settings', [
            'building_id' => $buildingB->id,
            'setting_label' => 'Không được tạo',
            'setting_name' => 'deny.other',
        ])->assertForbidden();

        $this->actingAs($managerA, 'admin')->postJson('/api/admin/settings', [
            'setting_label' => 'Không được tạo global',
            'setting_name' => 'deny.global',
        ])->assertForbidden();

        $this->actingAs($managerA, 'admin')->putJson("/api/admin/settings/{$settingId}", [
            'building_id' => $buildingA->id,
            'setting_label' => 'Nội quy cập nhật',
            'setting_name' => 'rules.main',
            'setting_value' => 'Không gây ồn sau 23h',
            'is_public' => false,
        ])->assertOk()
            ->assertJsonPath('result.setting_label', 'Nội quy cập nhật')
            ->assertJsonPath('result.is_public', false);

        $this->actingAs($managerA, 'admin')->getJson("/api/admin/settings/{$otherSetting->id}")
            ->assertNotFound();

        $this->actingAs($managerA, 'admin')->putJson("/api/admin/settings/{$otherSetting->id}", [
            'building_id' => $buildingB->id,
            'setting_label' => 'Không được sửa',
            'setting_name' => 'other.key',
        ])->assertForbidden();

        $this->actingAs($managerA, 'admin')->deleteJson("/api/admin/settings/{$otherSetting->id}")
            ->assertNotFound();

        $this->actingAs($managerA, 'admin')->deleteJson("/api/admin/settings/{$settingId}")
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_index_is_scoped_to_managed_buildings(): void
    {
        $superAdmin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $managerA = $this->admin(Admin::ROLE_BUILDING_MANAGER);
        $managerB = $this->admin(Admin::ROLE_BUILDING_MANAGER);
        $buildingA = $this->building($managerA, 'Tòa A');
        $buildingB = $this->building($managerB, 'Tòa B');

        $this->setting($superAdmin, null, 'global.key');
        $ownSetting = $this->setting($managerA, $buildingA, 'own.key');
        $this->setting($managerB, $buildingB, 'other.key');

        $response = $this->actingAs($managerA, 'admin')->getJson('/api/admin/settings');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'result.data')
            ->assertJsonPath('result.data.0.id', $ownSetting->id);

        $this->actingAs($managerA, 'admin')->getJson('/api/admin/settings?building_id='.$buildingB->id)
            ->assertForbidden();
    }

    public function test_duplicate_setting_name_is_blocked_in_same_building_scope(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $buildingA = $this->building($admin, 'Tòa A');
        $buildingB = $this->building($admin, 'Tòa B');

        $this->setting($admin, $buildingA, 'duplicate.key');
        $this->setting($admin, null, 'global.duplicate');

        $this->actingAs($admin, 'admin')->postJson('/api/admin/settings', [
            'building_id' => $buildingA->id,
            'setting_label' => 'Trùng khóa',
            'setting_name' => 'duplicate.key',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Khóa cài đặt đã tồn tại trong phạm vi tòa nhà này');

        $this->actingAs($admin, 'admin')->postJson('/api/admin/settings', [
            'building_id' => $buildingB->id,
            'setting_label' => 'Không trùng vì khác tòa',
            'setting_name' => 'duplicate.key',
        ])->assertCreated();

        $this->actingAs($admin, 'admin')->postJson('/api/admin/settings', [
            'setting_label' => 'Trùng global',
            'setting_name' => 'global.duplicate',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Khóa cài đặt đã tồn tại trong phạm vi tòa nhà này');
    }

    public function test_validation_returns_vietnamese_error_message(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);

        $this->actingAs($admin, 'admin')->postJson('/api/admin/settings', [
            'setting_name' => 'invalid key',
        ])->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Tên hiển thị cài đặt là bắt buộc.');
    }

    private function admin(int $role): Admin
    {
        return Admin::query()->create([
            'username' => 'admin_'.$role.'_'.uniqid(),
            'full_name' => 'Admin Test',
            'email' => 'admin_'.$role.'_'.uniqid().'@example.com',
            'phone' => '090'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
        ]);
    }

    private function building(Admin $manager, string $name): Building
    {
        $region = Region::query()->create([
            'code' => 'RG-'.uniqid(),
            'name' => 'Khu vực '.uniqid(),
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        return Building::query()->create([
            'region_id' => $region->id,
            'manager_admin_id' => $manager->id,
            'name' => $name,
            'address' => 'Địa chỉ kiểm thử',
            'total_floors' => 5,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $manager->id,
        ]);
    }

    private function setting(Admin $admin, ?Building $building, string $settingName): Setting
    {
        return Setting::query()->create([
            'building_id' => $building?->id,
            'setting_label' => 'Cài đặt '.$settingName,
            'setting_name' => $settingName,
            'setting_value' => 'Giá trị',
            'description' => 'Mô tả kiểm thử',
            'is_public' => true,
            'created_by' => $admin->id,
        ]);
    }
}
