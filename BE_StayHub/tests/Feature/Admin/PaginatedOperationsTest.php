<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\MaintenanceRequest;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginatedOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Building $building;

    private Room $room;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->create([
            'username' => 'pagination_admin',
            'full_name' => 'Pagination Admin',
            'email' => 'pagination_admin@stayhub.local',
            'phone' => '0900000001',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test address',
        ]);

        $region = Region::query()->create([
            'name' => 'Pagination Region',
            'code' => 'PAGINATION_REGION',
            'slug' => 'pagination-region',
            'created_by' => $this->admin->id,
        ]);

        $this->building = Building::query()->create([
            'region_id' => $region->id,
            'manager_admin_id' => $this->admin->id,
            'name' => 'Pagination Building',
            'slug' => 'pagination-building',
            'address' => 'Pagination address',
            'gender_policy' => 1,
            'status' => 1,
            'created_by' => $this->admin->id,
        ]);

        $roomType = RoomType::query()->create([
            'name' => 'Pagination Room Type',
            'slug' => 'pagination-room-type',
            'status' => 1,
            'created_by' => $this->admin->id,
        ]);

        $this->room = Room::query()->create([
            'building_id' => $this->building->id,
            'room_type_id' => $roomType->id,
            'room_number' => 'P101',
            'slug' => 'p101',
            'floor' => 1,
            'base_price' => 2500000,
            'max_occupants' => 2,
            'status' => 1,
            'created_by' => $this->admin->id,
        ]);

        $this->tenant = Tenant::query()->create([
            'full_name' => 'Pagination Tenant',
            'gender' => 1,
            'date_of_birth' => '2000-01-01',
            'phone' => '0900000002',
            'email' => 'pagination_tenant@stayhub.local',
            'username' => 'pagination_tenant',
            'password' => bcrypt('password'),
            'status' => 1,
            'identity_type' => 1,
            'identity_number' => '123456789012',
            'created_by' => $this->admin->id,
            'building_id' => $this->building->id,
        ]);
    }

    public function test_vehicle_index_returns_laravel_pagination_meta(): void
    {
        foreach (range(1, 13) as $index) {
            Vehicle::query()->create([
                'tenant_id' => $this->tenant->id,
                'vehicle_type' => Vehicle::VEHICLE_TYPE_MOTORBIKE,
                'license_plate' => sprintf('59P-%05d', $index),
                'brand' => 'Honda',
                'color' => 'Đen',
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson('/api/v1/admin/vehicles?per_page=5&page=2');

        $response->assertOk()
            ->assertJsonPath('result.meta.current_page', 2)
            ->assertJsonPath('result.meta.per_page', 5)
            ->assertJsonPath('result.meta.total', 13)
            ->assertJsonPath('result.meta.last_page', 3)
            ->assertJsonCount(5, 'result.data');
    }

    public function test_setting_index_returns_laravel_pagination_meta(): void
    {
        foreach (range(1, 12) as $index) {
            Setting::query()->create([
                'building_id' => $this->building->id,
                'setting_label' => "Cài đặt {$index}",
                'setting_value' => "Giá trị {$index}",
                'description' => "Mô tả {$index}",
                'is_public' => true,
                'created_by' => $this->admin->id,
            ]);
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson('/api/v1/admin/settings?per_page=5&page=2');

        $response->assertOk()
            ->assertJsonPath('result.meta.current_page', 2)
            ->assertJsonPath('result.meta.per_page', 5)
            ->assertJsonPath('result.meta.total', 12)
            ->assertJsonPath('result.meta.last_page', 3)
            ->assertJsonCount(5, 'result.data');
    }

    public function test_maintenance_index_returns_laravel_pagination_meta(): void
    {
        foreach (range(1, 11) as $index) {
            MaintenanceRequest::query()->create([
                'request_code' => sprintf('MR-PAGINATION-%03d', $index),
                'tenant_id' => $this->tenant->id,
                'room_id' => $this->room->id,
                'title' => "Yêu cầu {$index}",
                'description' => "Mô tả yêu cầu {$index}",
                'status' => MaintenanceRequest::STATUS_CREATED,
            ]);
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson('/api/v1/admin/maintenance-requests?per_page=5&page=2');

        $response->assertOk()
            ->assertJsonPath('result.meta.current_page', 2)
            ->assertJsonPath('result.meta.per_page', 5)
            ->assertJsonPath('result.meta.total', 11)
            ->assertJsonPath('result.meta.last_page', 3)
            ->assertJsonPath('result.meta.from', 6)
            ->assertJsonPath('result.meta.to', 10)
            ->assertJsonCount(5, 'result.data');
    }
}
