# Cancel Scheduled Room Transfer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm nghiệp vụ hủy lịch chuyển phòng đã lên lịch nhưng chưa thực thi, hủy theo toàn bộ `transfer_code`, không phá luồng lên lịch, đổi ngày, cron thực thi, chốt điện nước, quyết toán cọc và realtime tenant.

**Architecture:** Dùng sẵn `room_movements.status = RoomMovement::STATUS_CANCELLED` nên không đổi schema. API hủy nằm trong `RoomMovementController`, khóa toàn bộ nhóm `transfer_code` bằng transaction, chỉ cho hủy lịch chuyển phòng `PENDING/BLOCKED`, ghi log admin, tạo notification hủy cho từng tenant và broadcast `NotificationSent` sau commit. FE web thêm service/helper/action xác nhận hủy trong màn `room-movements`; mobile admin hiện chỉ tạo lịch chuyển nên không bắt buộc đổi nếu chưa có màn lịch sử phòng.

**Tech Stack:** Laravel 13, MySQL/SQLite testing, Eloquent, FormRequest, API Resources, Sanctum admin guard, Reverb/Soketi realtime via `NotificationSent`, React/Vite TypeScript.

---

## Current State Notes

- Backend đã có `RoomMovement::STATUS_CANCELLED = 4` và label `Đã hủy` trong `BE_StayHub/app/Models/RoomMovement.php`.
- `room_movements` đã có đủ cột để hủy mềm: `status`, `failure_reason`, `scheduled_payload`, `executed_at`, `transfer_code`; không cần migration.
- Lên lịch chuyển phòng tạo một hoặc nhiều rows cùng `transfer_code` trong `BE_StayHub/app/Http/Controllers/Admin/RoomController.php` và chưa tạo hợp đồng mới/cọc/payment trước khi execute.
- Cron `room-transfers:execute-scheduled` chỉ lấy `PENDING/BLOCKED`, nên chuyển sang `CANCELLED` là đủ để cron bỏ qua.
- Đổi ngày hiện đang reset nhóm `PENDING/BLOCKED` về `PENDING`; sau hủy phải chặn đổi ngày vì status không còn nằm trong danh sách này.
- Chốt điện nước và nhắc cutoff cũng chỉ query `PENDING/BLOCKED`, nên lịch đã hủy tự động bị loại khỏi luồng nhắc/chốt.
- `OperationalStateGuard` chặn đổi trạng thái phòng/tòa nếu còn `PENDING/BLOCKED`; sau hủy phòng/tòa được mở khóa đúng nghiệp vụ.
- FE đã khai báo `MOVEMENT_STATUS_CANCELLED = 4` và có badge `Đã hủy`, nhưng chưa có action/API hủy.
- Realtime hiện dùng `NotificationSent` broadcast tới private channel `tenant.{tenant_id}` cho `TARGET_TYPE_TENANT`; plan bắt buộc tạo notification riêng cho từng tenant trong nhóm hủy và dispatch đủ event sau commit.

## Target Business Rules

1. Chỉ admin có quyền xem/sửa room movements được hủy lịch chuyển phòng.
2. Building manager chỉ hủy được lịch thuộc tòa nhà mình quản lý theo cùng scope `baseQuery($admin)` hiện có.
3. Chỉ hủy được `movement_type = TRANSFER`, có `transfer_code`, status là `PENDING` hoặc `BLOCKED`.
4. Hủy theo cả nhóm `transfer_code`, không hủy lẻ từng tenant để tránh một mã chuyển bị nửa hủy nửa chờ xử lý.
5. Không hủy lịch đã `EXECUTED`, vì lúc đó đã phát sinh hợp đồng đích, contract_tenants, contract_deposit_transactions, room_service_prices, occupant counts và settlement.
6. Không xóa row `room_movements`; giữ lịch sử audit bằng `STATUS_CANCELLED` và `failure_reason` dạng `Đã hủy bởi admin <name>. Lý do: <note>`.
7. Không chỉnh `contract_tenants`, `contracts`, `rooms.current_occupants`, `contract_deposit_transactions`, `payments`, `invoices`, `room_service_prices` khi hủy lịch chưa thực thi.
8. Sau hủy, khách thuê trong lịch có thể được lên lịch chuyển phòng mới vì các guard chỉ chặn `PENDING/BLOCKED`.
9. Tenant phải nhận realtime đầy đủ: mỗi tenant trong nhóm có một record `notifications` target tenant và một event `NotificationSent` được broadcast sau commit.
10. Admin cũng nhận/nhìn thấy notification hủy lịch để đối soát; broadcast tới building channel nếu có building, hoặc `admin-super` nếu không xác định được building.

## Files Map

### Backend API
- Modify: `BE_StayHub/routes/api_v1.php`
- Modify: `BE_StayHub/app/Http/Controllers/Admin/RoomMovementController.php`
- Create: `BE_StayHub/app/Http/Requests/Admin/RoomMovement/CancelTransferRequest.php`
- Modify: `BE_StayHub/app/Helpers/AdminActivityLogger.php`

### Backend Tests
- Modify: `BE_StayHub/tests/Feature/Admin/RoomMovementControllerTest.php`

### Frontend Web
- Modify: `FE_StayHub/src/features/admin/room-movements/services/room-movements.service.ts`
- Modify: `FE_StayHub/src/features/admin/room-movements/types/room-movement-api.model.ts`
- Modify: `FE_StayHub/src/features/admin/room-movements/utils/transfer-date.helpers.ts`
- Modify: `FE_StayHub/src/features/admin/room-movements/components/room-movements-screen.tsx`

### Mobile
- No required change now: `FE_StayHub_Mobile/lib/controllers/contract_controller.dart` only calls `/admin/room-transfers/tenant` to create schedule; no admin room-movements list/cancel surface found.

---

## Task 1: Backend Cancel Contract Tests

**Files:**
- Modify: `BE_StayHub/tests/Feature/Admin/RoomMovementControllerTest.php`

- [ ] **Step 1: Add happy-path group cancel test**

Append this test near existing transfer-date tests, before `test_update_transfer_date_rejects_executed_and_past_dates`:

```php
    public function test_admin_can_cancel_pending_transfer_group_and_broadcast_tenant_notifications(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Ho_Chi_Minh'));
        Event::fake([NotificationSent::class]);

        $admin = $this->createAdmin('super_cancel_transfer', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa hủy lịch', 'toa-huy-lich');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'H101', 2);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'H202', 0);
        $tenantA = $this->createTenant($admin, $building, 'tenant_cancel_a');
        $tenantB = $this->createTenant($admin, $building, 'tenant_cancel_b');
        $contract = $this->createContract($fromRoom, $admin, 'HD-CANCEL-TRANSFER');
        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $tenantA->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);
        ContractTenant::create([
            'contract_id' => $contract->id,
            'tenant_id' => $tenantB->id,
            'join_date' => '2026-01-01',
            'billing_start_date' => '2026-01-01',
            'is_staying' => true,
            'created_by' => $admin->id,
        ]);

        $transferCode = 'TRF-2026-07-CANCEL';
        $movementA = RoomMovement::create($this->movementPayload($tenantA, $contract, $fromRoom, $toRoom, $admin, $transferCode, '2026-07-20', RoomMovement::STATUS_PENDING));
        $movementB = RoomMovement::create($this->movementPayload($tenantB, $contract, $fromRoom, $toRoom, $admin, $transferCode, '2026-07-20', RoomMovement::STATUS_BLOCKED, 'Hóa đơn cuối chưa thanh toán'));

        $response = $this->actingAs($admin, 'admin')->patchJson("/api/v1/admin/room-movements/{$movementA->id}/cancel-transfer", [
            'note' => 'Khách đổi ý không chuyển nữa',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Hủy lịch chuyển phòng thành công')
            ->assertJsonPath('result.transfer_code', $transferCode)
            ->assertJsonPath('result.movement.status', RoomMovement::STATUS_CANCELLED)
            ->assertJsonPath('result.movements.0.status', RoomMovement::STATUS_CANCELLED)
            ->assertJsonPath('result.movements.1.status', RoomMovement::STATUS_CANCELLED);

        foreach ([$movementA, $movementB] as $movement) {
            $movement->refresh();

            $this->assertSame(RoomMovement::STATUS_CANCELLED, (int) $movement->status);
            $this->assertStringContainsString('Khách đổi ý không chuyển nữa', (string) $movement->failure_reason);
            $this->assertSame('Khách đổi ý không chuyển nữa', $movement->scheduled_payload['cancel_note'] ?? null);
            $this->assertSame('2026-07-10 09:00:00', $movement->scheduled_payload['cancelled_at'] ?? null);
            $this->assertSame($admin->id, $movement->scheduled_payload['cancelled_by'] ?? null);
            $this->assertNull($movement->destination_contract_id);
            $this->assertNull($movement->executed_at);
        }

        $this->assertDatabaseHas('notifications', [
            'title' => 'Lịch chuyển phòng đã bị hủy',
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'tenant_id' => $tenantA->id,
            'status' => Notification::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Lịch chuyển phòng đã bị hủy',
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'tenant_id' => $tenantB->id,
            'status' => Notification::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Admin đã hủy lịch chuyển phòng',
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'building_id' => $toRoom->building_id,
            'status' => Notification::STATUS_SENT,
        ]);

        Event::assertDispatched(NotificationSent::class, 3);
    }
```

- [ ] **Step 2: Add rejection tests for unsafe statuses**

Append this test after the happy-path test:

```php
    public function test_cancel_transfer_rejects_executed_cancelled_and_non_transfer_movements(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Ho_Chi_Minh'));

        $admin = $this->createAdmin('super_cancel_transfer_invalid', Admin::ROLE_SUPER_ADMIN);
        $building = $this->createBuilding($admin, 'Tòa chặn hủy lịch', 'toa-chan-huy-lich');
        $roomType = $this->createRoomType($admin);
        $fromRoom = $this->createRoom($building, $roomType, $admin, 'HC101', 1);
        $toRoom = $this->createRoom($building, $roomType, $admin, 'HC202', 0);
        $tenant = $this->createTenant($admin, $building, 'tenant_cancel_invalid');
        $contract = $this->createContract($fromRoom, $admin, 'HD-CANCEL-INVALID');

        $executedMovement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $contract, $fromRoom, $toRoom, $admin, 'TRF-2026-07-EXECUTED-CANCEL', '2026-07-20', RoomMovement::STATUS_EXECUTED),
            ['executed_at' => '2026-07-20 00:10:00']
        ));

        $this->actingAs($admin, 'admin')->patchJson("/api/v1/admin/room-movements/{$executedMovement->id}/cancel-transfer", [
            'note' => 'Không được hủy',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ được hủy lịch chuyển phòng chưa thực thi');

        $cancelledMovement = RoomMovement::create($this->movementPayload($tenant, $contract, $fromRoom, $toRoom, $admin, 'TRF-2026-07-CANCELLED-AGAIN', '2026-07-21', RoomMovement::STATUS_CANCELLED));

        $this->actingAs($admin, 'admin')->patchJson("/api/v1/admin/room-movements/{$cancelledMovement->id}/cancel-transfer")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Lịch chuyển phòng đã được hủy trước đó');

        $checkoutMovement = RoomMovement::create(array_merge(
            $this->movementPayload($tenant, $contract, $fromRoom, $toRoom, $admin, 'TRF-2026-07-CHECKOUT-CANCEL', '2026-07-22', RoomMovement::STATUS_PENDING),
            [
                'transfer_code' => null,
                'movement_type' => RoomMovement::MOVEMENT_TYPE_CHECKOUT,
            ]
        ));

        $this->actingAs($admin, 'admin')->patchJson("/api/v1/admin/room-movements/{$checkoutMovement->id}/cancel-transfer")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ được hủy lịch chuyển phòng');
    }
```

- [ ] **Step 3: Add permission-scope test**

Append this test after the rejection test:

```php
    public function test_building_manager_cannot_cancel_transfer_outside_managed_building(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Ho_Chi_Minh'));

        $ownerAdmin = $this->createAdmin('owner_cancel_scope', Admin::ROLE_BUILDING_MANAGER);
        $otherAdmin = $this->createAdmin('other_cancel_scope', Admin::ROLE_BUILDING_MANAGER);
        $ownerBuilding = $this->createBuilding($ownerAdmin, 'Tòa chủ quản hủy', 'toa-chu-quan-huy');
        $otherBuilding = $this->createBuilding($otherAdmin, 'Tòa khác hủy', 'toa-khac-huy');
        $roomType = $this->createRoomType($ownerAdmin);
        $fromRoom = $this->createRoom($ownerBuilding, $roomType, $ownerAdmin, 'S101', 1);
        $toRoom = $this->createRoom($ownerBuilding, $roomType, $ownerAdmin, 'S202', 0);
        $tenant = $this->createTenant($ownerAdmin, $ownerBuilding, 'tenant_cancel_scope');
        $contract = $this->createContract($fromRoom, $ownerAdmin, 'HD-CANCEL-SCOPE');
        $movement = RoomMovement::create($this->movementPayload($tenant, $contract, $fromRoom, $toRoom, $ownerAdmin, 'TRF-2026-07-SCOPE-CANCEL', '2026-07-20', RoomMovement::STATUS_PENDING));

        $this->assertSame($otherAdmin->id, (int) $otherBuilding->manager_admin_id);

        $this->actingAs($otherAdmin, 'admin')->patchJson("/api/v1/admin/room-movements/{$movement->id}/cancel-transfer", [
            'note' => 'Không có quyền',
        ])->assertStatus(404)
            ->assertJsonPath('message', 'Không tìm thấy lịch chuyển phòng');

        $movement->refresh();
        $this->assertSame(RoomMovement::STATUS_PENDING, (int) $movement->status);
    }
```

- [ ] **Step 4: Run targeted tests and verify fail first**

Run:

```bash
cd BE_StayHub && php artisan test --filter=RoomMovementControllerTest
```

Expected before implementation: FAIL because route `/cancel-transfer` does not exist.

---

## Task 2: Backend Cancel API

**Files:**
- Create: `BE_StayHub/app/Http/Requests/Admin/RoomMovement/CancelTransferRequest.php`
- Modify: `BE_StayHub/routes/api_v1.php`
- Modify: `BE_StayHub/app/Helpers/AdminActivityLogger.php`
- Modify: `BE_StayHub/app/Http/Controllers/Admin/RoomMovementController.php`

- [ ] **Step 1: Create request validation**

Create `BE_StayHub/app/Http/Requests/Admin/RoomMovement/CancelTransferRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin\RoomMovement;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CancelTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.string' => 'Lý do hủy lịch chuyển phòng phải là chuỗi ký tự.',
            'note.max' => 'Lý do hủy lịch chuyển phòng không được vượt quá 500 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
```

- [ ] **Step 2: Add route before show route**

Modify `BE_StayHub/routes/api_v1.php` around the room movement routes:

```php
        Route::get('room-movements', [RoomMovementController::class, 'index']);
        Route::patch('room-movements/{roomMovement}/transfer-date', [RoomMovementController::class, 'updateTransferDate']);
        Route::patch('room-movements/{roomMovement}/cancel-transfer', [RoomMovementController::class, 'cancelTransfer']);
        Route::post('room-movements/{roomMovement}/settlement-cash-payment', [RoomMovementController::class, 'recordSettlementCashPayment']);
        Route::get('room-movements/{roomMovement}', [RoomMovementController::class, 'show']);
```

- [ ] **Step 3: Add admin log action label**

Modify `BE_StayHub/app/Helpers/AdminActivityLogger.php` in `ACTION_LABELS` near room transfer actions:

```php
        'cancel_room_transfer_schedule' => 'Hủy lịch chuyển phòng',
```

- [ ] **Step 4: Import request class**

Modify imports in `BE_StayHub/app/Http/Controllers/Admin/RoomMovementController.php`:

```php
use App\Http\Requests\Admin\RoomMovement\CancelTransferRequest;
```

- [ ] **Step 5: Add cancelTransfer controller method**

Add this method in `RoomMovementController` after `updateTransferDate()` and before `recordSettlementCashPayment()`:

```php
    // Hủy lịch chuyển phòng chưa thực thi
    public function cancelTransfer(CancelTransferRequest $request, int $roomMovement): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewRoomMovements($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền hủy lịch chuyển phòng', 403, null, 403);
            }

            $notifications = collect();

            $result = DB::transaction(function () use ($validated, $admin, $request, $roomMovement, &$notifications): array {
                $movement = $this->baseQuery($admin)
                    ->whereKey($roomMovement)
                    ->lockForUpdate()
                    ->first();

                if (! $movement) {
                    return ['error' => ApiResponse::responseJson(false, 'Không tìm thấy lịch chuyển phòng', 404, null, 404)];
                }

                if ((int) $movement->movement_type !== RoomMovement::MOVEMENT_TYPE_TRANSFER || blank($movement->transfer_code)) {
                    return ['error' => ApiResponse::responseJson(false, 'Chỉ được hủy lịch chuyển phòng', 422, null, 422)];
                }

                if ((int) $movement->status === RoomMovement::STATUS_CANCELLED) {
                    return ['error' => ApiResponse::responseJson(false, 'Lịch chuyển phòng đã được hủy trước đó', 422, null, 422)];
                }

                if (! in_array((int) $movement->status, [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED], true)) {
                    return ['error' => ApiResponse::responseJson(false, 'Chỉ được hủy lịch chuyển phòng chưa thực thi', 422, null, 422)];
                }

                $movements = $this->baseQuery($admin)
                    ->where('transfer_code', $movement->transfer_code)
                    ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
                    ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
                    ->lockForUpdate()
                    ->get();

                if ($movements->isEmpty()) {
                    return ['error' => ApiResponse::responseJson(false, 'Không tìm thấy lịch chuyển phòng có thể hủy', 404, null, 404)];
                }

                $movementIds = $movements->pluck('id');
                $note = trim((string) ($validated['note'] ?? ''));
                $cancelledAt = now('Asia/Ho_Chi_Minh')->toDateTimeString();
                $reason = filled($note)
                    ? "Đã hủy bởi admin {$admin->full_name}. Lý do: {$note}"
                    : "Đã hủy bởi admin {$admin->full_name}.";

                $oldData = $movements->map(fn (RoomMovement $scheduledMovement): array => [
                    'id' => $scheduledMovement->id,
                    'status' => (int) $scheduledMovement->status,
                    'failure_reason' => $scheduledMovement->failure_reason,
                    'scheduled_payload' => $scheduledMovement->scheduled_payload,
                ])->values()->all();

                $movements->each(function (RoomMovement $scheduledMovement) use ($admin, $note, $cancelledAt, $reason): void {
                    $payload = $scheduledMovement->scheduled_payload ?? [];
                    $payload['cancelled_at'] = $cancelledAt;
                    $payload['cancelled_by'] = $admin->id;

                    if (filled($note)) {
                        $payload['cancel_note'] = $note;
                    } else {
                        unset($payload['cancel_note']);
                    }

                    $scheduledMovement->forceFill([
                        'status' => RoomMovement::STATUS_CANCELLED,
                        'failure_reason' => $reason,
                        'scheduled_payload' => $payload,
                    ])->save();
                });

                $freshMovements = $this->baseQuery($admin)
                    ->whereKey($movementIds)
                    ->orderBy('id')
                    ->get();

                AdminActivityLogger::write($admin, 'Hủy lịch chuyển phòng', RoomMovement::class, $movement->id, [
                    'transfer_code' => $movement->transfer_code,
                    'movements' => $oldData,
                ], [
                    'transfer_code' => $movement->transfer_code,
                    'status' => RoomMovement::STATUS_CANCELLED,
                    'note' => $note ?: null,
                    'cancelled_at' => $cancelledAt,
                    'movement_ids' => $movementIds->values()->all(),
                ], $request);

                $notifications = $this->createTransferCancelledNotifications($freshMovements, $admin, $note ?: null);

                return [
                    'movement' => $freshMovements->firstWhere('id', $movement->id) ?: $freshMovements->first(),
                    'movements' => $freshMovements,
                    'transfer_code' => $movement->transfer_code,
                ];
            });

            if (isset($result['error'])) {
                return $result['error'];
            }

            DB::afterCommit(fn (): mixed => $this->broadcastNotifications($notifications));

            return ApiResponse::responseJson(true, 'Hủy lịch chuyển phòng thành công', 200, [
                'transfer_code' => $result['transfer_code'],
                'movement' => new RoomMovementResource($result['movement']),
                'movements' => RoomMovementResource::collection($result['movements'])->resolve(),
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }
```

- [ ] **Step 6: Add realtime notification builder**

Add this private method near `createTransferDateChangedNotifications()` in `RoomMovementController`:

```php
    // Tạo thông báo realtime khi hủy lịch chuyển phòng
    private function createTransferCancelledNotifications(EloquentCollection $movements, Admin $admin, ?string $note): Collection
    {
        $firstMovement = $movements->first();
        $transferCode = (string) $firstMovement?->transfer_code;
        $fromRoom = $firstMovement?->fromRoom?->room_number ?? 'phòng cũ';
        $toRoom = $firstMovement?->toRoom?->room_number ?? 'phòng mới';
        $movementDateText = $firstMovement?->movement_date?->format('d/m/Y') ?? 'chưa rõ';
        $noteText = filled($note) ? " Lý do: {$note}" : '';

        $tenantNotifications = $movements
            ->unique('tenant_id')
            ->map(fn (RoomMovement $movement): Notification => Notification::query()->create([
                'title' => 'Lịch chuyển phòng đã bị hủy',
                'content' => "Lịch chuyển phòng {$transferCode} từ {$fromRoom} sang {$toRoom} ngày {$movementDateText} đã bị hủy.{$noteText}",
                'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'action_url' => '/admin/room-movements?movement_id=' . $movement->id,
                'building_id' => $movement->toRoom?->building_id ?? $movement->fromRoom?->building_id,
                'room_id' => $movement->to_room_id ?? $movement->from_room_id,
                'tenant_id' => $movement->tenant_id,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]));

        $adminNotification = Notification::query()->create([
            'title' => 'Admin đã hủy lịch chuyển phòng',
            'content' => "Admin {$admin->full_name} đã hủy lịch chuyển phòng {$transferCode} từ {$fromRoom} sang {$toRoom} ngày {$movementDateText}.{$noteText}",
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'action_url' => $firstMovement ? '/admin/room-movements?movement_id=' . $firstMovement->id : '/admin/room-movements',
            'building_id' => $firstMovement?->toRoom?->building_id ?? $firstMovement?->fromRoom?->building_id,
            'room_id' => $firstMovement?->to_room_id ?? $firstMovement?->from_room_id,
            'target_admin_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]);

        return $tenantNotifications->push($adminNotification)->values();
    }
```

Important realtime requirement: keep `target_type = Notification::TARGET_TYPE_TENANT` and one notification per unique `tenant_id`. Do not replace this with a single room/building/all notification because tenant private channel delivery would become ambiguous and tests must assert exact event count.

- [ ] **Step 7: Run targeted backend tests**

Run:

```bash
cd BE_StayHub && php artisan test --filter=RoomMovementControllerTest
```

Expected after implementation: PASS.

---

## Task 3: Frontend API Types And Helpers

**Files:**
- Modify: `FE_StayHub/src/features/admin/room-movements/types/room-movement-api.model.ts`
- Modify: `FE_StayHub/src/features/admin/room-movements/services/room-movements.service.ts`
- Modify: `FE_StayHub/src/features/admin/room-movements/utils/transfer-date.helpers.ts`

- [ ] **Step 1: Add cancel payload/result types**

Modify `FE_StayHub/src/features/admin/room-movements/types/room-movement-api.model.ts` after `AdminUpdateRoomMovementTransferDateResult`:

```ts
export interface AdminCancelRoomMovementTransferPayload {
  note?: string
}

export interface AdminCancelRoomMovementTransferResult {
  transfer_code?: string | null
  movement: AdminRoomMovementResource
  movements: AdminRoomMovementResource[]
}
```

- [ ] **Step 2: Add cancel service**

Modify imports and add this function in `FE_StayHub/src/features/admin/room-movements/services/room-movements.service.ts` after `updateAdminRoomMovementTransferDate()`:

```ts
export async function cancelAdminRoomMovementTransfer(roomMovementId: number, payload: AdminCancelRoomMovementTransferPayload = {}) {
  return apiRequest<AdminCancelRoomMovementTransferResult>({
    url: `admin/room-movements/${roomMovementId}/cancel-transfer`,
    method: 'PATCH',
    data: payload,
  })
}
```

The type import must include `AdminCancelRoomMovementTransferPayload` and `AdminCancelRoomMovementTransferResult`.

- [ ] **Step 3: Add cancel eligibility helper**

Modify `FE_StayHub/src/features/admin/room-movements/utils/transfer-date.helpers.ts`:

```ts
export function canCancelTransferSchedule(movement: Pick<AdminRoomMovementResource, 'movement_type' | 'status' | 'transfer_code'>) {
  return movement.movement_type === MOVEMENT_TRANSFER
    && Boolean(movement.transfer_code)
    && [MOVEMENT_STATUS_PENDING, MOVEMENT_STATUS_BLOCKED].includes(Number(movement.status))
}
```

- [ ] **Step 4: Run frontend type check expected fail before component wiring**

Run:

```bash
cd FE_StayHub && npm run build
```

Expected before Task 4: may still PASS because new exports are unused; any import mistakes must be fixed before continuing.

---

## Task 4: Frontend Cancel UI

**Files:**
- Modify: `FE_StayHub/src/features/admin/room-movements/components/room-movements-screen.tsx`

- [ ] **Step 1: Add imports**

Update imports at the top:

```ts
import { AlertTriangle, ArrowDown, ArrowRightLeft, CalendarDays, CalendarPlus2, ChevronLeft, ChevronRight, Clock3, Eye, FilterX, HandCoins, History, Loader2, ReceiptText, Search, Sparkles, Trash2, X } from 'lucide-react'
import { ConfirmModal } from '../../../../shared/components/ConfirmModal'
import { useConfirmModal } from '../../../../shared/lib/hooks/use-confirm-modal'
import { cancelAdminRoomMovementTransfer, fetchAdminRoomMovementDetail, fetchAdminRoomMovements, recordAdminRoomMovementSettlementCashPayment, updateAdminRoomMovementTransferDate } from '../services/room-movements.service'
import { canCancelTransferSchedule, canUpdateTransferDate, toDateInputValue } from '../utils/transfer-date.helpers'
```

- [ ] **Step 2: Add confirmation state and success message**

Inside `RoomMovementsScreen()`, after `currentAdmin`:

```ts
  const { confirmState, isConfirmLoading, setIsConfirmLoading, showConfirm, closeConfirm } = useConfirmModal()
```

Add state near existing error states:

```ts
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
```

Clear success in `loadMovements()` only on hard filter reload if desired; do not clear it immediately after cancel success before render.

- [ ] **Step 3: Add cancel handler**

Add this function after `submitTransferDate()` and before `submitCashPayment()`:

```ts
  function openCancelTransferSchedule(movement: AdminRoomMovementResource) {
    if (!canCancelTransferSchedule(movement)) return

    const transferCode = movement.transfer_code || `#${movement.id}`

    showConfirm({
      title: 'Hủy lịch chuyển phòng',
      message: `Bạn có chắc muốn hủy toàn bộ lịch chuyển phòng ${transferCode}? Khách thuê sẽ nhận thông báo realtime và lịch này sẽ không được cron thực hiện.`,
      confirmLabel: 'Hủy lịch',
      cancelLabel: 'Giữ lịch',
      variant: 'danger',
      onConfirm: async () => {
        try {
          setIsConfirmLoading(true)
          setErrorMessage(null)
          setSuccessMessage(null)

          const response = await cancelAdminRoomMovementTransfer(movement.id)
          const freshMovement = response.result?.movement

          if (freshMovement) {
            setSelectedMovement(freshMovement)
          }

          setSuccessMessage(`Đã hủy lịch chuyển phòng ${transferCode}. Tenant đã được gửi realtime notification.`)
          await loadMovements()
        } catch (error) {
          setErrorMessage(getVisibleErrorMessage(error, 'Không thể hủy lịch chuyển phòng.'))
        } finally {
          setIsConfirmLoading(false)
          closeConfirm()
        }
      },
    })
  }
```

Keep note optional in v1. If product wants an admin-entered reason later, replace confirm modal with a dedicated cancel modal using the same endpoint `note` field.

- [ ] **Step 4: Add success alert**

Near existing error alert at table section:

```tsx
          {successMessage && <div className="m-5 rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/10 p-4 text-sm font-black text-[#0f5f59]">{successMessage}</div>}
          {errorMessage && <div className="m-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>}
```

- [ ] **Step 5: Add cancel action in table**

In table actions, before the detail button and after reschedule button:

```tsx
                        {canCancelTransferSchedule(movement) && (
                          <button type="button" onClick={() => openCancelTransferSchedule(movement)} className="group/cancel inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 shadow-sm transition hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-100 hover:text-rose-800 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95" title="Hủy lịch chuyển" aria-label="Hủy lịch chuyển phòng">
                            <Trash2 className="h-4.5 w-4.5 transition group-hover/cancel:-rotate-6" />
                          </button>
                        )}
```

- [ ] **Step 6: Wire cancel action into detail modal**

Change the mounted detail modal props:

```tsx
        <DetailModal movement={selectedMovement} currentAdmin={currentAdmin} isLoading={isDetailLoading} errorMessage={detailErrorMessage} onClose={closeDetail} onOpenCashPayment={openCashPayment} onOpenTransferDate={openTransferDateEditor} onOpenCancelTransfer={openCancelTransferSchedule} />
```

Update `DetailModal` signature:

```ts
function DetailModal({ movement, currentAdmin, isLoading, errorMessage, onClose, onOpenCashPayment, onOpenTransferDate, onOpenCancelTransfer }: { movement: AdminRoomMovementResource; currentAdmin: AdminProfile | null; isLoading: boolean; errorMessage: string | null; onClose: () => void; onOpenCashPayment: (movement: AdminRoomMovementResource) => void; onOpenTransferDate: (movement: AdminRoomMovementResource) => void; onOpenCancelTransfer: (movement: AdminRoomMovementResource) => void }) {
```

Add eligibility constant:

```ts
  const canCancel = canCancelTransferSchedule(movement)
```

Below the reschedule button, add:

```tsx
                {canCancel && (
                  <button type="button" onClick={() => onOpenCancelTransfer(movement)} className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-[0.99]">
                    <Trash2 className="h-4.5 w-4.5" /> Hủy lịch chuyển phòng
                  </button>
                )}
```

- [ ] **Step 7: Mount ConfirmModal**

Before closing fragment `</>` in `RoomMovementsScreen`, after other modals:

```tsx
      <ConfirmModal {...confirmState} onCancel={closeConfirm} isLoading={isConfirmLoading} />
```

- [ ] **Step 8: Run frontend build**

Run:

```bash
cd FE_StayHub && npm run build
```

Expected: PASS with no TypeScript errors.

---

## Task 5: Realtime And Regression Verification

**Files:**
- No source changes unless verification exposes a bug.

- [ ] **Step 1: Verify route list**

Run:

```bash
cd BE_StayHub && php artisan route:list --path=room-movements
```

Expected includes:

```text
PATCH  api/v1/admin/room-movements/{roomMovement}/cancel-transfer
```

- [ ] **Step 2: Verify targeted backend tests**

Run:

```bash
cd BE_StayHub && php artisan test --filter=RoomMovementControllerTest
```

Expected: PASS.

- [ ] **Step 3: Verify transfer creation still works**

Run:

```bash
cd BE_StayHub && php artisan test --filter='test_transfer_tenant_schedules_then_executes_single_room_transfer_with_manual_refund'
```

Expected: PASS; confirms hủy API did not alter schedule/execute flow.

- [ ] **Step 4: Verify reschedule still works**

Run:

```bash
cd BE_StayHub && php artisan test --filter='test_update_transfer_date_updates_group_and_broadcasts_notifications'
```

Expected: PASS; confirms hủy helper did not break transfer-date flow.

- [ ] **Step 5: Verify frontend build**

Run:

```bash
cd FE_StayHub && npm run build
```

Expected: PASS.

- [ ] **Step 6: Manual realtime smoke test in Docker**

Use existing app stack and two browser/mobile sessions:

1. Login admin and tenant affected by a pending transfer.
2. Open tenant notification UI or mobile tenant session subscribed to `tenant.{id}`.
3. Admin opens `/admin/room-movements`, cancels a `PENDING/BLOCKED` transfer.
4. Verify admin sees success message and row status becomes `Đã hủy`.
5. Verify tenant receives realtime notification with title `Lịch chuyển phòng đã bị hủy` without refreshing.
6. Verify `notifications` table has one tenant notification per moving tenant and one admin notification.
7. Run `php artisan room-transfers:execute-scheduled --date=<movement_date> --code=<transfer_code>` and verify command reports no execution for cancelled code.

## Risk Controls

- Do not add migrations because `STATUS_CANCELLED` already exists and schema supports soft-cancel.
- Do not delete `room_movements` rows because history, notifications, audit and filters depend on them.
- Do not touch contracts/deposits/payments/invoices because scheduled transfers have not created those side effects yet.
- Do not allow cancelling `EXECUTED` transfers; reversal would need a separate rollback/liquidation design.
- Broadcast notifications only after DB commit so tenants never receive realtime for a rollbacked cancellation.
- Keep cancel group locked by `transfer_code` to avoid cron executing some rows while admin cancels others.

## Self-Review

- Spec coverage: route, FormRequest, controller transaction, group cancel, admin log, tenant realtime, FE action, tests, and regression verification are all covered.
- Placeholder scan: no task uses TBD/TODO or unspecified validation; code snippets include exact class/function names and messages.
- Type consistency: backend response shape matches existing `movement/movements/transfer_code`; FE result types mirror backend; helper eligibility matches backend statuses `PENDING/BLOCKED`.
