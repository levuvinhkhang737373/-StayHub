<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceCrudTest extends TestCase
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

    public function test_super_admin_can_create_update_toggle_and_delete_service(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);

        $createResponse = $this->actingAs($admin, 'admin')->postJson('/api/admin/services', [
            'service_code' => 'SV-TEST',
            'name' => 'Dịch vụ kiểm thử',
            'service_type' => Service::SERVICE_TYPE_OTHER,
            'charge_method' => Service::CHARGE_METHOD_FIXED,
            'unit_name' => 'lần',
            'is_required' => false,
            'is_active' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.service_code', 'SV-TEST');

        $serviceId = $createResponse->json('result.id');

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'service_code' => 'SV-TEST',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')->putJson("/api/admin/services/{$serviceId}", [
            'service_code' => 'SV-TEST-2',
            'name' => 'Dịch vụ kiểm thử cập nhật',
            'service_type' => Service::SERVICE_TYPE_OTHER,
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_required' => true,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('result.service_code', 'SV-TEST-2')
            ->assertJsonPath('result.is_required', true);

        $this->actingAs($admin, 'admin')->patchJson("/api/admin/services/{$serviceId}/status", [
            'status' => false,
        ])->assertOk()
            ->assertJsonPath('result.is_active', false);

        $this->actingAs($admin, 'admin')->deleteJson("/api/admin/services/{$serviceId}")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('services', ['id' => $serviceId]);
    }

    public function test_building_manager_can_view_but_cannot_mutate_global_services(): void
    {
        $manager = $this->admin(Admin::ROLE_BUILDING_MANAGER);
        $service = $this->service();

        $this->actingAs($manager, 'admin')->getJson('/api/admin/services')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->actingAs($manager, 'admin')->getJson("/api/admin/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('result.id', $service->id);

        $this->actingAs($manager, 'admin')->postJson('/api/admin/services', [
            'service_code' => 'SV-DENY',
            'name' => 'Không được tạo',
            'service_type' => Service::SERVICE_TYPE_OTHER,
            'charge_method' => Service::CHARGE_METHOD_FIXED,
        ])->assertForbidden();

        $this->actingAs($manager, 'admin')->patchJson("/api/admin/services/{$service->id}/status", [
            'status' => false,
        ])->assertForbidden();
    }

    public function test_validation_returns_vietnamese_error_message(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);

        $this->actingAs($admin, 'admin')->postJson('/api/admin/services', [
            'name' => 'Thiếu mã',
            'service_type' => 'sai',
            'charge_method' => 99,
        ])->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Mã dịch vụ là bắt buộc.');
    }

    public function test_delete_is_blocked_when_service_has_prices(): void
    {
        $admin = $this->admin(Admin::ROLE_SUPER_ADMIN);
        $service = $this->service();
        $building = $this->building($admin);

        ServicePrice::query()->create([
            'service_id' => $service->id,
            'building_id' => $building->id,
            'price' => 100000,
            'effective_from' => '2026-05-01',
            'status' => ServicePrice::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin, 'admin')->deleteJson("/api/admin/services/{$service->id}")
            ->assertUnprocessable()
            ->assertJsonPath('status', false);

        $this->assertDatabaseHas('services', ['id' => $service->id]);
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

    private function service(): Service
    {
        return Service::query()->create([
            'service_code' => 'SV-'.uniqid(),
            'name' => 'Dịch vụ '.uniqid(),
            'service_type' => Service::SERVICE_TYPE_OTHER,
            'charge_method' => Service::CHARGE_METHOD_FIXED,
            'unit_name' => 'lần',
            'is_required' => false,
            'is_active' => true,
        ]);
    }

    private function building(Admin $admin): Building
    {
        $region = Region::query()->create([
            'code' => 'RG-'.uniqid(),
            'name' => 'Khu vực kiểm thử',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return Building::query()->create([
            'region_id' => $region->id,
            'manager_admin_id' => $admin->id,
            'name' => 'Tòa nhà kiểm thử',
            'address' => 'Địa chỉ kiểm thử',
            'total_floors' => 5,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);
    }
}
