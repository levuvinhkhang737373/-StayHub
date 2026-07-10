# Room Service Prices Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chuẩn hóa 4 bảng `services`, `service_prices`, `room_services`, `room_service_prices` để bỏ `price` khỏi `room_services` và mọi luồng giá dịch vụ phòng đọc/ghi qua `room_service_prices` mà không gãy hóa đơn, hợp đồng, chuyển phòng.

**Architecture:** `services` chỉ là danh mục dịch vụ, `service_prices` là giá mặc định cấp tòa nhà, `room_services` chỉ nối phòng-dịch vụ, `room_service_prices` là nguồn sự thật duy nhất cho giá dịch vụ phòng theo thời gian/hợp đồng. Tạo helper tập trung `RoomServicePriceResolver` để các controller/resource/command dùng chung rule ưu tiên giá contract trước, rồi giá mặc định phòng, rồi fallback tạo seed từ giá tòa nhà trong migration/luồng tạo phòng.

**Tech Stack:** Laravel 13, MySQL/SQLite testing, Eloquent Models, FormRequest, API Resources, PHPUnit Feature tests, React/Vite frontend.

---

## Current State Notes

- `room_services.price` đang là fallback trong `BE_StayHub/app/Http/Controllers/Admin/InvoiceController.php:1429`, resource hợp đồng, API giá dịch vụ phòng và UI tạo hợp đồng.
- `room_service_prices` hiện có unique `['room_service_id', 'effective_from']`, chưa cho phép cùng ngày vừa có giá mặc định `contract_id = null` vừa có giá deal theo `contract_id`.
- Khi lập hợp đồng, FE đang lấy giá dịch vụ ban đầu từ `selectedRoom.services[].pivot.price`; sau refactor phải lấy từ API room options đã được backend map giá mặc định theo tòa nhà/phòng.
- Điện/nước theo chỉ số vẫn lấy từ `service_prices`; không đưa vào deal giá phòng/hợp đồng.
- Dịch vụ không theo chỉ số như internet/rác/vệ sinh/gửi xe phải lấy giá từ `room_service_prices`.

## Target Data Rules

1. `services`: chỉ lưu metadata dịch vụ.
2. `service_prices`: giá mặc định cấp tòa nhà và đặc biệt dùng cho điện/nước theo chỉ số.
3. `room_services`: chỉ lưu `room_id`, `service_id`, timestamps; unique `room_id + service_id`.
4. `room_service_prices`:
   - `contract_id = null`: giá mặc định của dịch vụ trong phòng.
   - `contract_id = <id>`: giá deal riêng theo hợp đồng.
   - Luôn lấy theo kỳ: `effective_from <= target_date` và `effective_to is null OR effective_to >= target_date`.
   - Ưu tiên: giá `contract_id = current contract` trước, nếu không có thì giá `contract_id = null`.
5. Khi tạo phòng mới, tạo `room_services` từ `service_prices` đang active của tòa nhà và tạo luôn `room_service_prices` mặc định `contract_id = null` cho các dịch vụ không phải điện/nước.
6. Khi lập hợp đồng:
   - UI ban đầu hiển thị giá cấp tòa nhà/giá mặc định phòng.
   - Nếu admin giữ nguyên giá hoặc deal xuống, backend lưu giá hợp đồng vào `room_service_prices` với `contract_id = contract.id`.
   - Không update `room_services.price` nữa.
7. Khi tạo hóa đơn:
   - Dịch vụ điện/nước lấy từ `service_prices` và meter readings như hiện tại.
   - Dịch vụ phòng không theo chỉ số lấy từ `room_service_prices` theo kỳ hóa đơn, không fallback `room_services.price`.
8. Khi chuyển phòng:
   - Copy giá dịch vụ từ contract nguồn sang contract mới bằng `room_service_prices` theo ngày hiệu lực chuyển phòng.
   - Nếu phòng đích chưa có `room_services` cho dịch vụ đó thì tạo record nối, nhưng không có `price`.
   - Đóng `effective_to` của giá cũ theo mốc chuyển phòng như hiện tại.

## Files Map

### Backend Schema/Models
- Modify: `BE_StayHub/database/migrations/2026_07_02_222000_create_room_services_table.php`
- Create: `BE_StayHub/database/migrations/2026_07_10_130000_backfill_room_service_prices_and_drop_room_services_price.php`
- Modify: `BE_StayHub/database/migrations/2026_07_10_120000_create_room_service_prices_table.php`
- Modify: `BE_StayHub/app/Models/RoomService.php`
- Modify: `BE_StayHub/app/Models/Room.php`
- Modify: `BE_StayHub/app/Models/RoomServicePrice.php`

### Backend Price Resolver
- Create: `BE_StayHub/app/Services/RoomServicePriceResolver.php`

### Backend Controllers/Resources/Commands
- Modify: `BE_StayHub/app/Http/Controllers/Admin/RoomController.php`
- Modify: `BE_StayHub/app/Http/Controllers/Admin/ContractController.php`
- Modify: `BE_StayHub/app/Http/Controllers/Admin/InvoiceController.php`
- Modify: `BE_StayHub/app/Http/Controllers/Admin/RoomServicePriceController.php`
- Modify: `BE_StayHub/app/Http/Resources/Admin/RoomServicePriceResource.php`
- Modify: `BE_StayHub/app/Http/Resources/Admin/ContractDetailResource.php`
- Modify: `BE_StayHub/app/Http/Resources/Tenant/ContractResource.php`
- Modify: `BE_StayHub/app/Http/Controllers/Tenant/ContractController.php`
- Modify: `BE_StayHub/app/Console/Commands/ExecuteScheduledRoomTransfers.php`
- Modify: `BE_StayHub/app/Console/Commands/CheckExpiredContracts.php` only if tests expose contract close scope gaps.

### Backend Validation
- Modify: `BE_StayHub/app/Http/Requests/Admin/Contract/RegisterRequest.php`
- Modify: `BE_StayHub/app/Http/Requests/Admin/Contract/UpdateRequest.php`
- Modify: `BE_StayHub/app/Http/Requests/Admin/RoomServicePrice/UpdateRequest.php` only if unique/scope validation needs better messages.

### Frontend
- Modify: `FE_StayHub/src/features/admin/contracts/components/create-contract-screen.tsx`
- Modify: `FE_StayHub/src/features/admin/contracts/types/contracts.model.ts` or nearest contract API type file if service pivot type exists.
- Modify: `FE_StayHub/src/features/admin/room-service-prices/types/room-service-price.model.ts`
- Modify: `FE_StayHub/src/features/admin/room-service-prices/components/room-service-prices-screen.tsx` only to rename labels if needed; keep `base_price` response key for compatibility.

### Tests
- Modify: `BE_StayHub/tests/Feature/Admin/RoomServicePriceTest.php`
- Modify: `BE_StayHub/tests/Feature/Admin/ContractControllerTest.php`
- Modify: `BE_StayHub/tests/Feature/Admin/InvoiceControllerTest.php`
- Modify/Create: `BE_StayHub/tests/Feature/Admin/RoomServicePriceSchemaTest.php` only if schema assertions should be isolated.
- Modify FE tests only if current tests reference `pivot.price` or `base_price` types.

---

## Task 1: Lock Expected Behavior With Failing Backend Tests

**Files:**
- Modify: `BE_StayHub/tests/Feature/Admin/RoomServicePriceTest.php`
- Modify: `BE_StayHub/tests/Feature/Admin/ContractControllerTest.php`
- Modify: `BE_StayHub/tests/Feature/Admin/InvoiceControllerTest.php`

- [ ] **Step 1: Add schema assertion that `room_services.price` is gone**

In `BE_StayHub/tests/Feature/Admin/RoomServicePriceTest.php`, replace the existing schema test with:

```php
public function test_room_services_table_does_not_store_price_column(): void
{
    $this->assertFalse(Schema::hasColumn('room_services', 'price'));
}

public function test_room_service_prices_table_does_not_store_status_column(): void
{
    $this->assertFalse(Schema::hasColumn('room_service_prices', 'status'));
}
```

- [ ] **Step 2: Add test that default room service price comes from `room_service_prices`**

In `BE_StayHub/tests/Feature/Admin/RoomServicePriceTest.php`, add after `test_index_returns_only_non_meter_room_services_for_accessible_rooms`:

```php
public function test_index_uses_room_service_prices_as_default_price_source(): void
{
    RoomServicePrice::query()->create([
        'room_service_id' => $this->internetRoomService->id,
        'contract_id' => null,
        'price' => '125000.00',
        'effective_from' => '2026-07-01',
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin, 'admin')
        ->getJson('/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026');

    $response->assertStatus(200);

    $room = collect($response->json('result.data'))->firstWhere('id', $this->room->id);
    $internet = collect($room['services'])->firstWhere('service_id', $this->internetService->id);

    $this->assertSame('125000.00', $internet['base_price']);
    $this->assertSame('125000.00', $internet['effective_price']);
}
```

- [ ] **Step 3: Add test for same date default and contract-scoped prices**

In `BE_StayHub/tests/Feature/Admin/RoomServicePriceTest.php`, add:

```php
public function test_room_service_prices_allows_default_and_contract_price_on_same_date(): void
{
    RoomServicePrice::query()->create([
        'room_service_id' => $this->internetRoomService->id,
        'contract_id' => null,
        'price' => '100000.00',
        'effective_from' => '2026-08-01',
        'created_by' => $this->superAdmin->id,
    ]);

    RoomServicePrice::query()->create([
        'room_service_id' => $this->internetRoomService->id,
        'contract_id' => $this->contract->id,
        'price' => '80000.00',
        'effective_from' => '2026-08-01',
        'created_by' => $this->superAdmin->id,
    ]);

    $this->assertSame(2, RoomServicePrice::query()
        ->where('room_service_id', $this->internetRoomService->id)
        ->whereDate('effective_from', '2026-08-01')
        ->count());
}
```

- [ ] **Step 4: Add invoice fallback test with no `room_services.price`**

In `BE_StayHub/tests/Feature/Admin/InvoiceControllerTest.php`, add or update a focused test:

```php
public function test_invoice_uses_default_room_service_price_when_contract_has_no_deal(): void
{
    $this->electricityService->update(['is_active' => false]);
    $this->waterService->update(['is_active' => false]);

    $internetService = Service::create([
        'name' => 'Internet default resolver',
        'slug' => 'internet-default-resolver',
        'charge_method' => Service::CHARGE_METHOD_FIXED,
        'unit_name' => 'tháng',
        'is_active' => true,
    ]);

    ServicePrice::create([
        'service_id' => $internetService->id,
        'building_id' => $this->building->id,
        'price' => '100000.00',
        'effective_from' => '2026-01-01',
        'status' => ServicePrice::STATUS_ACTIVE,
    ]);

    $roomService = RoomService::query()->create([
        'room_id' => $this->room->id,
        'service_id' => $internetService->id,
    ]);

    RoomServicePrice::query()->create([
        'room_service_id' => $roomService->id,
        'contract_id' => null,
        'price' => '110000.00',
        'effective_from' => '2026-01-01',
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/invoices/preview', [
            'contract_id' => $this->contract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

    $response->assertStatus(200);
    $item = collect($response->json('result.items'))->firstWhere('service_id', $internetService->id);

    $this->assertSame('110000.00', $item['unit_price']);
    $this->assertSame('110000.00', $item['amount']);
}
```

- [ ] **Step 5: Add invoice contract deal priority test**

In `BE_StayHub/tests/Feature/Admin/InvoiceControllerTest.php`, add:

```php
public function test_invoice_prefers_contract_scoped_room_service_price_over_default(): void
{
    $this->electricityService->update(['is_active' => false]);
    $this->waterService->update(['is_active' => false]);

    $internetService = Service::create([
        'name' => 'Internet deal resolver',
        'slug' => 'internet-deal-resolver',
        'charge_method' => Service::CHARGE_METHOD_FIXED,
        'unit_name' => 'tháng',
        'is_active' => true,
    ]);

    ServicePrice::create([
        'service_id' => $internetService->id,
        'building_id' => $this->building->id,
        'price' => '100000.00',
        'effective_from' => '2026-01-01',
        'status' => ServicePrice::STATUS_ACTIVE,
    ]);

    $roomService = RoomService::query()->create([
        'room_id' => $this->room->id,
        'service_id' => $internetService->id,
    ]);

    RoomServicePrice::query()->create([
        'room_service_id' => $roomService->id,
        'contract_id' => null,
        'price' => '110000.00',
        'effective_from' => '2026-01-01',
        'created_by' => $this->superAdmin->id,
    ]);

    RoomServicePrice::query()->create([
        'room_service_id' => $roomService->id,
        'contract_id' => $this->contract->id,
        'price' => '85000.00',
        'effective_from' => '2026-01-01',
        'effective_to' => '2026-12-31',
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/invoices/preview', [
            'contract_id' => $this->contract->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

    $response->assertStatus(200);
    $item = collect($response->json('result.items'))->firstWhere('service_id', $internetService->id);

    $this->assertSame('85000.00', $item['unit_price']);
    $this->assertSame('85000.00', $item['amount']);
}
```

- [ ] **Step 6: Add contract creation test that stores deal only in `room_service_prices`**

In `BE_StayHub/tests/Feature/Admin/RoomServicePriceTest.php`, extend `test_contract_deal_creates_contract_scoped_room_service_price_and_blocks_utilities` after asserting `room_service_prices`:

```php
$this->assertDatabaseHas('room_services', [
    'id' => $roomService->id,
    'room_id' => $this->otherRoom->id,
    'service_id' => $this->internetService->id,
]);

$this->assertFalse(Schema::hasColumn('room_services', 'price'));
```

- [ ] **Step 7: Run focused tests and verify they fail before implementation**

Run:

```bash
cd BE_StayHub
php artisan test tests/Feature/Admin/RoomServicePriceTest.php tests/Feature/Admin/InvoiceControllerTest.php --filter='room_service|RoomService|invoice_uses_default_room_service_price|invoice_prefers_contract_scoped_room_service_price'
```

Expected: FAIL because `room_services.price` still exists, unique constraint blocks same-day scoped rows, and factories/setup still create `RoomService` with `price`.

---

## Task 2: Refactor Schema and Models

**Files:**
- Modify: `BE_StayHub/database/migrations/2026_07_02_222000_create_room_services_table.php`
- Modify: `BE_StayHub/database/migrations/2026_07_10_120000_create_room_service_prices_table.php`
- Create: `BE_StayHub/database/migrations/2026_07_10_130000_backfill_room_service_prices_and_drop_room_services_price.php`
- Modify: `BE_StayHub/app/Models/RoomService.php`
- Modify: `BE_StayHub/app/Models/Room.php`
- Modify: `BE_StayHub/app/Models/RoomServicePrice.php`

- [ ] **Step 1: Update original `room_services` migration**

In `BE_StayHub/database/migrations/2026_07_02_222000_create_room_services_table.php`, remove `$table->decimal('price', 15, 2);` and change seed insert to create only the join row plus default room price row.

Use this `up()` body:

```php
public function up(): void
{
    Schema::create('room_services', function (Blueprint $table) {
        $table->id();
        $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
        $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
        $table->timestamps();

        $table->unique(['room_id', 'service_id']);
    });

    $rooms = DB::table('rooms')->get();
    foreach ($rooms as $room) {
        $activePrices = DB::table('service_prices')
            ->join('services', 'services.id', '=', 'service_prices.service_id')
            ->where('service_prices.building_id', $room->building_id)
            ->where('service_prices.status', 1)
            ->select('service_prices.*', 'services.charge_method', 'services.slug')
            ->get();

        foreach ($activePrices as $price) {
            DB::table('room_services')->insertOrIgnore([
                'room_id' => $room->id,
                'service_id' => $price->service_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Update `room_service_prices` unique constraint**

In `BE_StayHub/database/migrations/2026_07_10_120000_create_room_service_prices_table.php`, replace:

```php
$table->unique(['room_service_id', 'effective_from']);
```

with:

```php
$table->unique(['room_service_id', 'contract_id', 'effective_from'], 'room_service_prices_scope_unique');
$table->index(['room_service_id', 'contract_id', 'effective_from', 'effective_to'], 'room_service_prices_scope_lookup_idx');
```

Keep existing `room_service_prices_lookup_idx` unless index name length conflicts in MySQL. If conflict occurs, keep only `room_service_prices_scope_lookup_idx` and `room_service_prices_contract_period_idx`.

- [ ] **Step 3: Create migration to backfill default prices and drop old column**

Create `BE_StayHub/database/migrations/2026_07_10_130000_backfill_room_service_prices_and_drop_room_services_price.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_services')) {
            return;
        }

        $hasRoomServicePrice = Schema::hasTable('room_service_prices');
        $hasPriceColumn = Schema::hasColumn('room_services', 'price');

        if ($hasRoomServicePrice && $hasPriceColumn) {
            DB::table('room_services')
                ->join('services', 'services.id', '=', 'room_services.service_id')
                ->where('services.charge_method', '!=', 1)
                ->whereNotIn('services.slug', ['electric', 'water', 'electricity', 'dien', 'nuoc', 'dien-sinh-hoat', 'nuoc-sinh-hoat'])
                ->select('room_services.id', 'room_services.price', 'room_services.created_at')
                ->orderBy('room_services.id')
                ->chunk(500, function ($roomServices): void {
                    foreach ($roomServices as $roomService) {
                        $createdAt = $roomService->created_at ?: now();

                        DB::table('room_service_prices')->updateOrInsert(
                            [
                                'room_service_id' => $roomService->id,
                                'contract_id' => null,
                                'effective_from' => '2026-01-01',
                            ],
                            [
                                'price' => $roomService->price,
                                'effective_to' => null,
                                'created_by' => null,
                                'created_at' => $createdAt,
                                'updated_at' => now(),
                            ]
                        );
                    }
                });
        }

        if ($hasPriceColumn) {
            Schema::table('room_services', function (Blueprint $table): void {
                $table->dropColumn('price');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_services') || Schema::hasColumn('room_services', 'price')) {
            return;
        }

        Schema::table('room_services', function (Blueprint $table): void {
            $table->decimal('price', 15, 2)->default(0)->after('service_id');
        });

        DB::table('room_services')
            ->leftJoin('room_service_prices', function ($join): void {
                $join->on('room_service_prices.room_service_id', '=', 'room_services.id')
                    ->whereNull('room_service_prices.contract_id')
                    ->whereNull('room_service_prices.effective_to');
            })
            ->select('room_services.id', 'room_service_prices.price')
            ->orderBy('room_services.id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('room_services')
                        ->where('id', $row->id)
                        ->update(['price' => $row->price ?? 0]);
                }
            });
    }
};
```

- [ ] **Step 4: Make `RoomService` model price-free**

In `BE_StayHub/app/Models/RoomService.php`, change fillable/casts to:

```php
protected $fillable = ['room_id', 'service_id'];

protected function casts(): array
{
    return [
        'room_id' => 'integer',
        'service_id' => 'integer',
    ];
}
```

Keep `room()`, `service()`, `prices()` relationships.

- [ ] **Step 5: Remove pivot price from `Room` model**

In `BE_StayHub/app/Models/Room.php`, change:

```php
return $this->belongsToMany(Service::class, 'room_services')->withPivot('price')->withTimestamps();
```

to:

```php
return $this->belongsToMany(Service::class, 'room_services')->withTimestamps();
```

- [ ] **Step 6: Add query scopes to `RoomServicePrice`**

In `BE_StayHub/app/Models/RoomServicePrice.php`, add imports:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
```

Add methods:

```php
public function scopeEffectiveFor(Builder $query, Carbon|string $targetDate): Builder
{
    $date = $targetDate instanceof Carbon ? $targetDate->toDateString() : $targetDate;

    return $query->whereDate('effective_from', '<=', $date)
        ->where(function (Builder $scope) use ($date): void {
            $scope->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $date);
        });
}

public function scopeForContractOrDefault(Builder $query, ?int $contractId): Builder
{
    return $query->where(function (Builder $scope) use ($contractId): void {
        $scope->whereNull('contract_id')
            ->when($contractId !== null, fn (Builder $contractScope): Builder => $contractScope->orWhere('contract_id', $contractId));
    });
}

public function scopePriorityForContract(Builder $query, ?int $contractId): Builder
{
    if ($contractId === null) {
        return $query->orderByDesc('effective_from')->orderByDesc('id');
    }

    return $query
        ->orderByRaw('contract_id = ? DESC', [$contractId])
        ->orderByDesc('effective_from')
        ->orderByDesc('id');
}
```

- [ ] **Step 7: Run schema/model tests**

Run:

```bash
cd BE_StayHub
php artisan test tests/Feature/Admin/RoomServicePriceTest.php --filter='room_services_table_does_not_store_price_column|room_service_prices_table_does_not_store_status_column|room_service_prices_allows_default_and_contract_price_on_same_date'
```

Expected: PASS after schema and tests are aligned. If SQLite does not support a migration operation, adjust migration using Laravel schema-supported operations only.

---

## Task 3: Add Central Price Resolver

**Files:**
- Create: `BE_StayHub/app/Services/RoomServicePriceResolver.php`
- Test through existing Feature tests from Task 1.

- [ ] **Step 1: Create resolver class**

Create `BE_StayHub/app/Services/RoomServicePriceResolver.php`:

```php
<?php

namespace App\Services;

use App\Helpers\DecimalMoney;
use App\Models\Contract;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\RoomServicePrice;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomServicePriceResolver
{
    public function effectiveRoomServicePrice(RoomService $roomService, Carbon $targetDate, ?Contract $contract = null): ?RoomServicePrice
    {
        return RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->forContractOrDefault($contract?->id)
            ->effectiveFor($targetDate)
            ->priorityForContract($contract?->id)
            ->first();
    }

    public function effectivePriceAmount(RoomService $roomService, Carbon $targetDate, ?Contract $contract = null): ?string
    {
        $price = $this->effectiveRoomServicePrice($roomService, $targetDate, $contract);

        return $price ? DecimalMoney::normalize($price->price) : null;
    }

    public function defaultRoomServicePrice(RoomService $roomService, Carbon $targetDate): ?RoomServicePrice
    {
        return RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->whereNull('contract_id')
            ->effectiveFor($targetDate)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function ensureRoomService(Room $room, Service|int $service): RoomService
    {
        $serviceId = $service instanceof Service ? $service->id : $service;

        return RoomService::query()->firstOrCreate([
            'room_id' => $room->id,
            'service_id' => (int) $serviceId,
        ]);
    }

    public function ensureDefaultPriceFromServicePrice(Room $room, ServicePrice $servicePrice, ?int $createdBy = null): ?RoomServicePrice
    {
        $service = $servicePrice->relationLoaded('service') ? $servicePrice->service : $servicePrice->service()->first();
        if (! $service || $service->isMeteredUtility()) {
            return null;
        }

        return DB::transaction(function () use ($room, $servicePrice, $createdBy): RoomServicePrice {
            $roomService = $this->ensureRoomService($room, (int) $servicePrice->service_id);
            $effectiveFrom = $servicePrice->effective_from?->toDateString() ?? now()->toDateString();

            return RoomServicePrice::query()->updateOrCreate(
                [
                    'room_service_id' => $roomService->id,
                    'contract_id' => null,
                    'effective_from' => $effectiveFrom,
                ],
                [
                    'price' => DecimalMoney::normalize($servicePrice->price),
                    'effective_to' => $servicePrice->effective_to?->toDateString(),
                    'created_by' => $createdBy,
                ]
            );
        });
    }

    public function servicePricesForContract(Room $room, Contract $contract, Carbon $targetDate): Collection
    {
        return RoomService::query()
            ->with(['service:id,name,slug,charge_method,unit_name,is_active'])
            ->where('room_id', $room->id)
            ->whereHas('service', fn (Builder $query): Builder => $query->where('is_active', true))
            ->get()
            ->map(function (RoomService $roomService) use ($contract, $targetDate): array {
                return [
                    'room_service' => $roomService,
                    'service' => $roomService->service,
                    'price' => $this->effectivePriceAmount($roomService, $targetDate, $contract),
                ];
            })
            ->filter(fn (array $item): bool => $item['service'] && $item['price'] !== null)
            ->values();
    }
}
```

- [ ] **Step 2: Run autoload check**

Run:

```bash
cd BE_StayHub
composer dump-autoload
php artisan test tests/Feature/Admin/RoomServicePriceTest.php --filter='room_service_prices_allows_default_and_contract_price_on_same_date'
```

Expected: autoload succeeds and the focused test still passes.

---

## Task 4: Refactor Room Creation and Room Service Price Admin API

**Files:**
- Modify: `BE_StayHub/app/Http/Controllers/Admin/RoomController.php`
- Modify: `BE_StayHub/app/Http/Controllers/Admin/RoomServicePriceController.php`
- Modify: `BE_StayHub/app/Http/Resources/Admin/RoomServicePriceResource.php`

- [ ] **Step 1: Update `RoomController` imports**

In `BE_StayHub/app/Http/Controllers/Admin/RoomController.php`, add:

```php
use App\Models\ServicePrice;
use App\Services\RoomServicePriceResolver;
```

If the file currently uses fully qualified `\App\Models\ServicePrice` and `\App\Models\RoomService`, remove those fully qualified references in the touched block.

- [ ] **Step 2: Seed room services without `price` and create default prices**

In `BE_StayHub/app/Http/Controllers/Admin/RoomController.php`, replace the block at lines around `164-175` with:

```php
$priceResolver = app(RoomServicePriceResolver::class);
$activeBuildingServices = ServicePrice::query()
    ->with('service:id,slug,charge_method')
    ->where('building_id', $room->building_id)
    ->where('status', ServicePrice::STATUS_ACTIVE)
    ->whereNull('effective_to')
    ->get();

foreach ($activeBuildingServices as $buildingPrice) {
    $priceResolver->ensureRoomService($room, (int) $buildingPrice->service_id);
    $priceResolver->ensureDefaultPriceFromServicePrice($room, $buildingPrice, $admin->id);
}
```

- [ ] **Step 3: Update `RoomServicePriceController::roomRelations()` select**

In `BE_StayHub/app/Http/Controllers/Admin/RoomServicePriceController.php`, change:

```php
->select(['id', 'room_id', 'service_id', 'price'])
```

to:

```php
->select(['id', 'room_id', 'service_id'])
```

Also ensure `prices` eager load includes both default and scheduled rows for the target date:

```php
'prices' => fn ($priceQuery) => $priceQuery
    ->with('creator:id,full_name')
    ->whereDate('effective_from', '<=', $targetDate->toDateString())
    ->where(function (Builder $query) use ($targetDate): void {
        $query->whereNull('effective_to')
            ->orWhereDate('effective_to', '>=', $targetDate->toDateString());
    })
    ->orderByRaw('contract_id IS NOT NULL')
    ->orderByDesc('effective_from')
    ->orderByDesc('id'),
```

For the room price admin screen, contract-scoped rows should not be used as default room prices. If tests show contract prices leaking into room default list, add `->whereNull('contract_id')` in this eager load.

- [ ] **Step 4: Update `RoomServicePriceResource` to read base/effective from `room_service_prices`**

In `BE_StayHub/app/Http/Resources/Admin/RoomServicePriceResource.php`, replace lines that use `$this->price` with default/effective price objects.

Use this `toArray()` body:

```php
public function toArray(Request $request): array
{
    $targetDate = $this->targetDate();
    $effectivePrice = $this->effectivePriceFor($targetDate);
    $scheduledPrice = $this->scheduledPriceFor($targetDate);
    $service = $this->service;

    return [
        'id' => $this->id,
        'room_id' => $this->room_id,
        'service_id' => $this->service_id,
        'service_name' => $service?->name,
        'service_slug' => $service?->slug,
        'charge_method' => $service?->charge_method,
        'charge_method_label' => $service ? (Service::CHARGE_METHOD_LABELS[$service->charge_method] ?? null) : null,
        'unit_name' => $service?->unit_name,
        'base_price' => $effectivePrice ? (string) $effectivePrice->price : '0.00',
        'effective_price' => $effectivePrice ? (string) $effectivePrice->price : '0.00',
        'scheduled_price' => $scheduledPrice ? (string) $scheduledPrice->price : null,
        'effective_from' => optional($effectivePrice?->effective_from)->toDateString(),
        'effective_to' => optional($effectivePrice?->effective_to)->toDateString(),
        'status_label' => $this->statusLabel($effectivePrice),
        'created_by' => $scheduledPrice?->created_by,
        'creator_name' => $scheduledPrice?->relationLoaded('creator') ? $scheduledPrice?->creator?->full_name : null,
        'created_at' => optional($scheduledPrice?->created_at)->toDateTimeString(),
    ];
}
```

Update `effectivePriceFor()` so it ignores contract-scoped rows for the room price admin list:

```php
private function effectivePriceFor(string $targetDate): ?RoomServicePrice
{
    return $this->relationLoaded('prices')
        ? $this->prices
            ->filter(fn (RoomServicePrice $price): bool => $price->contract_id === null && $price->effective_from->toDateString() <= $targetDate && ($price->effective_to === null || $price->effective_to->toDateString() >= $targetDate))
            ->sortByDesc(fn (RoomServicePrice $price): string => $price->effective_from->toDateString())
            ->first()
        : null;
}
```

Update `scheduledPriceFor()` similarly:

```php
private function scheduledPriceFor(string $targetDate): ?RoomServicePrice
{
    return $this->relationLoaded('prices')
        ? $this->prices->first(fn (RoomServicePrice $price): bool => $price->contract_id === null && $price->effective_from->toDateString() === $targetDate)
        : null;
}
```

- [ ] **Step 5: Update admin scheduling to write default scope**

In `BE_StayHub/app/Http/Controllers/Admin/RoomServicePriceController.php`, in `schedulePrice()`, remove contract binding from room-level schedule unless this endpoint intentionally schedules active-contract-only prices. User requirement says room price source should be `room_service_prices`, and this screen is room-level price management. Change existing queries to `whereNull('contract_id')` and created row `contract_id => null`:

```php
$existing = RoomServicePrice::query()
    ->where('room_service_id', $roomService->id)
    ->whereNull('contract_id')
    ->whereDate('effective_from', $effectiveFrom)
    ->lockForUpdate()
    ->first();

RoomServicePrice::query()
    ->where('room_service_id', $roomService->id)
    ->whereNull('contract_id')
    ->whereDate('effective_from', '<', $effectiveFrom)
    ->where(function (Builder $query) use ($effectiveFrom): void {
        $query->whereNull('effective_to')
            ->orWhereDate('effective_to', '>=', $effectiveFrom);
    })
    ->update([
        'effective_to' => $previousEnd,
        'updated_at' => now(),
    ]);
```

Create row:

```php
return RoomServicePrice::query()->create([
    'room_service_id' => $roomService->id,
    'contract_id' => null,
    'price' => $price,
    'effective_from' => $effectiveFrom,
    'effective_to' => null,
    'created_by' => $admin->id,
])->load('roomService.service');
```

If business requires active contracts to keep their deal until contract end, do not update contract-scoped rows in this endpoint.

- [ ] **Step 6: Run room service price API tests**

Run:

```bash
cd BE_StayHub
php artisan test tests/Feature/Admin/RoomServicePriceTest.php
```

Expected: PASS except tests that still create `RoomService` with `price`; update test fixtures in the next task if needed.

---

## Task 5: Refactor Contract Creation/Update and Contract Resources

**Files:**
- Modify: `BE_StayHub/app/Http/Controllers/Admin/ContractController.php`
- Modify: `BE_StayHub/app/Http/Requests/Admin/Contract/UpdateRequest.php`
- Modify: `BE_StayHub/app/Http/Resources/Admin/ContractDetailResource.php`
- Modify: `BE_StayHub/app/Http/Resources/Tenant/ContractResource.php`
- Modify: `BE_StayHub/app/Http/Controllers/Tenant/ContractController.php`

- [ ] **Step 1: Inject resolver import in `ContractController`**

In `BE_StayHub/app/Http/Controllers/Admin/ContractController.php`, add:

```php
use App\Services\RoomServicePriceResolver;
```

- [ ] **Step 2: Update available rooms API to return services with computed price**

Replace `availableRooms` room query eager load at `BE_StayHub/app/Http/Controllers/Admin/ContractController.php:76-80` with `roomServices.service` and `roomServices.prices`:

```php
$targetDate = now()->startOfDay();

$rooms = Room::query()
    ->with([
        'roomServices' => function ($query) use ($targetDate): void {
            $query->select(['id', 'room_id', 'service_id'])
                ->with([
                    'service:id,name,slug,charge_method,unit_name,is_active',
                    'prices' => fn ($priceQuery) => $priceQuery
                        ->whereNull('contract_id')
                        ->whereDate('effective_from', '<=', $targetDate->toDateString())
                        ->where(function (Builder $scope) use ($targetDate): void {
                            $scope->whereNull('effective_to')
                                ->orWhereDate('effective_to', '>=', $targetDate->toDateString());
                        })
                        ->orderByDesc('effective_from')
                        ->orderByDesc('id'),
                ]);
        },
    ])
    ->select(['id', 'building_id', 'room_number', 'status', 'base_price', 'max_occupants', 'current_occupants'])
    ->where('building_id', $buildingId)
    ->where('status', Room::STATUS_ACTIVE)
    ->whereDoesntHave('contracts', function (Builder $query) use ($ignoreContractId): void {
        $query->whereIn('status', Contract::RESERVED_STATUSES)
            ->when($ignoreContractId > 0, fn (Builder $contractQuery): Builder => $contractQuery->whereKeyNot($ignoreContractId));
    })
    ->orderBy('room_number')
    ->get()
    ->map(function (Room $room): Room {
        $room->setRelation('services', $room->roomServices
            ->filter(fn (RoomService $roomService): bool => (bool) $roomService->service?->is_active)
            ->map(function (RoomService $roomService): array {
                $service = $roomService->service;
                $price = $roomService->prices->first()?->price ?? '0.00';

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'charge_method' => $service->charge_method,
                    'unit_name' => $service->unit_name,
                    'room_service_id' => $roomService->id,
                    'price' => (string) $price,
                ];
            })
            ->values());

        unset($room->roomServices);

        return $room;
    });
```

This preserves the response key `services` for FE while removing pivot price dependency.

- [ ] **Step 3: Update `syncContractRoomServices()` not to write `room_services.price`**

In `BE_StayHub/app/Http/Controllers/Admin/ContractController.php`, replace the `updateOrCreate` call in `syncContractRoomServices()` with:

```php
$roomService = RoomService::query()->firstOrCreate([
    'room_id' => $room->id,
    'service_id' => (int) $item['service_id'],
]);
```

Keep:

```php
$this->upsertContractRoomServicePrice($roomService, $contract, $contractStart, $contractEnd, (string) $item['price'], $admin);
```

- [ ] **Step 4: Update `upsertContractRoomServicePrice()` unique lookup**

Replace the method body with scope-aware unique logic:

```php
private function upsertContractRoomServicePrice(RoomService $roomService, Contract $contract, Carbon $contractStart, ?Carbon $contractEnd, string $price, Admin $admin): void
{
    $effectiveFrom = $contractStart->toDateString();
    $effectiveTo = $contractEnd?->toDateString();

    $existing = RoomServicePrice::query()
        ->where('room_service_id', $roomService->id)
        ->where('contract_id', $contract->id)
        ->whereDate('effective_from', $effectiveFrom)
        ->lockForUpdate()
        ->first();

    if ($existing) {
        $existing->forceFill([
            'price' => $price,
            'effective_to' => $effectiveTo,
            'created_by' => $admin->id,
        ])->save();

        return;
    }

    $this->closeRoomServicePrice($roomService, $contractStart->copy()->subDay()->toDateString(), $contract);

    RoomServicePrice::query()->create([
        'room_service_id' => $roomService->id,
        'contract_id' => $contract->id,
        'price' => $price,
        'effective_from' => $effectiveFrom,
        'effective_to' => $effectiveTo,
        'created_by' => $admin->id,
    ]);
}
```

- [ ] **Step 5: Keep default prices separate when closing room-level prices**

Review `closeRoomServicePrice()`:

```php
->when($exceptContract, fn (Builder $query): Builder => $query->where(function (Builder $scope) use ($exceptContract): void {
    $scope->whereNull('contract_id')->orWhere('contract_id', '!=', $exceptContract->id);
}))
```

If contract deal creation must not close default room prices, change it to only close same contract rows when called from contract sync:

```php
->when($exceptContract, fn (Builder $query): Builder => $query->where('contract_id', $exceptContract->id))
```

Recommended: add a new helper `closeContractRoomServicePriceForRoomService(RoomService $roomService, Contract $contract, string $endDate)` and use it in contract sync, so default `contract_id = null` rows remain open.

- [ ] **Step 6: Allow services in contract update request**

In `BE_StayHub/app/Http/Requests/Admin/Contract/UpdateRequest.php`, add rules at the end of `rules()` before closing array:

```php
'services' => ['nullable', 'array'],
'services.*.service_id' => ['required_with:services', 'integer', 'distinct', Rule::exists('services', 'id')],
'services.*.price' => ['required_with:services', 'regex:/^\d{1,13}(\.\d{1,2})?$/', 'gte:0'],
```

This keeps update behavior aligned with controller code at `array_key_exists('services', $validated)`.

- [ ] **Step 7: Update admin contract resource fallback**

In `BE_StayHub/app/Http/Resources/Admin/ContractDetailResource.php`, replace `contractRoomServicePrice()` fallback logic:

```php
private function contractRoomServicePrice($service): string
{
    if (! $this->relationLoaded('roomServicePrices')) {
        return '0.00';
    }

    $roomServicePrice = $this->roomServicePrices
        ->filter(fn (RoomServicePrice $price): bool => (int) $price->roomService?->service_id === (int) $service->id)
        ->sortByDesc(fn (RoomServicePrice $price): string => $price->effective_from?->toDateString() ?? '')
        ->first();

    return (string) ($roomServicePrice?->price ?? '0.00');
}
```

Then update `detailRelations()` in `ContractController` to load `room.roomServices.service` and prices instead of `room.services`, or keep `room.services` but no longer rely on pivot. Recommended:

```php
'room.roomServices.service:id,name,slug,charge_method,unit_name,is_active',
```

And update resource mapping from `$this->room->services->map()` to `$this->room->roomServices->map(fn (RoomService $roomService) => ...)` with service from `$roomService->service`.

- [ ] **Step 8: Update tenant contract resource similarly**

In `BE_StayHub/app/Http/Resources/Tenant/ContractResource.php`, apply the same fallback change: no `$service->pivot->price`; return `room_service_prices` amount or `'0.00'`.

Update `BE_StayHub/app/Http/Controllers/Tenant/ContractController.php` relation loads from:

```php
'room.services',
```

to:

```php
'room.roomServices.service',
```

Then update `ContractResource` mapping to use `roomServices`.

- [ ] **Step 9: Run contract tests**

Run:

```bash
cd BE_StayHub
php artisan test tests/Feature/Admin/ContractControllerTest.php tests/Feature/Admin/RoomServicePriceTest.php --filter='contract|deal|room_service'
```

Expected: PASS after fixtures are updated to stop inserting `price` into `room_services`.

---

## Task 6: Refactor Invoice Price Source

**Files:**
- Modify: `BE_StayHub/app/Http/Controllers/Admin/InvoiceController.php`
- Test: `BE_StayHub/tests/Feature/Admin/InvoiceControllerTest.php`

- [ ] **Step 1: Import resolver**

In `BE_StayHub/app/Http/Controllers/Admin/InvoiceController.php`, add:

```php
use App\Services\RoomServicePriceResolver;
```

If the controller constructor exists, inject resolver as a promoted/assigned dependency. If no constructor exists, use `app(RoomServicePriceResolver::class)` inside helper to keep patch small.

- [ ] **Step 2: Replace non-metered price fallback in `buildAutomaticItems()`**

Keep building-level `$prices = $this->currentServicePrices($buildingId, $periodEnd);` for discovering active services. Replace the non-metered block at lines around `735-755` with:

```php
if (! $isMetered) {
    $roomService = RoomService::query()
        ->where('room_id', $contract->room_id)
        ->where('service_id', $service->id)
        ->first();

    if (! $roomService) {
        continue;
    }

    $resolvedAmount = $this->roomServicePriceForPeriod($roomService, $periodEnd, $contract);
    if ($resolvedAmount === null) {
        $errors[] = "Phòng {$contract->room?->room_number} chưa có giá dịch vụ {$service->name} hiệu lực trong kỳ {$billingMonth}/{$billingYear}.";

        continue;
    }

    $priceAmount = $resolvedAmount;
}
```

- [ ] **Step 3: Change `roomServicePriceForPeriod()` nullable return**

Replace `roomServicePriceForPeriod()` with:

```php
private function roomServicePriceForPeriod(RoomService $roomService, Carbon $periodEnd, Contract $contract): ?string
{
    $scheduledPrice = $roomService->relationLoaded('prices')
        ? $roomService->prices->first()
        : RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->forContractOrDefault($contract->id)
            ->effectiveFor($periodEnd)
            ->priorityForContract($contract->id)
            ->first();

    return $scheduledPrice ? DecimalMoney::normalize($scheduledPrice->price) : null;
}
```

- [ ] **Step 4: Ensure eager load ordering still prioritizes contract**

Where `RoomService::where(...)->with(['prices' => ...])` remains in `InvoiceController`, ensure it orders:

```php
->orderByRaw('contract_id = ? DESC', [$contract->id])
->orderByDesc('effective_from')
->orderByDesc('id')
```

If that eager load was removed in Step 2, no action needed.

- [ ] **Step 5: Run invoice tests**

Run:

```bash
cd BE_StayHub
php artisan test tests/Feature/Admin/InvoiceControllerTest.php --filter='invoice_uses_scheduled_room_service_price|invoice_uses_default_room_service_price|invoice_prefers_contract_scoped_room_service_price|remaining_contract|transfer'
```

Expected: PASS. If a test expects fallback to old `room_services.price`, update fixture to create a default `room_service_prices` row.

---

## Task 7: Refactor Scheduled Room Transfer Price Copy

**Files:**
- Modify: `BE_StayHub/app/Console/Commands/ExecuteScheduledRoomTransfers.php`
- Test: `BE_StayHub/tests/Feature/Admin/InvoiceControllerTest.php` and any room movement tests.

- [ ] **Step 1: Update `copyRoomServicePrices()` to ensure target room service exists without price**

In `BE_StayHub/app/Console/Commands/ExecuteScheduledRoomTransfers.php`, update target services map creation:

```php
$targetRoomServices = RoomService::query()
    ->where('room_id', $room->id)
    ->get()
    ->keyBy('service_id');
```

Inside loop, replace missing target skip:

```php
$targetRoomService = $targetRoomServices->get((int) $sourcePrice->roomService?->service_id);
if (! $targetRoomService) {
    $targetRoomService = RoomService::query()->create([
        'room_id' => $room->id,
        'service_id' => (int) $sourcePrice->roomService?->service_id,
    ]);
    $targetRoomServices->put((int) $sourcePrice->roomService?->service_id, $targetRoomService);
}
```

- [ ] **Step 2: Scope updateOrCreate by contract id**

In `copyRoomServicePrices()`, replace `updateOrCreate` keys:

```php
[
    'room_service_id' => $targetRoomService->id,
    'effective_from' => $effectiveFrom->toDateString(),
]
```

with:

```php
[
    'room_service_id' => $targetRoomService->id,
    'contract_id' => $newContract->id,
    'effective_from' => $effectiveFrom->toDateString(),
]
```

And remove `'contract_id' => $newContract->id` from update values because it is now part of keys.

- [ ] **Step 3: Do not close default prices while copying contract-scoped transfer prices**

Change the close query before `updateOrCreate` from:

```php
->whereNull('effective_to')
```

to:

```php
->where('contract_id', $newContract->id)
->whereNull('effective_to')
```

This prevents a transfer from accidentally closing default room prices for the target room.

- [ ] **Step 4: Verify source price priority**

Keep the existing source query ordering:

```php
->orderByRaw('contract_id IS NULL')
->orderByDesc('effective_from')
```

If MySQL/SQLite ordering is confusing, replace with explicit contract priority:

```php
->orderByRaw('contract_id = ? DESC', [$sourceContract->id])
->orderByDesc('effective_from')
->orderByDesc('id')
```

- [ ] **Step 5: Run transfer/invoice tests**

Run:

```bash
cd BE_StayHub
php artisan test tests/Feature/Admin/InvoiceControllerTest.php --filter='transfer|remaining_contract|room_services'
php artisan test tests/Feature/Admin/RoomMovementControllerTest.php
```

If `RoomMovementControllerTest.php` does not exist, run:

```bash
cd BE_StayHub
php artisan test tests/Feature/Admin --filter='transfer'
```

Expected: PASS. New contract after transfer keeps copied contract-scoped price and invoices use that price.

---

## Task 8: Refactor Frontend Contract Price Source

**Files:**
- Modify: `FE_StayHub/src/features/admin/contracts/components/create-contract-screen.tsx`
- Modify: `FE_StayHub/src/features/admin/room-service-prices/types/room-service-price.model.ts`

- [ ] **Step 1: Replace pivot price usage in create contract screen**

In `FE_StayHub/src/features/admin/contracts/components/create-contract-screen.tsx`, replace:

```tsx
price: formatMoneyInput(String(Math.round(Number(service.pivot?.price || 0)))),
```

with:

```tsx
price: formatMoneyInput(String(Math.round(Number(service.price || service.base_price || service.effective_price || 0)))),
```

This matches backend available rooms response after Task 5.

- [ ] **Step 2: Preserve building-level initial display**

The block at `create-contract-screen.tsx:397-424` already loads `buildingRes.result?.service_prices` and maps `price` from active `service_prices`. Keep this behavior because user wants first view to show building-level price before admin deals.

If selected room has room default price from backend, `updateForm('room_id')` should use room-specific `service.price`; otherwise `buildingServices` is still the fallback in the service management modal.

- [ ] **Step 3: Update TypeScript type if room service type contains pivot**

Search:

```bash
cd FE_StayHub
rg -n "pivot\?\.price|pivot.price|withPivot|base_price|effective_price" src/features/admin/contracts src/features/admin/room-service-prices
```

Replace any remaining contract service `pivot.price` access with `service.price || service.base_price || service.effective_price || 0`.

- [ ] **Step 4: Keep room-service-price API type stable**

In `FE_StayHub/src/features/admin/room-service-prices/types/room-service-price.model.ts`, keep:

```ts
base_price: string
effective_price: string
```

No UI rename is required because backend still returns these keys from `room_service_prices`.

- [ ] **Step 5: Run FE tests/typecheck**

Run:

```bash
cd FE_StayHub
npm test -- --runInBand room-service-price-screen
npm run build
```

If the project does not define `npm test`, run:

```bash
cd FE_StayHub
npm run build
```

Expected: build succeeds and no `pivot.price` references remain in contract creation flow.

---

## Task 9: Update Test Fixtures and Remove Old Price Writes

**Files:**
- Modify all backend tests found by search.
- Modify backend code found by search.

- [ ] **Step 1: Search for old writes/read dependencies**

Run:

```bash
rg -n "room_services.*price|RoomService::(create|query\(\)->create|query\(\)->updateOrCreate)|'price' => .*RoomService|->price|pivot->price|withPivot\('price'\)|service\.pivot\?\.price" BE_StayHub/app BE_StayHub/tests FE_StayHub/src
```

- [ ] **Step 2: Update `RoomService::create()` test fixtures**

For every test fixture like:

```php
$roomService = RoomService::create([
    'room_id' => $room->id,
    'service_id' => $service->id,
    'price' => '100000.00',
]);
```

Change to:

```php
$roomService = RoomService::create([
    'room_id' => $room->id,
    'service_id' => $service->id,
]);

RoomServicePrice::create([
    'room_service_id' => $roomService->id,
    'contract_id' => null,
    'price' => '100000.00',
    'effective_from' => '2026-01-01',
    'created_by' => $this->superAdmin->id,
]);
```

Use the fixture's actual date/admin variable. For tests without admin, use `created_by => null`.

- [ ] **Step 3: Update app code old fallback reads**

Every app code fallback like:

```php
$roomService->price
$service->pivot->price
```

must become one of:

```php
$priceResolver->effectivePriceAmount($roomService, $targetDate, $contract)
```

or direct query:

```php
RoomServicePrice::query()
    ->where('room_service_id', $roomService->id)
    ->forContractOrDefault($contract?->id)
    ->effectiveFor($targetDate)
    ->priorityForContract($contract?->id)
    ->first()?->price
```

- [ ] **Step 4: Ensure no mass assignment includes room service price**

Run:

```bash
rg -n "protected \$fillable = \['room_id', 'service_id', 'price'\]|withPivot\('price'\)|roomService->price|pivot->price|service\.pivot\?\.price" BE_StayHub/app FE_StayHub/src
```

Expected: no matches except documentation or this plan.

- [ ] **Step 5: Run focused backend tests**

Run:

```bash
cd BE_StayHub
php artisan test tests/Feature/Admin/RoomServicePriceTest.php tests/Feature/Admin/ContractControllerTest.php tests/Feature/Admin/InvoiceControllerTest.php
```

Expected: PASS.

---

## Task 10: Full Regression and Documentation Update

**Files:**
- Modify: `README.md`
- Modify: `thiet_ke_co_so_du_lieu.md`
- Run backend/frontend tests.

- [ ] **Step 1: Update README table descriptions**

In `README.md`, change the `room_services` description around line `86` from:

```md
* `room_services`: Danh sách các dịch vụ và đơn giá đang được cấu hình áp dụng cho từng phòng cụ thể.
```

to:

```md
* `room_services`: Bảng nối phòng và dịch vụ đang áp dụng cho từng phòng; không lưu giá.
* `room_service_prices`: Đơn giá dịch vụ phòng theo thời gian, gồm giá mặc định của phòng và giá deal theo hợp đồng.
```

- [ ] **Step 2: Update database design doc**

In `thiet_ke_co_so_du_lieu.md`, update section `2.1.3.13. Bảng room_services` to remove row:

```md
| price | DECIMAL(15,2) | Đơn giá dịch vụ của phòng | DEFAULT: 0.00, NOT NULL |
```

Add a section for `room_service_prices` if missing:

```md
### **2.1.3.x. Bảng room_service_prices**

| Tên trường | Kiểu dữ liệu | Mô tả | Ràng buộc |
| :--- | :--- | :--- | :--- |
| id | BIGINT UNSIGNED | Khoá chính | PRIMARY KEY, AUTO_INCREMENT |
| room_service_id | BIGINT UNSIGNED | Dịch vụ của phòng | FOREIGN KEY -> room_services(id), CASCADE |
| contract_id | BIGINT UNSIGNED | Hợp đồng được deal giá riêng | NULLABLE, FOREIGN KEY -> contracts(id), SET NULL |
| price | DECIMAL(15,2) | Đơn giá áp dụng | NOT NULL |
| effective_from | DATE | Ngày bắt đầu hiệu lực | NOT NULL |
| effective_to | DATE | Ngày kết thúc hiệu lực | NULLABLE |
| created_by | BIGINT UNSIGNED | Admin tạo | NULLABLE, FOREIGN KEY -> admins(id), SET NULL |
| created_at | TIMESTAMP | Thời điểm tạo | NULLABLE |
| updated_at | TIMESTAMP | Thời điểm cập nhật | NULLABLE |
```

- [ ] **Step 3: Run full backend test suite**

Run:

```bash
cd BE_StayHub
php artisan test
```

Expected: PASS. If unrelated tests fail because of existing environment data, capture the failures and run focused suites again to confirm refactor scope.

- [ ] **Step 4: Run frontend build**

Run:

```bash
cd FE_StayHub
npm run build
```

Expected: PASS.

- [ ] **Step 5: Final search for forbidden patterns**

Run:

```bash
rg -n "room_services.*price|withPivot\('price'\)|pivot->price|service\.pivot\?\.price|roomService->price|room_service_id.*effective_from'\]" BE_StayHub/app BE_StayHub/database BE_StayHub/tests FE_StayHub/src
```

Expected: no app/frontend code depends on `room_services.price`. Any remaining test references should be explicit schema assertions or migration down logic only.

- [ ] **Step 6: Manual API smoke checks**

Run these after backend is running:

```bash
curl -s "http://localhost/api/v1/admin/room-service-prices?billing_month=8&billing_year=2026" \
  -H "Accept: application/json"
```

Expected: each non-metered service returns `base_price` and `effective_price` from `room_service_prices`.

Manual UI checks:
- Create contract screen: selecting room shows service prices initially from building/default price.
- Deal a service price down before saving contract.
- Contract detail shows deal price.
- Invoice preview for that contract uses deal price.
- Schedule room service price for next month.
- Invoice preview for next month uses room default price if no contract deal overrides it.
- Execute/simulate transfer and preview invoice for destination/remaining contract; copied service price comes from `room_service_prices`.

---

## Risk Controls

- Do not drop old `room_services.price` until migration has backfilled default `room_service_prices` rows.
- Do not let room-level price scheduling close contract-scoped deal rows.
- Do not let contract deal creation close default `contract_id = null` rows unless product explicitly wants deal to replace room default.
- Do not include điện/nước metered services in room service deal flow; they remain `service_prices` + meter readings.
- Keep API response keys `base_price`, `effective_price`, `scheduled_price` to minimize frontend breakage, even though source is now `room_service_prices`.
- Scope unique constraints by `contract_id`; otherwise same-day default and contract deal cannot coexist.

## Self-Review

- Spec coverage: plan covers schema, model, resolver, room creation, contract creation/update, invoice, transfer, frontend, tests, docs.
- Placeholder scan: no intentional TBD/TODO placeholders remain; each task has concrete file paths and commands.
- Type consistency: backend uses `RoomServicePriceResolver`, `RoomService`, `RoomServicePrice`, `Contract`, `Carbon`; frontend uses existing `price/base_price/effective_price` keys.
