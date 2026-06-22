<?php

namespace Tests\Feature\Admin;

use App\Helpers\ImageHelper;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $managerAdmin;
    private Building $managedBuilding;
    private Building $otherBuilding;
    private Room $managedRoom;
    private Room $otherRoom;
    private ExpenseCategory $activeCategory;
    private ExpenseCategory $inactiveCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->createAdmin('superadmin_expense', Admin::ROLE_SUPER_ADMIN);
        $this->managerAdmin = $this->createAdmin('manager_expense', Admin::ROLE_BUILDING_MANAGER);

        $region = Region::create([
            'name' => 'Khu vực phiếu chi',
            'code' => 'EXPENSE_REGION',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->managedBuilding = Building::create([
            'region_id' => $region->id,
            'manager_admin_id' => $this->managerAdmin->id,
            'name' => 'Tòa manager',
            'slug' => 'toa-manager',
            'address' => '1 Test',
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->otherBuilding = Building::create([
            'region_id' => $region->id,
            'manager_admin_id' => $this->superAdmin->id,
            'name' => 'Tòa khác',
            'slug' => 'toa-khac',
            'address' => '2 Test',
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $roomType = RoomType::create([
            'name' => 'Phòng test phiếu chi',
            'slug' => 'phong-test-phieu-chi',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->managedRoom = $this->createRoom($this->managedBuilding, $roomType, 'A101');
        $this->otherRoom = $this->createRoom($this->otherBuilding, $roomType, 'B202');

        $this->activeCategory = ExpenseCategory::create([
            'name' => 'Sửa chữa',
            'description' => 'Chi phí sửa chữa',
            'is_active' => ExpenseCategory::ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->inactiveCategory = ExpenseCategory::create([
            'name' => 'Danh mục đã tắt',
            'description' => 'Không được chọn khi tạo phiếu chi',
            'is_active' => ExpenseCategory::INACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    protected function tearDown(): void
    {
        Expense::query()->get()->each(function (Expense $expense): void {
            collect($expense->receipt_images ?? [])->each(fn (string $path): bool => ImageHelper::delete($path));
        });

        parent::tearDown();
    }

    public function test_superadmin_can_create_update_show_and_cancel_expense_with_receipt_images(): void
    {
        $createResponse = $this->actingAs($this->superAdmin, 'admin')->post('/api/v1/admin/expenses', [
            'building_id' => $this->managedBuilding->id,
            'room_id' => $this->managedRoom->id,
            'expense_category_id' => $this->activeCategory->id,
            'title' => 'Sửa máy lạnh phòng A101',
            'amount' => '650000.00',
            'expense_date' => '2026-06-20',
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
            'note' => 'Thanh toán cho thợ sửa chữa',
            'receipt_images' => [UploadedFile::fake()->image('receipt.jpg')],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.amount', '650000.00')
            ->assertJsonPath('result.amount_formatted', '650.000 VNĐ')
            ->assertJsonPath('result.status', Expense::STATUS_RECORDED);

        $expenseId = (int) $createResponse->json('result.id');
        $expense = Expense::query()->findOrFail($expenseId);

        $this->assertStringStartsWith('EXP-', $expense->expense_code);
        $this->assertCount(1, $expense->receipt_images);

        $showResponse = $this->actingAs($this->superAdmin, 'admin')->getJson("/api/v1/admin/expenses/{$expenseId}");
        $showResponse->assertOk()
            ->assertJsonPath('result.id', $expenseId)
            ->assertJsonCount(1, 'result.receipt_image_urls');

        $updateResponse = $this->actingAs($this->superAdmin, 'admin')->post("/api/v1/admin/expenses/{$expenseId}", [
            '_method' => 'PATCH',
            'building_id' => $this->managedBuilding->id,
            'room_id' => null,
            'expense_category_id' => $this->activeCategory->id,
            'title' => 'Vệ sinh khu vực chung',
            'amount' => '1200000.50',
            'expense_date' => '2026-06-21',
            'payment_method' => Expense::PAYMENT_METHOD_BANK_TRANSFER,
            'note' => 'Cập nhật phiếu chi',
            'deleted_receipt_images' => $expense->receipt_images,
            'receipt_images' => [UploadedFile::fake()->image('receipt-new.png')],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('result.title', 'Vệ sinh khu vực chung')
            ->assertJsonPath('result.amount', '1200000.50')
            ->assertJsonPath('result.amount_formatted', '1.200.000 VNĐ')
            ->assertJsonCount(1, 'result.receipt_images');

        $cancelResponse = $this->actingAs($this->superAdmin, 'admin')->patchJson("/api/v1/admin/expenses/{$expenseId}/cancel");
        $cancelResponse->assertOk()
            ->assertJsonPath('result.status', Expense::STATUS_CANCELLED);

        $this->actingAs($this->superAdmin, 'admin')->post("/api/v1/admin/expenses/{$expenseId}", [
            '_method' => 'PATCH',
            'building_id' => $this->managedBuilding->id,
            'title' => 'Không được sửa',
            'amount' => '1.00',
            'expense_date' => '2026-06-21',
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
        ])->assertUnprocessable();
    }

    public function test_building_manager_only_accesses_own_building_expenses(): void
    {
        $ownExpense = $this->createExpense($this->managedBuilding, $this->managedRoom, 'EXP-2026-06-9001');
        $otherExpense = $this->createExpense($this->otherBuilding, $this->otherRoom, 'EXP-2026-06-9002');

        $indexResponse = $this->actingAs($this->managerAdmin, 'admin')->getJson('/api/v1/admin/expenses?per_page=20');
        $indexResponse->assertOk()
            ->assertJsonPath('result.pagination.total', 1)
            ->assertJsonPath('result.data.0.id', $ownExpense->id);

        $this->actingAs($this->managerAdmin, 'admin')
            ->getJson("/api/v1/admin/expenses/{$otherExpense->id}")
            ->assertNotFound();

        $this->actingAs($this->managerAdmin, 'admin')->postJson('/api/v1/admin/expenses', [
            'building_id' => $this->otherBuilding->id,
            'expense_category_id' => $this->activeCategory->id,
            'title' => 'Chi ngoài quyền',
            'amount' => '100000.00',
            'expense_date' => '2026-06-21',
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
        ])->assertForbidden();

        $this->actingAs($this->managerAdmin, 'admin')->postJson('/api/v1/admin/expenses', [
            'building_id' => $this->managedBuilding->id,
            'room_id' => $this->managedRoom->id,
            'expense_category_id' => $this->activeCategory->id,
            'title' => 'Chi trong quyền',
            'amount' => '250000.00',
            'expense_date' => '2026-06-21',
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
        ])->assertCreated();

        $this->actingAs($this->managerAdmin, 'admin')
            ->patchJson("/api/v1/admin/expenses/{$otherExpense->id}/cancel")
            ->assertNotFound();
    }

    public function test_expense_validation_rejects_invalid_room_category_amount_and_date_range(): void
    {
        $basePayload = [
            'building_id' => $this->managedBuilding->id,
            'expense_category_id' => $this->activeCategory->id,
            'title' => 'Phiếu validation',
            'amount' => '100000.00',
            'expense_date' => '2026-06-21',
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
        ];

        $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/expenses', array_merge($basePayload, [
            'room_id' => $this->otherRoom->id,
        ]))->assertUnprocessable();

        $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/expenses', array_merge($basePayload, [
            'expense_category_id' => $this->inactiveCategory->id,
        ]))->assertUnprocessable();

        $this->actingAs($this->superAdmin, 'admin')->postJson('/api/v1/admin/expenses', array_merge($basePayload, [
            'amount' => '-1',
        ]))->assertUnprocessable();

        $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/expenses?expense_date_from=2026-06-22&expense_date_to=2026-06-21')
            ->assertUnprocessable();
    }

    private function createAdmin(string $username, int $role): Admin
    {
        return Admin::create([
            'username' => $username,
            'full_name' => ucwords(str_replace('_', ' ', $username)),
            'email' => $username.'@stayhub.local',
            'phone' => '09'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);
    }

    private function createRoom(Building $building, RoomType $roomType, string $roomNumber): Room
    {
        return Room::create([
            'building_id' => $building->id,
            'room_type_id' => $roomType->id,
            'room_number' => $roomNumber,
            'slug' => strtolower($roomNumber),
            'floor' => 1,
            'area_m2' => '25.00',
            'base_price' => '3000000.00',
            'max_occupants' => 4,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'description' => 'Phòng test phiếu chi',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    private function createExpense(Building $building, Room $room, string $code): Expense
    {
        return Expense::create([
            'expense_code' => $code,
            'building_id' => $building->id,
            'room_id' => $room->id,
            'expense_category_id' => $this->activeCategory->id,
            'title' => 'Phiếu chi '.$code,
            'amount' => '100000.00',
            'expense_date' => '2026-06-20',
            'receipt_images' => [],
            'payment_method' => Expense::PAYMENT_METHOD_CASH,
            'note' => 'Test scope',
            'status' => Expense::STATUS_RECORDED,
            'created_by' => $this->superAdmin->id,
        ]);
    }
}
