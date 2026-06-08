<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Building;
use App\Models\MaintenanceRequest;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintenanceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_tenant_can_create_maintenance_request_and_view_list(): void
    {
        $admin = $this->createAdmin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin);
        $room = $this->createRoom($building);
        $tenant = $this->createTenant($room);

        // 1. Tenant gửi yêu cầu sửa chữa mới
        $response = $this->actingAs($tenant, 'tenant')->postJson('/api/tenant/maintenance-requests', [
            'title' => 'Hỏng vòi nước',
            'description' => 'Vòi nước bồn rửa mặt bị rò rỉ mạnh, cần sửa gấp.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.title', 'Hỏng vòi nước');

        $requestId = $response->json('result.id');

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $requestId,
            'title' => 'Hỏng vòi nước',
            'status' => MaintenanceRequest::STATUS_CREATED,
        ]);

        // 2. Tenant xem danh sách yêu cầu của mình
        $this->actingAs($tenant, 'tenant')->getJson('/api/tenant/maintenance-requests')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'result.data');
    }

    public function test_admin_can_assign_staff_and_update_status(): void
    {
        $admin = $this->createAdmin(Admin::ROLE_SUPER_ADMIN);
        $staff = $this->createAdmin(Admin::ROLE_BUILDING_MANAGER);
        $building = $this->createBuilding($admin);
        $room = $this->createRoom($building);
        $tenant = $this->createTenant($room);

        // Tạo sẵn yêu cầu bảo trì
        $maintenance = MaintenanceRequest::query()->create([
            'request_code' => 'SC-000001',
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
            'title' => 'Hỏng điều hòa',
            'description' => 'Điều hòa không mát',
            'status' => MaintenanceRequest::STATUS_CREATED,
        ]);

        // 1. Admin phân công nhân sự xử lý
        $responseAssign = $this->actingAs($admin, 'admin')->patchJson("/api/admin/maintenance-requests/{$maintenance->id}/assign", [
            'assigned_to' => $staff->id,
        ]);

        $responseAssign->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.assigned_to', $staff->id);

        // 2. Admin cập nhật trạng thái phiếu sang Đang xử lý
        $this->actingAs($admin, 'admin')->patchJson("/api/admin/maintenance-requests/{$maintenance->id}/status", [
            'status' => MaintenanceRequest::STATUS_PROCESSING,
            'note' => 'Nhân viên đang di chuyển tới phòng.',
        ])->assertOk()
            ->assertJsonPath('result.status', MaintenanceRequest::STATUS_PROCESSING);

        // 3. Admin cập nhật trạng thái phiếu sang Đã hoàn thành
        $this->actingAs($admin, 'admin')->patchJson("/api/admin/maintenance-requests/{$maintenance->id}/status", [
            'status' => MaintenanceRequest::STATUS_COMPLETED,
            'note' => 'Đã thay block máy lạnh và nạp ga.',
        ])->assertOk()
            ->assertJsonPath('result.status', MaintenanceRequest::STATUS_COMPLETED);

        // 4. Tenant gửi phản hồi chất lượng sửa chữa
        $this->actingAs($tenant, 'tenant')->postJson("/api/tenant/maintenance-requests/{$maintenance->id}/feedback", [
            'rating' => 5,
            'comment' => 'Dịch vụ sửa rất nhanh và nhiệt tình.',
        ])->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.rating', 5);
    }

    private function createAdmin(int $role): Admin
    {
        return Admin::query()->create([
            'username' => 'admin_'.uniqid(),
            'full_name' => 'Admin Test',
            'email' => 'admin_'.uniqid().'@example.com',
            'phone' => '090'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
        ]);
    }

    private function createBuilding(Admin $admin): Building
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

    private function createRoomType(Building $building): RoomType
    {
        return RoomType::query()->create([
            'name' => 'Loại phòng kiểm thử',
            'building_id' => $building->id,
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $building->created_by,
        ]);
    }

    private function createRoom(Building $building): Room
    {
        $roomType = $this->createRoomType($building);

        return Room::query()->create([
            'building_id' => $building->id,
            'room_type_id' => $roomType->id,
            'room_number' => 'Room-'.uniqid(),
            'floor' => 1,
            'area_m2' => 25.0,
            'base_price' => 3000000.0,
            'max_occupants' => 4,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $building->created_by,
        ]);
    }

    private function createTenant(Room $room): Tenant
    {
        $admin = Admin::query()->first() ?? $this->createAdmin(Admin::ROLE_SUPER_ADMIN);

        $tenant = Tenant::query()->create([
            'building_id' => $room->building_id,
            'username' => 'tenant_'.uniqid(),
            'full_name' => 'Tenant Test',
            'email' => 'tenant_'.uniqid().'@example.com',
            'phone' => '080'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'date_of_birth' => '1998-01-01',
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '1234567890'.random_int(10, 99),
            'created_by' => $admin->id,
        ]);

        $contract = \App\Models\Contract::query()->create([
            'contract_code' => 'HD-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'room_id' => $room->id,
            'representative_tenant_id' => $tenant->id,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'billing_cycle_day' => 5,
            'room_price' => $room->base_price,
            'deposit_amount' => $room->base_price * 2,
            'status' => \App\Models\Contract::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        $contract->tenants()->attach($tenant->id, [
            'join_date' => now()->subDays(5)->toDateString(),
            'is_representative' => true,
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        return $tenant;
    }
}
