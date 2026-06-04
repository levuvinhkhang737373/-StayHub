<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Notification;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_crud_notification_campaign(): void
    {
        $admin = $this->createAdmin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin);
        $room = $this->createRoom($building);
        $tenant = $this->createTenant($room);

        // 1. Admin tạo thông báo mới
        $responseCreate = $this->actingAs($admin, 'admin')->postJson('/api/admin/notifications', [
            'title' => 'Cúp nước định kỳ',
            'content' => 'Hệ thống nước sẽ cúp từ 9h đến 11h sáng mai để bảo trì.',
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_BUILDING,
            'building_id' => $building->id,
            'status' => Notification::STATUS_SENT,
        ]);

        $responseCreate->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.title', 'Cúp nước định kỳ');

        $notifId = $responseCreate->json('result.id');

        $this->assertDatabaseHas('notifications', [
            'id' => $notifId,
            'title' => 'Cúp nước định kỳ',
            'status' => Notification::STATUS_SENT,
        ]);

        // 2. Admin cập nhật thông báo
        $this->actingAs($admin, 'admin')->putJson("/api/admin/notifications/{$notifId}", [
            'title' => 'Cúp nước định kỳ (Cập nhật giờ)',
            'content' => 'Hệ thống nước sẽ cúp từ 8h đến 11h sáng mai để bảo trì.',
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_BUILDING,
            'building_id' => $building->id,
            'status' => Notification::STATUS_SENT,
        ])->assertOk()
            ->assertJsonPath('result.title', 'Cúp nước định kỳ (Cập nhật giờ)');

        // 3. Admin xóa thông báo
        $this->actingAs($admin, 'admin')->deleteJson("/api/admin/notifications/{$notifId}")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('notifications', ['id' => $notifId]);
    }

    public function test_tenant_can_view_and_read_notifications(): void
    {
        $admin = $this->createAdmin(Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin);
        $room = $this->createRoom($building);
        $tenant = $this->createTenant($room);

        // Tạo thông báo gửi cho tất cả tenants
        $notificationAll = Notification::query()->create([
            'title' => 'Thông báo chung',
            'content' => 'Nội dung thông báo chung',
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_ALL,
            'status' => Notification::STATUS_SENT,
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);

        // Tạo thông báo gửi cho riêng tenant
        $notificationTarget = Notification::query()->create([
            'title' => 'Nhắc đóng tiền phòng',
            'content' => 'Vui lòng thanh toán tiền phòng đúng hạn.',
            'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'tenant_id' => $tenant->id,
            'status' => Notification::STATUS_SENT,
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);

        // 1. Tenant lấy danh sách thông báo
        $responseList = $this->actingAs($tenant, 'tenant')->getJson('/api/tenant/notifications');
        $responseList->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'result.data');

        // 2. Tenant đánh dấu đọc một thông báo
        $this->actingAs($tenant, 'tenant')->postJson("/api/tenant/notifications/{$notificationTarget->id}/read")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('notification_reads', [
            'notification_id' => $notificationTarget->id,
            'tenant_id' => $tenant->id,
        ]);

        // 3. Tenant đánh dấu đọc tất cả
        $this->actingAs($tenant, 'tenant')->postJson('/api/tenant/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('notification_reads', [
            'notification_id' => $notificationAll->id,
            'tenant_id' => $tenant->id,
        ]);
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

        return Tenant::query()->create([
            'room_id' => $room->id,
            'username' => 'tenant_'.uniqid(),
            'full_name' => 'Tenant Test',
            'email' => 'tenant_'.uniqid().'@example.com',
            'phone' => '080'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'date_of_birth' => '1998-01-01',
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789012',
            'created_by' => $admin->id,
        ]);
    }
}
