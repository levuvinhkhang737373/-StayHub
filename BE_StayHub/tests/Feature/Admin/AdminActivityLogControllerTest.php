<?php

namespace Tests\Feature\Admin;

use App\Helpers\AdminActivityLogger;
use App\Jobs\BulkGenerateInvoicesJob;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\Building;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $managerAdmin;
    private Building $building;
    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->createAdmin('super_activity', Admin::ROLE_SUPER_ADMIN);
        $this->managerAdmin = $this->createAdmin('manager_activity', Admin::ROLE_BUILDING_MANAGER);

        $region = Region::create([
            'name' => 'Khu audit',
            'code' => 'AUDIT_REGION',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->building = Building::create([
            'region_id' => $region->id,
            'manager_admin_id' => $this->managerAdmin->id,
            'name' => 'Tòa audit',
            'slug' => 'toa-audit',
            'address' => '1 Audit',
            'total_floors' => 8,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->roomType = RoomType::create([
            'name' => 'Phòng audit',
            'slug' => 'phong-audit',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_only_super_admin_can_view_activity_logs(): void
    {
        $this->createAdminLog($this->superAdmin, 'create_region', Region::class, 1);

        $this->getJson('/api/v1/admin/activity-logs')
            ->assertUnauthorized()
            ->assertJsonPath('status', false);

        $this->actingAs($this->managerAdmin, 'admin')
            ->getJson('/api/v1/admin/activity-logs')
            ->assertForbidden()
            ->assertJsonPath('message', 'Bạn không có quyền truy cập chức năng này');

        $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/activity-logs')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.meta.total', 1)
            ->assertJsonPath('result.data.0.admin_name', 'Quản trị tổng - Super_activity')
            ->assertJsonPath('result.data.0.action', 'Tạo khu vực');
    }

    public function test_super_admin_can_filter_activity_logs_and_view_masked_detail(): void
    {
        $targetLog = $this->createAdminLog(
            $this->superAdmin,
            'update_room',
            Room::class,
            99,
            ['password' => 'old-secret', 'room_number' => 'A101'],
            ['password' => 'new-secret', 'room_number' => 'A102', 'profile' => ['token' => 'hidden-token']],
            now()->subDay(),
        );

        $managerLog = $this->createAdminLog($this->managerAdmin, 'create_building', Building::class, $this->building->id, null, ['name' => 'Khác'], now()->subDays(5));

        $query = http_build_query([
            'keyword' => 'A102',
            'admin_id' => $this->superAdmin->id,
            'action' => 'update_room',
            'entity_type' => Room::class,
            'entity_id' => 99,
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/activity-logs?{$query}")
            ->assertOk()
            ->assertJsonPath('result.meta.total', 1)
            ->assertJsonPath('result.data.0.id', $targetLog->id)
            ->assertJsonPath('result.data.0.admin_name', 'Quản trị tổng - Super_activity')
            ->assertJsonPath('result.data.0.entity_name', 'A102')
            ->assertJsonPath('result.data.0.entity_type_label', 'Phòng')
            ->assertJsonPath('result.data.0.old_data.password', '***')
            ->assertJsonPath('result.data.0.new_data.profile.token', '***');

        $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/activity-logs/{$targetLog->id}")
            ->assertOk()
            ->assertJsonPath('result.id', $targetLog->id)
            ->assertJsonPath('result.admin_name', 'Quản trị tổng - Super_activity')
            ->assertJsonPath('result.entity_name', 'A102')
            ->assertJsonPath('result.old_data.password', '***')
            ->assertJsonPath('result.new_data.profile.token', '***')
            ->assertJsonPath('result.changed_fields.0', 'password')
            ->assertJsonPath('result.old_data_display.0.label', 'Mật khẩu')
            ->assertJsonPath('result.old_data_display.0.value', 'Đã ẩn')
            ->assertJsonPath('result.new_data_display.1.label', 'Số phòng')
            ->assertJsonPath('result.new_data_display.1.value', 'A102')
            ->assertJsonPath('result.changed_fields_display.0', 'Mật khẩu')
            ->assertJsonPath('result.change_summary.1.label', 'Số phòng')
            ->assertJsonPath('result.change_summary.1.old_value', 'A101')
            ->assertJsonPath('result.change_summary.1.new_value', 'A102');

        $this->actingAs($this->superAdmin, 'admin')
            ->getJson("/api/v1/admin/activity-logs/{$managerLog->id}")
            ->assertOk()
            ->assertJsonPath('result.admin_name', 'Tòa audit - Manager_activity');
    }

    public function test_room_mutations_are_logged_with_admin_activity_logger(): void
    {
        $createResponse = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/rooms', $this->roomPayload([
                'room_number' => 'A101',
            ]));

        $createResponse->assertCreated()
            ->assertJsonPath('status', true);

        $roomId = (int) $createResponse->json('result.id');

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->superAdmin->id,
            'action' => 'Tạo phòng',
            'entity_type' => Room::class,
            'entity_id' => $roomId,
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$roomId}", $this->roomPayload([
                'room_number' => 'A102',
                'base_price' => '2500000',
            ]))
            ->assertOk();

        $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/api/v1/admin/rooms/{$roomId}/status", [
                'status' => Room::STATUS_MAINTENANCE,
            ])
            ->assertOk();

        $this->actingAs($this->superAdmin, 'admin')
            ->deleteJson("/api/v1/admin/rooms/{$roomId}")
            ->assertOk();

        foreach (['Cập nhật phòng', 'Cập nhật trạng thái phòng', 'Xóa phòng'] as $action) {
            $this->assertDatabaseHas('admin_logs', [
                'admin_id' => $this->superAdmin->id,
                'action' => $action,
                'entity_type' => Room::class,
                'entity_id' => $roomId,
            ]);
        }
    }

    public function test_bulk_invoice_queue_action_is_logged(): void
    {
        Queue::fake();

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/api/v1/admin/buildings/{$this->building->id}/invoices/bulk-generate", [
                'building_id' => $this->building->id,
                'billing_month' => 6,
                'billing_year' => 2026,
            ])
            ->assertAccepted()
            ->assertJsonPath('status', true);

        Queue::assertPushed(BulkGenerateInvoicesJob::class);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->superAdmin->id,
            'action' => 'Xếp hàng tạo hóa đơn hàng loạt',
            'entity_type' => Building::class,
            'entity_id' => $this->building->id,
        ]);
    }

    private function createAdmin(string $username, int $role): Admin
    {
        return Admin::create([
            'username' => $username,
            'full_name' => ucfirst($username),
            'email' => "{$username}@stayhub.local",
            'phone' => '090'.random_int(1000000, 9999999),
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);
    }

    private function createAdminLog(
        Admin $admin,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?Carbon $createdAt = null,
    ): AdminLog {
        $log = AdminActivityLogger::write($admin, $action, $entityType, $entityId, $oldData, $newData);

        if ($createdAt) {
            $log->forceFill(['created_at' => $createdAt])->save();
        }

        return $log->refresh();
    }

    private function roomPayload(array $overrides = []): array
    {
        return [
            'building_id' => $this->building->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'A101',
            'floor' => 1,
            'area_m2' => '24.5',
            'base_price' => '2000000',
            'max_occupants' => 2,
            'description' => 'Phòng test audit log',
            'status' => Room::STATUS_ACTIVE,
            ...$overrides,
        ];
    }
}
