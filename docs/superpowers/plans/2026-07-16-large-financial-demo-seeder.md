# Large Financial Demo Seeder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây dựng và chạy một seeder Laravel độc lập chỉ bổ sung 12 tòa, 120 phòng, 2.400 khách thuê và lịch sử hóa đơn/doanh thu 01/2025–06/2026 mà không sửa dữ liệu cũ.

**Architecture:** Một class dataset thuần PHP sinh mã/tên/ngày/số tiền deterministic; một seeder điều phối transaction và bulk insert theo dependency graph; feature test đối soát count, tính idempotent, bảo toàn dữ liệu cũ và các bất biến tài chính. Seeder không được đăng ký vào `DatabaseSeeder` và chỉ chạy bằng `--class`.

**Tech Stack:** PHP 8.3, Laravel 13, Query Builder, Carbon, PHPUnit 12, SQLite in-memory cho test, MySQL 8 cho lần seed demo thật.

---

## File map

- Create `BE_StayHub/database/seeders/Support/LargeFinancialDemoDataset.php`: hằng số namespace, danh sách tòa/quản lý, periods, tên tenant, mã unique, consumption và payment scenario deterministic.
- Create `BE_StayHub/database/seeders/StayHubLargeFinancialDemoSeeder.php`: transaction, lookup danh mục, chunked inserts và verifier hậu điều kiện.
- Create `BE_StayHub/tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php`: integration tests của seeder trên schema thật.
- Modify `docs/superpowers/plans/2026-07-16-large-financial-demo-seeder.md`: đánh dấu checkbox khi thực thi.
- Do not modify `BE_StayHub/database/seeders/DatabaseSeeder.php` or existing demo seeders.

### Task 1: Dataset deterministic cho tên Việt Nam và tài chính

**Files:**
- Create: `BE_StayHub/database/seeders/Support/LargeFinancialDemoDataset.php`
- Test: `BE_StayHub/tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php`

- [ ] **Step 1: Viết test RED cho dataset**

Tạo test dùng `RefreshDatabase` với các assertion cụ thể:

```php
public function test_dataset_has_exact_vietnamese_scope_and_periods(): void
{
    $dataset = new LargeFinancialDemoDataset();

    $this->assertCount(12, $dataset->buildings());
    $this->assertSame('Ký túc xá Hoa Phượng Đỏ', $dataset->buildings()[0]['name']);
    $this->assertSame('Ký túc xá Văn Thánh', $dataset->buildings()[11]['name']);
    $this->assertCount(18, $dataset->periods());
    $this->assertSame('2025-01', $dataset->periods()[0]->format('Y-m'));
    $this->assertSame('2026-06', $dataset->periods()[17]->format('Y-m'));
    $this->assertSame('showcase26.b01.p101.t01@demo.example.test', $dataset->tenantEmail(1, 101, 1));
    $this->assertSame('showcase26_b01_p101_t01', $dataset->tenantUsername(1, 101, 1));
}

public function test_dataset_financial_values_are_deterministic_and_valid(): void
{
    $dataset = new LargeFinancialDemoDataset();
    $period = $dataset->periods()[0];

    $this->assertSame($dataset->electricConsumption(2, 104, $period), $dataset->electricConsumption(2, 104, $period));
    $this->assertGreaterThan(0, $dataset->electricConsumption(2, 104, $period));
    $this->assertGreaterThan(0, $dataset->waterConsumption(2, 104, $period));
    $this->assertContains($dataset->paymentScenario(2, 104, $period), ['paid', 'paid_split', 'paid_late', 'partial', 'unpaid', 'cancelled']);
}
```

- [ ] **Step 2: Chạy test và xác nhận RED**

Run:

```bash
cd BE_StayHub && php artisan test tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php --filter=dataset
```

Expected: FAIL vì class `Database\Seeders\Support\LargeFinancialDemoDataset` chưa tồn tại.

- [ ] **Step 3: Implement dataset tối thiểu**

Class phải khai báo rõ:

```php
final class LargeFinancialDemoDataset
{
    public const PREFIX = 'SHOWCASE26';
    public const BUILDING_COUNT = 12;
    public const ROOMS_PER_BUILDING = 10;
    public const TENANTS_PER_ROOM = 20;

    public function buildings(): array;
    public function periods(): array;
    public function roomNumber(int $buildingNumber, int $roomPosition): int;
    public function tenantUsername(int $buildingNumber, int $roomNumber, int $tenantPosition): string;
    public function tenantEmail(int $buildingNumber, int $roomNumber, int $tenantPosition): string;
    public function tenantPhone(int $globalTenantNumber): string;
    public function tenantIdentityNumber(int $globalTenantNumber): string;
    public function tenantName(int $globalTenantNumber): string;
    public function roomPrice(int $buildingNumber, int $roomPosition): int;
    public function electricConsumption(int $buildingNumber, int $roomNumber, CarbonImmutable $period): int;
    public function waterConsumption(int $buildingNumber, int $roomNumber, CarbonImmutable $period): int;
    public function paymentScenario(int $buildingNumber, int $roomNumber, CarbonImmutable $period): string;
}
```

`buildings()` trả đúng 12 tên đã duyệt cùng 12 địa chỉ TP.HCM và 12 hồ sơ quản lý Việt Nam. `periods()` trả CarbonImmutable đầu tháng từ 2025-01-01 đến 2026-06-01. Phone dùng dải `098` + 7 chữ số; CCCD dùng `0792` + 8 chữ số. Scenario dùng modulo deterministic, trong đó kỳ cũ chủ yếu paid và tháng 05–06/2026 có đủ partial/unpaid/cancelled.

- [ ] **Step 4: Chạy test dataset và xác nhận GREEN**

Run command ở Step 2. Expected: 2 tests PASS.

- [ ] **Step 5: Chạy Pint cho file mới**

```bash
cd BE_StayHub && vendor/bin/pint database/seeders/Support/LargeFinancialDemoDataset.php tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php
```

Expected: exit 0.

### Task 2: Seeder tạo cấu trúc tòa/phòng/tenant/hợp đồng nhưng không chạm dữ liệu cũ

**Files:**
- Create: `BE_StayHub/database/seeders/StayHubLargeFinancialDemoSeeder.php`
- Modify: `BE_StayHub/tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php`

- [ ] **Step 1: Viết test RED cho scope và preservation**

Test tạo trước một admin, region, building và tenant không thuộc namespace `SHOWCASE26`; lưu snapshot. Gọi seeder trực tiếp rồi assert:

```php
$this->seed(StayHubLargeFinancialDemoSeeder::class);

$this->assertDatabaseCount('buildings', 13);
$this->assertSame(12, DB::table('buildings')->where('slug', 'like', 'showcase26-%')->count());
$this->assertSame(120, DB::table('rooms')->where('room_number', 'like', 'B__-P___')->count());
$this->assertSame(2400, DB::table('tenants')->where('username', 'like', 'showcase26_%')->count());
$this->assertSame(120, DB::table('contracts')->where('contract_code', 'like', 'SHOWCASE26-HD-%')->count());
$this->assertSame($legacySnapshot, DB::table('buildings')->where('id', $legacyBuildingId)->first());
```

Assert thêm cho toàn bộ dữ liệu mới:

- 12 admin manager, email `@demo.example.test`, password `Hash::check('12345678', ...)`.
- Tenant email giả, password check, mọi ảnh null.
- Mỗi room `max_occupants = current_occupants = 20`.
- Mỗi contract có đúng 20 `contract_tenants`, một representative, active và cọc paid.
- Không có building_images/room_images thuộc 12 tòa mới.

- [ ] **Step 2: Chạy test và xác nhận RED**

```bash
cd BE_StayHub && php artisan test tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php --filter=structure
```

Expected: FAIL vì seeder class chưa tồn tại.

- [ ] **Step 3: Implement transaction và các batch cấu trúc**

Seeder dùng `WithoutModelEvents`, một `DB::transaction`, `Hash::make('12345678')` một lần và các method:

```php
public function run(): void;
private function seedOwnerAndManagers(string $passwordHash): array;
private function resolveRegion(int $ownerId): int;
private function seedRoomType(int $ownerId): int;
private function resolveServices(int $ownerId): array;
private function seedBuildings(array $managerIds, int $ownerId, int $regionId): array;
private function seedRooms(array $buildingIds, array $managerIds, int $roomTypeId): array;
private function seedTenants(array $buildingIds, array $managerIds, string $passwordHash): array;
private function seedContractsAndOccupancy(array $roomIds, array $tenantIds, array $managerIds): array;
```

Mọi row chỉ insert nếu unique key `SHOWCASE26` chưa có; dùng `insertOrIgnore` theo chunks 250/500 rồi query ID map theo unique keys. Nếu namespace tồn tại thiếu/mâu thuẫn, ném `RuntimeException` trước khi ghi tiếp. Không update record legacy, không update toàn bộ rooms và không gọi seeder cũ.

Tạo một owner `showcase26_owner@demo.example.test` và 12 manager. Tạo region riêng `SHOWCASE26-HCM` để không cập nhật RegionSeeder cũ. Tạo room type riêng `showcase26-ky-tuc-xa-20-nguoi`. Contracts có ngày 2025-01-01 đến 2026-12-31, deposit = room price × 2, payment_status success, representative tenant đúng pivot; deposit transaction mã tham chiếu `SHOWCASE26-COC-*`.

- [ ] **Step 4: Chạy structure test và xác nhận GREEN**

Run command ở Step 2. Expected: PASS.

### Task 3: Dịch vụ, đồng hồ và chuỗi chỉ số 18 tháng

**Files:**
- Modify: `BE_StayHub/database/seeders/StayHubLargeFinancialDemoSeeder.php`
- Modify: `BE_StayHub/tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php`

- [ ] **Step 1: Viết test RED cho utilities**

Sau seed, assert:

```php
$this->assertSame(240, $this->showcaseMeterQuery()->count());
$this->assertSame(4320, $this->showcaseReadingQuery()->count());

$invalidReadings = $this->showcaseReadingQuery()
    ->whereRaw('consumption != current_reading - previous_reading')
    ->orWhereNull('contract_id')
    ->orWhere('status', '!=', MeterReading::STATUS_INVOICED)
    ->count();
$this->assertSame(0, $invalidReadings);
```

Assert mỗi room có điện, nước, internet, rác, vệ sinh active; giá điện/nước cấp tòa và room_service_prices hiệu lực `2025-01-01`; mọi image_path đồng hồ/reading là null; periods liên tục từ 2025-01 đến 2026-06.

- [ ] **Step 2: Chạy test và xác nhận RED**

```bash
cd BE_StayHub && php artisan test tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php --filter=utilities
```

Expected: FAIL vì chưa có meters/readings.

- [ ] **Step 3: Implement utilities theo chunk**

Thêm các method:

```php
private function seedServicePrices(array $buildingIds, array $services, array $managerIds): void;
private function seedRoomServices(array $roomIds, array $services, array $managerIds): array;
private function seedMeters(array $roomIds, array $services): array;
private function seedMeterReadings(array $meterIds, array $contractIds, array $managerIds): array;
```

Giá: điện 3.800–4.200/kWh theo tòa, nước 16.000–19.000/m³, internet 120.000/phòng, rác 30.000/người, vệ sinh 60.000/phòng. Chỉ số bắt đầu deterministic; mỗi kỳ dùng previous của kỳ trước rồi cộng consumption. Unique reading gồm meter_device_id + contract_id + year + month. Mọi ảnh null.

- [ ] **Step 4: Chạy utilities test và xác nhận GREEN**

Run command ở Step 2. Expected: PASS.

### Task 4: Hóa đơn, items và payments khớp doanh thu

**Files:**
- Modify: `BE_StayHub/database/seeders/StayHubLargeFinancialDemoSeeder.php`
- Modify: `BE_StayHub/tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php`

- [ ] **Step 1: Viết test RED cho invoice invariants**

Assert:

```php
$this->assertSame(2160, $this->showcaseInvoiceQuery()->count());

$brokenTotals = DB::table('invoices')
    ->leftJoin('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
    ->where('invoices.invoice_code', 'like', 'SHOWCASE26-HDD-%')
    ->groupBy('invoices.id', 'invoices.total_amount')
    ->havingRaw('ABS(invoices.total_amount - SUM(invoice_items.amount)) >= 0.01')
    ->count();
$this->assertSame(0, $brokenTotals);
```

Viết query riêng đối soát `paid_amount` với confirmed real payments và `remaining_amount`. Assert 18 kỳ/hợp đồng; mọi invoice có room/electric/water/internet/trash/cleaning items; meter items liên kết reading. Assert có đủ paid, partial/overdue, unpaid/overdue và cancelled; cancelled không confirmed payment, remaining 0. Assert pending payment có proof_image null và không được cộng paid.

- [ ] **Step 2: Chạy test và xác nhận RED**

```bash
cd BE_StayHub && php artisan test tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php --filter=invoices
```

Expected: FAIL vì chưa có invoices/items/payments.

- [ ] **Step 3: Implement invoice rows, items và payments**

Thêm:

```php
private function seedInvoicesAndItems(array $contractIds, array $roomIds, array $readingIds, array $services, array $managerIds): array;
private function seedPayments(array $invoices, array $managerIds): void;
private function invoiceStatusAndAmounts(string $scenario, string $totalAmount, CarbonImmutable $dueDate): array;
```

Mỗi invoice có 6 item cố định; một số deterministic có item phụ thu/giảm trừ. Dùng integer VND khi tính rồi format decimal `.00`. Issued_at là ngày cuối kỳ lúc 08:00, due ngày 5 tháng kế. Các kỳ cũ chủ yếu paid; `paid_split` tạo 2 confirmed payments tổng đúng paid_amount; `paid_late` dùng payment_date tháng kế; partial có confirmed payment khoảng 50%; unpaid không confirmed; cancelled có total/items để xem lịch sử nhưng remaining 0 và không payment confirmed. Một số partial/unpaid có thêm pending payment nhưng không cộng `paid_amount`.

- [ ] **Step 4: Chạy invoice test và xác nhận GREEN**

Run command ở Step 2. Expected: PASS.

### Task 5: Idempotency, full verification và vận hành trên MySQL hiện có

**Files:**
- Modify: `BE_StayHub/database/seeders/StayHubLargeFinancialDemoSeeder.php`
- Modify: `BE_StayHub/tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php`
- Modify: `docs/superpowers/plans/2026-07-16-large-financial-demo-seeder.md`

- [ ] **Step 1: Viết test RED cho chạy lần hai**

Trong test, seed lần đầu, snapshot counts + checksum các bảng namespace và snapshot legacy, seed lần hai, assert tất cả bằng nhau. Test thêm rằng `DatabaseSeeder.php` không chứa `StayHubLargeFinancialDemoSeeder::class`.

- [ ] **Step 2: Chạy test và xác nhận RED nếu seeder còn thay đổi dữ liệu**

```bash
cd BE_StayHub && php artisan test tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php --filter=idempotent
```

Expected trước fix: FAIL nếu timestamp/hash/row thay đổi; sau đó dùng lỗi này để hoàn thiện guard.

- [ ] **Step 3: Hoàn thiện guard và verifier trong seeder**

Đầu `run()`, nếu dataset namespace đã đầy đủ, chạy verification read-only rồi return. Nếu không có namespace, seed mới. Nếu có một phần, throw rõ ràng, không sửa. Cuối transaction gọi verifier đếm đúng 12/120/2400/120/4320/2160 và kiểm tra tổng tiền; throw sẽ rollback toàn bộ lần chạy mới.

- [ ] **Step 4: Chạy test file đầy đủ**

```bash
cd BE_StayHub && php artisan test tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php
```

Expected: tất cả test PASS, 0 failures.

- [ ] **Step 5: Chạy regression suite tài chính**

```bash
cd BE_StayHub && php artisan test tests/Feature/Admin/FinancialReportTest.php tests/Feature/Admin/DashboardControllerTest.php tests/Feature/Admin/InvoiceControllerTest.php
```

Expected: PASS, 0 failures.

- [ ] **Step 6: Format và static syntax check**

```bash
cd BE_StayHub && vendor/bin/pint database/seeders/Support/LargeFinancialDemoDataset.php database/seeders/StayHubLargeFinancialDemoSeeder.php tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php
cd BE_StayHub && php -l database/seeders/Support/LargeFinancialDemoDataset.php && php -l database/seeders/StayHubLargeFinancialDemoSeeder.php && php -l tests/Feature/Database/StayHubLargeFinancialDemoSeederTest.php
```

Expected: Pint exit 0; cả ba file `No syntax errors detected`.

- [ ] **Step 7: Chạy seeder riêng trên MySQL hiện có**

Không chạy `db:seed` mặc định. Chạy đúng:

```bash
docker exec laravel_app php artisan db:seed --class='Database\\Seeders\\StayHubLargeFinancialDemoSeeder' --force
```

Expected: exit 0; seeder báo hoàn tất hoặc bộ namespace đã đầy đủ. Nếu môi trường không có Docker, ghi nhận blocker và không thay bằng `migrate:fresh` hay kết nối DB khác.

- [ ] **Step 8: Đối soát dữ liệu MySQL sau seed**

Chạy một `php artisan tinker --execute` read-only để in JSON counts và các tổng mismatch. Expected chính xác:

```json
{"buildings":12,"rooms":120,"tenants":2400,"contracts":120,"readings":4320,"invoices":2160,"invoice_total_mismatches":0,"payment_total_mismatches":0}
```

- [ ] **Step 9: Reload Octane nếu app container đang chạy**

```bash
docker exec laravel_app php artisan octane:reload
```

Expected: exit 0.

### Task 6: Hai vòng review và handoff

**Files:**
- Review all files listed in File map.

- [ ] **Step 1: Spec compliance review**

Reviewer đối chiếu từng mục của `docs/superpowers/specs/2026-07-16-large-financial-demo-seeder-design.md`, đặc biệt no-delete/no-update-legacy, 12/120/2400/2160, ảnh null, password/email và doanh thu theo confirmed payment date. Mọi gap phải sửa và re-review.

- [ ] **Step 2: Code quality review**

Reviewer kiểm tra chunking, memory, Query Builder compatibility SQLite/MySQL, decimal rounding, unique-key collisions, transaction size, method boundaries và test quality. Critical/Important phải sửa và re-review.

- [ ] **Step 3: Fresh final verification**

Chạy lại test seeder, regression tài chính, Pint check, syntax check và `git diff --check`. Chỉ báo hoàn thành dựa trên output mới nhất.
