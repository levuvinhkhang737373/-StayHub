<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Building $building;
    private RoomType $roomType;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'username' => 'superadmin_room',
            'full_name' => 'Super Admin Room Test',
            'email' => 'superadmin_room@stayhub.local',
            'phone' => '0901234588',
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
            'name' => 'Building Room Test',
            'slug' => 'building-room-test',
            'address' => '123 Test St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
            'total_floors' => 5,
        ]);

        $this->roomType = RoomType::create([
            'name' => 'Standard Room Type',
            'slug' => 'standard-room-type',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->room = Room::create([
            'building_id' => $this->building->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'R101',
            'slug' => 'r101',
            'floor' => 1,
            'base_price' => '3000000.00',
            'max_occupants' => 3,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    private function getRoomPayload(array $overrides = []): array
    {
        return [
            'building_id' => $this->building->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'R101',
            'floor' => 1,
            'area_m2' => '25.0',
            'base_price' => '3000000',
            'max_occupants' => 3,
            'description' => 'Phòng test',
            'status' => Room::STATUS_ACTIVE,
            ...$overrides,
        ];
    }

    /**
     * Test cập nhật phòng thành công thông qua API update khi dữ liệu hợp lệ.
     */
    public function test_update_room_success_with_valid_data(): void
    {
        $payload = $this->getRoomPayload([
            'room_number' => 'R101-Updated',
            'status' => Room::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'room_number' => 'R101-Updated',
            'status' => Room::STATUS_ACTIVE,
        ]);
    }

    /**
     * Test API update chặn chuyển trạng thái sang ngưng hoạt động hoặc bảo trì
     * khi phòng có khách thuê ở (current_occupants > 0).
     */
    public function test_update_room_fails_to_deactivate_when_occupants_exist(): void
    {
        $this->room->update(['current_occupants' => 1]);

        // Thử chuyển sang Ngưng hoạt động (3)
        $payload = $this->getRoomPayload([
            'status' => Room::STATUS_INACTIVE,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}", $payload);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Không thể chuyển phòng sang trạng thái ngưng hoạt động khi đang có khách ở.');

        // Thử chuyển sang Bảo trì (2)
        $payload['status'] = Room::STATUS_MAINTENANCE;

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}", $payload);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Không thể chuyển phòng sang trạng thái bảo trì khi đang có khách ở.');
    }

    /**
     * Test API update chặn chuyển trạng thái sang ngưng hoạt động hoặc bảo trì
     * khi phòng có hợp đồng đang có hiệu lực.
     */
    public function test_update_room_fails_to_deactivate_when_active_contract_exists(): void
    {
        Contract::create([
            'contract_code' => 'HD-TEST-ROOM',
            'room_id' => $this->room->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'room_price' => '3000000.00',
            'deposit_amount' => '3000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // Thử chuyển sang Ngưng hoạt động
        $payload = $this->getRoomPayload([
            'status' => Room::STATUS_INACTIVE,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}", $payload);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Không thể chuyển phòng sang trạng thái ngưng hoạt động khi đang có hợp đồng hiệu lực.');
    }

    /**
     * Test API updateStatus cũng chặn chuyển trạng thái không hợp lệ.
     */
    public function test_update_status_endpoint_fails_when_occupants_or_contracts_exist(): void
    {
        $this->room->update(['current_occupants' => 2]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/api/v1/admin/rooms/{$this->room->id}/status", [
                'status' => Room::STATUS_MAINTENANCE,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Không thể chuyển phòng sang trạng thái bảo trì khi đang có khách ở.');
    }


    /**
     * Test API update chặn chuyển phòng sang bảo trì/ngưng khi có hợp đồng chờ ký giữ phòng.
     */
    public function test_update_room_fails_to_deactivate_when_pending_contract_exists(): void
    {
        Contract::create([
            'contract_code' => 'HD-PENDING-ROOM',
            'room_id' => $this->room->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'room_price' => '3000000.00',
            'deposit_amount' => '3500000.00',
            'status' => Contract::STATUS_PENDING_SIGN,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->putJson("/api/v1/admin/rooms/{$this->room->id}", $this->getRoomPayload([
                'status' => Room::STATUS_MAINTENANCE,
            ]));

        $response->assertStatus(400)
            ->assertJsonPath('status', false);

        $this->assertSame(Room::STATUS_ACTIVE, (int) $this->room->fresh()->status);
    }

}
