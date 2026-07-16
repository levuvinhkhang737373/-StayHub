<?php

namespace Tests\Feature\Database;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\StayHubLargeFinancialDemoSeeder;
use Database\Seeders\Support\LargeFinancialDemoDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class StayHubLargeFinancialDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_does_not_change_existing_showcase_or_legacy_rows(): void
    {
        $timestamp = CarbonImmutable::parse('2024-01-02 03:04:05');
        $legacyAdminId = DB::table('admins')->insertGetId([
            'username' => 'legacy_idempotency_admin',
            'full_name' => 'Quản lý dữ liệu cũ',
            'email' => 'legacy.idempotency@example.test',
            'phone' => '0912345678',
            'password' => Hash::make('legacy-password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'avatar_url' => null,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'date_of_birth' => '1985-05-06',
            'address' => 'TP.HCM',
            'image_path_faceid' => null,
            'created_faceid_at' => null,
            'updated_faceid_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        $before = $this->showcaseSnapshot();
        $legacyBefore = (array) DB::table('admins')->find($legacyAdminId);

        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        $this->assertSame($before, $this->showcaseSnapshot());
        $this->assertSame($legacyBefore, (array) DB::table('admins')->find($legacyAdminId));
        $this->assertStringNotContainsString(
            'StayHubLargeFinancialDemoSeeder',
            file_get_contents(database_path('seeders/DatabaseSeeder.php')),
        );
    }

    public function test_seeder_ignores_a_similarly_named_legacy_admin_on_mysql_like_patterns(): void
    {
        $timestamp = CarbonImmutable::parse('2024-01-02 03:04:05');
        $legacyAdminId = DB::table('admins')->insertGetId([
            'username' => 'showcase26Xlegacy',
            'full_name' => 'Quản lý dữ liệu gần giống namespace',
            'email' => 'showcase26xlegacy@example.test',
            'phone' => '0912345679',
            'password' => Hash::make('legacy-password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'avatar_url' => null,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'date_of_birth' => '1985-05-06',
            'address' => 'TP.HCM',
            'image_path_faceid' => null,
            'created_faceid_at' => null,
            'updated_faceid_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $snapshot = (array) DB::table('admins')->find($legacyAdminId);

        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        $this->assertSame($snapshot, (array) DB::table('admins')->find($legacyAdminId));
        $this->assertSame(1, DB::table('admins')->where('username', 'showcase26_owner')->count());
    }

    public function test_complete_namespace_verifier_rejects_a_same_count_natural_key_corruption_read_only(): void
    {
        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        DB::table('buildings')
            ->where('slug', 'showcase26-b01')
            ->update(['slug' => 'showcase26-b99']);
        $snapshot = (array) DB::table('buildings')->where('slug', 'showcase26-b99')->first();

        $exception = null;

        try {
            $this->seed(StayHubLargeFinancialDemoSeeder::class);
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('Dữ liệu SHOWCASE26 đã tồn tại', $exception->getMessage());
        $this->assertSame($snapshot, (array) DB::table('buildings')->where('slug', 'showcase26-b99')->first());
        $this->assertSame(0, DB::table('buildings')->where('slug', 'showcase26-b01')->count());
    }

    public function test_complete_namespace_verifier_rejects_an_extra_literal_namespace_key_read_only(): void
    {
        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        $adminId = DB::table('admins')->insertGetId([
            'username' => 'showcase26_intruder',
            'full_name' => 'Quản lý ngoài tập dữ liệu demo',
            'email' => 'showcase26_intruder@example.test',
            'phone' => '0912345680',
            'password' => Hash::make('intruder-password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'avatar_url' => null,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'date_of_birth' => '1985-05-06',
            'address' => 'TP.HCM',
            'image_path_faceid' => null,
            'created_faceid_at' => null,
            'updated_faceid_at' => null,
            'created_at' => '2024-01-02 03:04:05',
            'updated_at' => '2024-01-02 03:04:05',
        ]);
        $snapshot = (array) DB::table('admins')->find($adminId);

        $exception = null;

        try {
            $this->seed(StayHubLargeFinancialDemoSeeder::class);
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('Dữ liệu SHOWCASE26 đã tồn tại', $exception->getMessage());
        $this->assertSame($snapshot, (array) DB::table('admins')->find($adminId));
    }

    public function test_seeder_creates_exact_generated_natural_key_sets(): void
    {
        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        $dataset = new LargeFinancialDemoDataset;
        $adminUsernames = ['showcase26_owner'];
        $buildingSlugs = [];
        $roomSlugs = [];
        $tenantUsernames = [];
        $contractCodes = [];
        $meterCodes = [];
        $invoiceCodes = [];
        $paymentCodes = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            $adminUsernames[] = sprintf('showcase26_manager_b%02d', $buildingNumber);
            $buildingSlugs[] = sprintf('showcase26-b%02d', $buildingNumber);

            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $dataset->roomNumber($buildingNumber, $roomPosition);
                $roomSlugs[] = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);
                $contractCodes[] = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);
                $meterCodes[] = sprintf('SHOWCASE26-DIEN-B%02d-P%d', $buildingNumber, $roomNumber);
                $meterCodes[] = sprintf('SHOWCASE26-NUOC-B%02d-P%d', $buildingNumber, $roomNumber);

                foreach (range(1, LargeFinancialDemoDataset::TENANTS_PER_ROOM) as $tenantPosition) {
                    $tenantUsernames[] = $dataset->tenantUsername($buildingNumber, $roomNumber, $tenantPosition);
                }

                foreach ($dataset->periods() as $period) {
                    $invoiceCode = sprintf(
                        'SHOWCASE26-HDD-B%02d-P%d-%s',
                        $buildingNumber,
                        $roomNumber,
                        $period->format('Ym'),
                    );
                    $invoiceCodes[] = $invoiceCode;
                    $paymentBase = str_replace('SHOWCASE26-HDD-', 'SHOWCASE26-TT-', $invoiceCode);
                    $scenario = $dataset->paymentScenario($buildingNumber, $roomNumber, $period);
                    $suffixes = match ($scenario) {
                        'paid_split' => ['01', '02'],
                        'partial' => ['01', 'PENDING'],
                        'unpaid' => ['PENDING'],
                        'cancelled' => ['CANCELLED'],
                        default => ['01'],
                    };

                    foreach ($suffixes as $suffix) {
                        $paymentCodes[] = $paymentBase.'-'.$suffix;
                    }
                }
            }
        }

        $buildingIds = DB::table('buildings')->whereIn('slug', $buildingSlugs)->pluck('id');
        $roomIds = DB::table('rooms')->whereIn('building_id', $buildingIds)->pluck('id');
        $contractIds = DB::table('contracts')->whereIn('room_id', $roomIds)->pluck('id');
        $invoiceIds = DB::table('invoices')->whereIn('contract_id', $contractIds)->pluck('id');

        $this->assertExactKeys($adminUsernames, DB::table('admins')->whereIn('username', $adminUsernames), 'username');
        $this->assertExactKeys($buildingSlugs, DB::table('buildings')->where('region_id', DB::table('regions')->where('code', 'SHOWCASE26-HCM')->value('id')), 'slug');
        $this->assertExactKeys($roomSlugs, DB::table('rooms')->whereIn('building_id', $buildingIds), 'slug');
        $this->assertExactKeys($tenantUsernames, DB::table('tenants')->whereIn('building_id', $buildingIds), 'username');
        $this->assertExactKeys($contractCodes, DB::table('contracts')->whereIn('room_id', $roomIds), 'contract_code');
        $this->assertExactKeys($meterCodes, DB::table('meter_devices')->whereIn('room_id', $roomIds), 'meter_code');
        $this->assertExactKeys($invoiceCodes, DB::table('invoices')->whereIn('contract_id', $contractIds), 'invoice_code');
        $this->assertExactKeys($paymentCodes, DB::table('payments')->whereIn('invoice_id', $invoiceIds), 'payment_code');
    }

    public function test_seeder_rejects_a_partial_nonmeter_service_namespace_without_writing(): void
    {
        $serviceId = DB::table('services')->insertGetId([
            'name' => 'Internet SHOWCASE26',
            'slug' => 'showcase26-internet',
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_required' => false,
            'is_active' => true,
            'created_by' => null,
            'created_at' => '2024-01-02 03:04:05',
            'updated_at' => '2024-01-02 03:04:05',
        ]);
        $snapshot = (array) DB::table('services')->find($serviceId);

        try {
            $this->seed(StayHubLargeFinancialDemoSeeder::class);
            $this->fail('Seeder phải từ chối namespace SHOWCASE26 chưa hoàn chỉnh.');
        } catch (RuntimeException $runtimeException) {
            $this->assertStringContainsString('Dữ liệu SHOWCASE26 đã tồn tại', $runtimeException->getMessage());
        }

        $this->assertSame($snapshot, (array) DB::table('services')->find($serviceId));
        $this->assertSame(0, DB::table('admins')->where('username', 'showcase26_owner')->count());
    }

    public function test_dataset_has_exact_vietnamese_scope_and_periods(): void
    {
        $dataset = new LargeFinancialDemoDataset;
        $buildings = $dataset->buildings();
        $periods = $dataset->periods();

        $this->assertSame('SHOWCASE26', LargeFinancialDemoDataset::PREFIX);
        $this->assertSame(12, LargeFinancialDemoDataset::BUILDING_COUNT);
        $this->assertSame(10, LargeFinancialDemoDataset::ROOMS_PER_BUILDING);
        $this->assertSame(20, LargeFinancialDemoDataset::TENANTS_PER_ROOM);
        $this->assertCount(12, $buildings);
        $this->assertSame('Ký túc xá Hoa Phượng Đỏ', $buildings[0]['name']);
        $this->assertSame('Ký túc xá Văn Thánh', $buildings[11]['name']);

        foreach ($buildings as $building) {
            $this->assertMatchesRegularExpression('/^Ký túc xá [\p{L} ]+$/u', $building['name']);
            $this->assertStringContainsString('TP.HCM', $building['address']);
            $this->assertMatchesRegularExpression('/^[\p{L}]+(?: [\p{L}]+)+$/u', $building['manager']['full_name']);
            $this->assertContains($building['manager']['gender'], [1, 2]);
        }

        $this->assertCount(18, $periods);
        $this->assertSame('2025-01', $periods[0]->format('Y-m'));
        $this->assertSame('2026-06', $periods[17]->format('Y-m'));
        $this->assertSame('showcase26.b01.p101.t01@demo.example.test', $dataset->tenantEmail(1, 101, 1));
        $this->assertSame('showcase26_b01_p101_t01', $dataset->tenantUsername(1, 101, 1));
    }

    public function test_dataset_financial_identifiers_and_room_numbers_are_deterministic_and_valid(): void
    {
        $dataset = new LargeFinancialDemoDataset;
        $period = $dataset->periods()[0];

        $this->assertSame(
            $dataset->electricConsumption(2, 104, $period),
            $dataset->electricConsumption(2, 104, $period),
        );
        $this->assertSame(
            $dataset->waterConsumption(2, 104, $period),
            $dataset->waterConsumption(2, 104, $period),
        );
        $this->assertGreaterThan(0, $dataset->electricConsumption(2, 104, $period));
        $this->assertGreaterThan(0, $dataset->waterConsumption(2, 104, $period));
        $this->assertContains(
            $dataset->paymentScenario(2, 104, $period),
            ['paid', 'paid_split', 'paid_late', 'partial', 'unpaid', 'cancelled'],
        );

        $phones = array_map($dataset->tenantPhone(...), range(1, 2400));
        $identityNumbers = array_map($dataset->tenantIdentityNumber(...), range(1, 2400));

        $this->assertCount(2400, array_unique($phones));
        $this->assertCount(2400, array_unique($identityNumbers));
        $this->assertContainsOnlyString($phones);
        $this->assertContainsOnlyString($identityNumbers);
        $this->assertSame(2400, count(array_filter($phones, fn (string $phone): bool => preg_match('/^098\d{7}$/', $phone) === 1)));
        $this->assertSame(2400, count(array_filter($identityNumbers, fn (string $identityNumber): bool => preg_match('/^0792\d{8}$/', $identityNumber) === 1)));

        $this->assertSame(
            [101, 102, 201, 202, 301, 302, 401, 402, 501, 502],
            array_map(fn (int $position): int => $dataset->roomNumber(1, $position), range(1, 10)),
        );

        $tenantNames = array_map($dataset->tenantName(...), range(1, 2400));

        $this->assertGreaterThanOrEqual(1920, count(array_unique($tenantNames)));
        $this->assertSame(2400, count(array_filter(
            $tenantNames,
            fn (string $name): bool => preg_match('/^(Nguyễn|Trần|Lê|Phạm|Hoàng|Huỳnh|Phan|Vũ|Võ|Đặng|Bùi|Đỗ) [\p{L}]+ [\p{L}]+$/u', $name) === 1,
        )));

        $roomPrices = [];

        foreach (range(1, 12) as $buildingNumber) {
            foreach (range(1, 10) as $roomPosition) {
                $roomPrice = $dataset->roomPrice($buildingNumber, $roomPosition);

                $this->assertSame($roomPrice, $dataset->roomPrice($buildingNumber, $roomPosition));
                $this->assertGreaterThan(0, $roomPrice);
                $roomPrices[] = $roomPrice;
            }
        }

        $this->assertGreaterThan(1, count(array_unique($roomPrices)));
    }

    public function test_dataset_payment_scenarios_match_historical_and_recent_distribution(): void
    {
        $dataset = new LargeFinancialDemoDataset;
        $periods = $dataset->periods();
        $historicalScenarios = [];

        foreach (array_slice($periods, 0, 16) as $period) {
            array_push($historicalScenarios, ...$this->scenariosForPeriod($dataset, $period));
        }

        $this->assertSame([], array_values(array_diff(
            array_unique($historicalScenarios),
            ['paid', 'paid_split', 'paid_late'],
        )));
        $this->assertGreaterThan(
            count($historicalScenarios) / 2,
            count(array_filter($historicalScenarios, fn (string $scenario): bool => $scenario === 'paid')),
        );

        $expectedRecentScenarios = ['cancelled', 'paid', 'paid_late', 'paid_split', 'partial', 'unpaid'];

        foreach ([$periods[16], $periods[17]] as $period) {
            $scenariosForPeriod = $this->scenariosForPeriod($dataset, $period);
            $recentScenarios = array_values(array_unique($scenariosForPeriod));
            $scenarioCounts = array_count_values($scenariosForPeriod);
            sort($recentScenarios);

            $this->assertSame($expectedRecentScenarios, $recentScenarios, "Thiếu kịch bản thanh toán kỳ {$period->format('Y-m')}");
            $this->assertLessThanOrEqual(3, $scenarioCounts['cancelled'], "Quá nhiều hóa đơn hủy kỳ {$period->format('Y-m')}");
            $this->assertGreaterThan(
                max($scenarioCounts['partial'], $scenarioCounts['unpaid'], $scenarioCounts['cancelled']),
                $scenarioCounts['paid'] + $scenarioCounts['paid_split'] + $scenarioCounts['paid_late'],
                "Nhóm hóa đơn đã thanh toán không chiếm ưu thế kỳ {$period->format('Y-m')}",
            );
        }
    }

    public function test_structure_creates_the_showcase_residential_scope_without_changing_legacy_rows(): void
    {
        $timestamp = CarbonImmutable::parse('2024-01-02 03:04:05');
        $legacyAdminId = DB::table('admins')->insertGetId([
            'username' => 'legacy_admin',
            'full_name' => 'Quản lý dữ liệu cũ',
            'email' => 'legacy.admin@example.test',
            'phone' => '0911111111',
            'password' => Hash::make('legacy-password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'avatar_url' => '/legacy/admin.png',
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'date_of_birth' => '1985-05-06',
            'address' => 'Địa chỉ quản trị cũ',
            'image_path_faceid' => '/legacy/admin-face.jpg',
            'created_faceid_at' => $timestamp,
            'updated_faceid_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $legacyRegionId = DB::table('regions')->insertGetId([
            'parent_id' => null,
            'code' => 'LEGACY-HCM',
            'name' => 'Khu vực dữ liệu cũ',
            'path' => '/legacy-hcm',
            'slug' => 'legacy-hcm',
            'description' => 'Không được thay đổi',
            'is_active' => true,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $legacyBuildingId = DB::table('buildings')->insertGetId([
            'region_id' => $legacyRegionId,
            'manager_admin_id' => $legacyAdminId,
            'name' => 'Tòa nhà dữ liệu cũ',
            'slug' => 'legacy-building',
            'address' => 'Địa chỉ tòa nhà cũ',
            'total_floors' => 2,
            'gender_policy' => Building::GENDER_POLICY_MALE,
            'description' => 'Không được thay đổi',
            'status' => Building::STATUS_MAINTENANCE,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $legacyRoomTypeId = DB::table('room_types')->insertGetId([
            'name' => 'Loại phòng dữ liệu cũ',
            'slug' => 'legacy-room-type',
            'description' => 'Không được thay đổi',
            'status' => RoomType::STATUS_INACTIVE,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $legacyTenantId = DB::table('tenants')->insertGetId([
            'building_id' => $legacyBuildingId,
            'created_by' => $legacyAdminId,
            'full_name' => 'Khách Thuê Dữ Liệu Cũ',
            'gender' => Tenant::GENDER_FEMALE,
            'date_of_birth' => '2000-02-03',
            'phone' => '0922222222',
            'email' => 'legacy.tenant@example.test',
            'username' => 'legacy_tenant',
            'password' => Hash::make('legacy-password'),
            'permanent_address' => 'Địa chỉ thường trú cũ',
            'current_address' => 'Địa chỉ hiện tại cũ',
            'avatar_url' => '/legacy/tenant.png',
            'status' => Tenant::STATUS_STOPPED_RENTING,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_date' => '2020-01-02',
            'identity_place' => 'Cơ quan cũ',
            'identity_number' => '001200000001',
            'front_image_url' => '/legacy/front.jpg',
            'back_image_url' => '/legacy/back.jpg',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $legacySnapshots = [
            'admins' => (array) DB::table('admins')->find($legacyAdminId),
            'regions' => (array) DB::table('regions')->find($legacyRegionId),
            'buildings' => (array) DB::table('buildings')->find($legacyBuildingId),
            'room_types' => (array) DB::table('room_types')->find($legacyRoomTypeId),
            'tenants' => (array) DB::table('tenants')->find($legacyTenantId),
        ];

        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        $showcaseBuildingIds = DB::table('buildings')
            ->where('slug', 'like', 'showcase26-%')
            ->pluck('id');
        $showcaseRoomIds = DB::table('rooms')
            ->whereIn('building_id', $showcaseBuildingIds)
            ->pluck('id');
        $showcaseContractIds = DB::table('contracts')
            ->where('contract_code', 'like', 'SHOWCASE26-HD-%')
            ->pluck('id');
        $showcaseAdminRows = DB::table('admins')
            ->where('username', 'like', 'showcase26_%')
            ->get();
        $showcaseManagerRows = $showcaseAdminRows
            ->where('role', Admin::ROLE_BUILDING_MANAGER)
            ->values();
        $showcaseTenantRows = DB::table('tenants')
            ->where('username', 'like', 'showcase26_%')
            ->get();

        $this->assertCount(12, $showcaseBuildingIds);
        $this->assertCount(120, $showcaseRoomIds);
        $this->assertCount(2400, $showcaseTenantRows);
        $this->assertCount(120, $showcaseContractIds);
        $this->assertCount(12, $showcaseManagerRows);
        $this->assertSame(
            'showcase26_owner@demo.example.test',
            $showcaseAdminRows->firstWhere('username', 'showcase26_owner')->email,
        );
        $this->assertTrue($showcaseManagerRows->every(
            fn (object $manager): bool => Hash::check('12345678', $manager->password),
        ));
        $this->assertCount(1, $showcaseTenantRows->pluck('password')->unique());
        $this->assertTrue(Hash::check('12345678', $showcaseTenantRows->first()->password));
        $this->assertTrue($showcaseAdminRows->every(
            fn (object $admin): bool => str_ends_with($admin->email, '@demo.example.test')
                && $admin->avatar_url === null
                && $admin->image_path_faceid === null,
        ));
        $this->assertTrue($showcaseTenantRows->every(
            fn (object $tenant): bool => str_ends_with($tenant->email, '@demo.example.test')
                && $tenant->avatar_url === null
                && $tenant->front_image_url === null
                && $tenant->back_image_url === null,
        ));

        $this->assertSame(120, DB::table('rooms')
            ->whereIn('id', $showcaseRoomIds)
            ->where('max_occupants', 20)
            ->where('current_occupants', 20)
            ->count());
        $this->assertSame(120, DB::table('contracts')
            ->whereIn('id', $showcaseContractIds)
            ->where('status', Contract::STATUS_ACTIVE)
            ->where('payment_status', Contract::PAYMENT_STATUS_SUCCESS)
            ->whereDate('start_date', '2025-01-01')
            ->whereDate('end_date', '2026-12-31')
            ->whereColumn('deposit_amount', '>', 'room_price')
            ->count());

        foreach (DB::table('contracts')->whereIn('id', $showcaseContractIds)->get() as $contract) {
            $activeTenantIds = DB::table('contract_tenants')
                ->where('contract_id', $contract->id)
                ->where('is_staying', true)
                ->whereNull('leave_date')
                ->pluck('tenant_id');
            $depositTransaction = DB::table('contract_deposit_transactions')
                ->where('contract_id', $contract->id)
                ->where('transaction_type', ContractDepositTransaction::TRANSACTION_TYPE_COLLECT)
                ->first();

            $this->assertCount(20, $activeTenantIds);
            $this->assertSame(1, $activeTenantIds->filter(
                fn (int $tenantId): bool => $tenantId === $contract->representative_tenant_id,
            )->count());
            $this->assertNotNull($depositTransaction);
            $this->assertSame(1, DB::table('contract_deposit_transactions')
                ->where('contract_id', $contract->id)
                ->where('transaction_type', ContractDepositTransaction::TRANSACTION_TYPE_COLLECT)
                ->count());
            $this->assertSame((float) $contract->room_price * 2, (float) $contract->deposit_amount);
            $this->assertSame((float) $contract->deposit_amount, (float) $depositTransaction->amount);
        }

        $this->assertSame(0, DB::table('building_images')->whereIn('building_id', $showcaseBuildingIds)->count());
        $this->assertSame(0, DB::table('room_images')->whereIn('room_id', $showcaseRoomIds)->count());

        foreach ($legacySnapshots as $table => $snapshot) {
            $this->assertSame($snapshot, (array) DB::table($table)->find($snapshot['id']));
        }
    }

    public function test_structure_rejects_an_exact_namespace_collision_before_writing_showcase_rows(): void
    {
        $timestamp = CarbonImmutable::parse('2024-04-05 06:07:08');
        $conflictingOwnerId = DB::table('admins')->insertGetId([
            'username' => 'showcase26_owner',
            'full_name' => 'Tài khoản dữ liệu cũ bị trùng khóa',
            'email' => 'legacy.conflict@example.test',
            'phone' => '0933333333',
            'password' => Hash::make('legacy-conflict-password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'avatar_url' => '/legacy/conflict.png',
            'status' => Admin::STATUS_INACTIVE,
            'gender' => Admin::GENDER_FEMALE,
            'date_of_birth' => '1990-09-09',
            'address' => 'Dữ liệu cũ không được thay đổi',
            'image_path_faceid' => '/legacy/conflict-face.jpg',
            'created_faceid_at' => $timestamp,
            'updated_faceid_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $snapshot = (array) DB::table('admins')->find($conflictingOwnerId);

        $exception = null;

        try {
            $this->seed(StayHubLargeFinancialDemoSeeder::class);
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $this->assertNotNull($exception, 'Seeder phải từ chối dữ liệu SHOWCASE26 đã tồn tại.');
        $this->assertStringContainsString('Dữ liệu SHOWCASE26 đã tồn tại', $exception->getMessage());
        $this->assertSame($snapshot, (array) DB::table('admins')->find($conflictingOwnerId));
        $this->assertSame(0, DB::table('regions')->where('code', 'SHOWCASE26-HCM')->count());
        $this->assertSame(0, DB::table('buildings')->where('slug', 'like', 'showcase26-%')->count());
        $this->assertSame(0, DB::table('tenants')->where('username', 'like', 'showcase26_%')->count());
        $this->assertSame(0, DB::table('contracts')->where('contract_code', 'like', 'SHOWCASE26-HD-%')->count());
    }

    public function test_utilities_create_exact_service_assignments_prices_meters_and_progressive_readings(): void
    {
        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        $dataset = new LargeFinancialDemoDataset;
        $serviceDefinitions = [
            'electric' => [Service::CHARGE_METHOD_BY_METER, 3800, 4200],
            'water' => [Service::CHARGE_METHOD_BY_METER, 16000, 19000],
            'showcase26-internet' => [Service::CHARGE_METHOD_BY_ROOM, 120000, 120000],
            'showcase26-trash' => [Service::CHARGE_METHOD_BY_PERSON, 30000, 30000],
            'showcase26-cleaning' => [Service::CHARGE_METHOD_BY_ROOM, 60000, 60000],
        ];
        $serviceSlugs = array_keys($serviceDefinitions);
        $services = DB::table('services')
            ->whereIn('slug', $serviceSlugs)
            ->get()
            ->keyBy('slug');
        $buildingIds = DB::table('buildings')
            ->whereIn('slug', array_map(
                fn (int $number): string => sprintf('showcase26-b%02d', $number),
                range(1, LargeFinancialDemoDataset::BUILDING_COUNT),
            ))
            ->pluck('id');
        $roomIds = DB::table('rooms')
            ->whereIn('building_id', $buildingIds)
            ->pluck('id');

        $this->assertCount(5, $services);

        foreach ($serviceDefinitions as $slug => [$chargeMethod]) {
            $this->assertSame($chargeMethod, (int) $services[$slug]->charge_method);
            $this->assertSame(1, (int) $services[$slug]->is_active);
        }

        $servicePrices = DB::table('service_prices')
            ->whereIn('building_id', $buildingIds)
            ->whereIn('service_id', $services->pluck('id'))
            ->get();

        $this->assertCount(60, $servicePrices);
        $this->assertTrue($servicePrices->every(
            fn (object $price): bool => $price->effective_from === '2025-01-01'
                && $price->effective_to === null
                && (int) $price->status === ServicePrice::STATUS_ACTIVE,
        ));

        foreach ($serviceDefinitions as $slug => [, $minimumPrice, $maximumPrice]) {
            $prices = $servicePrices
                ->where('service_id', $services[$slug]->id)
                ->pluck('price')
                ->map(fn (string|int|float $price): int => (int) $price);

            $this->assertCount(12, $prices);
            $this->assertSame($minimumPrice, $prices->min());
            $this->assertSame($maximumPrice, $prices->max());
        }

        $roomServices = DB::table('room_services')
            ->whereIn('room_id', $roomIds)
            ->whereIn('service_id', $services->pluck('id'))
            ->get();

        $this->assertCount(600, $roomServices);
        $this->assertTrue($roomServices->every(
            fn (object $roomService): bool => (int) $roomService->is_active === 1
                && $roomService->ended_at === null,
        ));
        $this->assertCount(120, $roomServices->groupBy('room_id'));
        $this->assertTrue($roomServices->groupBy('room_id')->every(
            fn ($assignments): bool => $assignments->pluck('service_id')->unique()->count() === 5,
        ));

        $roomServicePrices = DB::table('room_service_prices')
            ->join('room_services', 'room_services.id', '=', 'room_service_prices.room_service_id')
            ->whereIn('room_services.room_id', $roomIds)
            ->whereIn('room_services.service_id', $services->pluck('id'))
            ->get([
                'room_service_prices.*',
                'room_services.service_id',
            ]);

        $this->assertCount(360, $roomServicePrices);
        $this->assertTrue($roomServicePrices->every(
            fn (object $price): bool => $price->contract_id === null
                && $price->effective_from === '2025-01-01'
                && $price->effective_to === null,
        ));

        foreach (['showcase26-internet', 'showcase26-trash', 'showcase26-cleaning'] as $slug) {
            $prices = $roomServicePrices
                ->where('service_id', $services[$slug]->id)
                ->pluck('price')
                ->map(fn (string|int|float $price): int => (int) $price);

            $this->assertCount(120, $prices);
            $this->assertSame($serviceDefinitions[$slug][1], $prices->unique()->sole());
        }

        $expectedMeterCodes = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $dataset->roomNumber($buildingNumber, $roomPosition);
                $expectedMeterCodes[] = sprintf('SHOWCASE26-DIEN-B%02d-P%d', $buildingNumber, $roomNumber);
                $expectedMeterCodes[] = sprintf('SHOWCASE26-NUOC-B%02d-P%d', $buildingNumber, $roomNumber);
            }
        }

        $meters = DB::table('meter_devices')
            ->whereIn('meter_code', $expectedMeterCodes)
            ->get();

        $this->assertCount(240, $meters);
        $this->assertEqualsCanonicalizing($expectedMeterCodes, $meters->pluck('meter_code')->all());
        $this->assertTrue($meters->every(
            fn (object $meter): bool => (int) $meter->status === MeterDevice::STATUS_ACTIVE
                && $meter->image_path === null,
        ));
        $this->assertCount(120, $meters->groupBy('room_id'));
        $this->assertTrue($meters->groupBy('room_id')->every(
            fn ($roomMeters): bool => $roomMeters->pluck('meter_type')->map(fn ($type): int => (int) $type)->sort()->values()->all()
                === [MeterDevice::METER_TYPE_ELECTRIC, MeterDevice::METER_TYPE_WATER],
        ));
        $this->assertSame(120, $meters
            ->where('service_id', $services['electric']->id)
            ->where('meter_type', MeterDevice::METER_TYPE_ELECTRIC)
            ->count());
        $this->assertSame(120, $meters
            ->where('service_id', $services['water']->id)
            ->where('meter_type', MeterDevice::METER_TYPE_WATER)
            ->count());

        $readingQuery = DB::table('meter_readings')
            ->join('meter_devices', 'meter_devices.id', '=', 'meter_readings.meter_device_id')
            ->whereIn('meter_devices.meter_code', $expectedMeterCodes);

        $this->assertSame(4320, (clone $readingQuery)->count());

        $invalidReadings = (clone $readingQuery)
            ->leftJoin('contracts', 'contracts.id', '=', 'meter_readings.contract_id')
            ->where(function ($query): void {
                $query->whereRaw('meter_readings.consumption != meter_readings.current_reading - meter_readings.previous_reading')
                    ->orWhereNull('meter_readings.contract_id')
                    ->orWhereColumn('contracts.room_id', '!=', 'meter_devices.room_id')
                    ->orWhere('meter_readings.status', '!=', MeterReading::STATUS_INVOICED)
                    ->orWhereNotNull('meter_readings.image_path');
            })
            ->count();

        $this->assertSame(0, $invalidReadings);

        $readingsByMeter = (clone $readingQuery)
            ->join('rooms', 'rooms.id', '=', 'meter_devices.room_id')
            ->join('buildings', 'buildings.id', '=', 'rooms.building_id')
            ->orderBy('meter_readings.meter_device_id')
            ->orderBy('meter_readings.billing_year')
            ->orderBy('meter_readings.billing_month')
            ->get([
                'meter_readings.*',
                'meter_devices.initial_reading',
                'meter_devices.meter_code',
                'rooms.room_number',
                'buildings.slug as building_slug',
            ])
            ->groupBy('meter_device_id');
        $expectedPeriods = array_map(
            fn (CarbonImmutable $period): string => $period->format('Y-m'),
            $dataset->periods(),
        );

        $this->assertCount(240, $readingsByMeter);

        foreach ($readingsByMeter as $meterReadings) {
            $this->assertCount(18, $meterReadings);
            $this->assertSame($expectedPeriods, $meterReadings
                ->map(fn (object $reading): string => sprintf('%04d-%02d', $reading->billing_year, $reading->billing_month))
                ->all());

            $previous = (float) $meterReadings->first()->initial_reading;

            foreach ($meterReadings as $index => $reading) {
                $period = $dataset->periods()[$index];
                $buildingNumber = (int) substr($reading->building_slug, -2);
                $roomNumber = (int) substr($reading->room_number, 5);
                $expectedConsumption = str_contains($reading->meter_code, '-DIEN-')
                    ? $dataset->electricConsumption($buildingNumber, $roomNumber, $period)
                    : $dataset->waterConsumption($buildingNumber, $roomNumber, $period);

                $this->assertSame($previous, (float) $reading->previous_reading);
                $this->assertSame((float) $expectedConsumption, (float) $reading->consumption);
                $this->assertSame(
                    (float) $reading->current_reading - (float) $reading->previous_reading,
                    (float) $reading->consumption,
                );
                $this->assertSame($period->endOfMonth()->toDateString(), $reading->reading_date);
                $previous = (float) $reading->current_reading;
            }
        }
    }

    public function test_utilities_reuse_valid_canonical_services_without_modifying_them(): void
    {
        $timestamp = CarbonImmutable::parse('2024-06-07 08:09:10');
        $legacyAdminId = DB::table('admins')->insertGetId([
            'username' => 'legacy_canonical_utility_admin',
            'full_name' => 'Quản lý điện nước chuẩn',
            'email' => 'legacy.canonical.utility@example.test',
            'phone' => '0955555555',
            'password' => Hash::make('legacy-password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'avatar_url' => '/legacy/canonical-utility-admin.png',
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_FEMALE,
            'date_of_birth' => '1985-06-07',
            'address' => 'Địa chỉ quản lý điện nước chuẩn',
            'image_path_faceid' => '/legacy/canonical-utility-admin-face.jpg',
            'created_faceid_at' => $timestamp,
            'updated_faceid_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $serviceIds = [];

        $chargeMethods = [
            'electric' => Service::CHARGE_METHOD_BY_METER,
            'water' => Service::CHARGE_METHOD_BY_METER,
            'internet' => Service::CHARGE_METHOD_BY_ROOM,
            'trash' => Service::CHARGE_METHOD_BY_PERSON,
            'cleaning' => Service::CHARGE_METHOD_BY_ROOM,
        ];

        foreach ($chargeMethods as $slug => $chargeMethod) {
            $serviceIds[$slug] = DB::table('services')->insertGetId([
                'name' => 'Dịch vụ chuẩn cũ '.$slug,
                'slug' => $slug,
                'charge_method' => $chargeMethod,
                'unit_name' => 'đơn vị cũ',
                'is_required' => false,
                'is_active' => false,
                'created_by' => $legacyAdminId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $snapshots = collect($serviceIds)->mapWithKeys(
            fn (int $serviceId, string $slug): array => [$slug => (array) DB::table('services')->find($serviceId)],
        )->all();

        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        foreach ($snapshots as $slug => $snapshot) {
            $this->assertSame($snapshot, (array) DB::table('services')->find($snapshot['id']));
            $this->assertSame(12, DB::table('service_prices')->where('service_id', $serviceIds[$slug])->count());
            $this->assertSame(120, DB::table('room_services')->where('service_id', $serviceIds[$slug])->count());

            if (in_array($slug, ['electric', 'water'], true)) {
                $this->assertSame(120, DB::table('meter_devices')->where('service_id', $serviceIds[$slug])->count());
            }
        }

        $expectedItemTypes = [
            'electric' => InvoiceItem::ITEM_TYPE_ELECTRIC,
            'water' => InvoiceItem::ITEM_TYPE_WATER,
            'internet' => InvoiceItem::ITEM_TYPE_INTERNET,
            'trash' => InvoiceItem::ITEM_TYPE_TRASH,
            'cleaning' => InvoiceItem::ITEM_TYPE_SURCHARGE,
        ];

        foreach ($expectedItemTypes as $slug => $itemType) {
            $this->assertSame(2160, DB::table('invoice_items')
                ->where('service_id', $serviceIds[$slug])
                ->where('item_type', $itemType)
                ->count());
        }
    }

    public function test_utilities_preflight_rejects_an_incompatible_canonical_service_before_writing(): void
    {
        $timestamp = CarbonImmutable::parse('2024-07-08 09:10:11');
        $serviceId = DB::table('services')->insertGetId([
            'name' => 'Điện tính sai phương thức',
            'slug' => 'electric',
            'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
            'unit_name' => 'phòng',
            'is_required' => false,
            'is_active' => true,
            'created_by' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $snapshot = (array) DB::table('services')->find($serviceId);

        $exception = null;

        try {
            $this->seed(StayHubLargeFinancialDemoSeeder::class);
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('electric', $exception->getMessage());
        $this->assertStringContainsString('theo chỉ số', $exception->getMessage());
        $this->assertSame($snapshot, (array) DB::table('services')->find($serviceId));
        $this->assertSame(0, DB::table('admins')->where('username', 'showcase26_owner')->count());
        $this->assertSame(0, DB::table('regions')->where('code', 'SHOWCASE26-HCM')->count());
        $this->assertSame(0, DB::table('buildings')->where('slug', 'like', 'showcase26-%')->count());
    }

    public function test_invoices_create_exact_items_and_real_payment_history(): void
    {
        $this->seed(StayHubLargeFinancialDemoSeeder::class);

        $dataset = new LargeFinancialDemoDataset;
        $invoices = DB::table('invoices')
            ->join('contracts', 'contracts.id', '=', 'invoices.contract_id')
            ->join('rooms', 'rooms.id', '=', 'invoices.room_id')
            ->join('buildings', 'buildings.id', '=', 'rooms.building_id')
            ->where('invoices.invoice_code', 'like', 'SHOWCASE26-HDD-%')
            ->orderBy('invoices.invoice_code')
            ->get([
                'invoices.*',
                'contracts.contract_code',
                'rooms.room_number',
                'buildings.slug as building_slug',
            ]);

        $this->assertCount(2160, $invoices);
        $this->assertSame(
            [Invoice::STATUS_PAID, Invoice::STATUS_OVERDUE, Invoice::STATUS_CANCELLED],
            $invoices->pluck('status')->map(fn ($status): int => (int) $status)->unique()->sort()->values()->all(),
        );

        $expectedPeriods = array_map(
            fn (CarbonImmutable $period): string => $period->format('Y-m'),
            $dataset->periods(),
        );
        $invoicesByContract = $invoices->groupBy('contract_id');

        $this->assertCount(120, $invoicesByContract);

        foreach ($invoicesByContract as $contractInvoices) {
            $this->assertCount(18, $contractInvoices);
            $this->assertSame($expectedPeriods, $contractInvoices
                ->sortBy(fn (object $invoice): string => sprintf('%04d-%02d', $invoice->billing_year, $invoice->billing_month))
                ->map(fn (object $invoice): string => sprintf('%04d-%02d', $invoice->billing_year, $invoice->billing_month))
                ->values()
                ->all());
        }

        $items = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.invoice_code', 'like', 'SHOWCASE26-HDD-%')
            ->orderBy('invoice_items.invoice_id')
            ->orderBy('invoice_items.item_type')
            ->get(['invoice_items.*'])
            ->groupBy('invoice_id');
        $payments = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('invoices.invoice_code', 'like', 'SHOWCASE26-HDD-%')
            ->orderBy('payments.payment_code')
            ->get(['payments.*'])
            ->groupBy('invoice_id');
        $requiredItemTypes = [
            InvoiceItem::ITEM_TYPE_ROOM,
            InvoiceItem::ITEM_TYPE_ELECTRIC,
            InvoiceItem::ITEM_TYPE_WATER,
            InvoiceItem::ITEM_TYPE_INTERNET,
            InvoiceItem::ITEM_TYPE_TRASH,
            InvoiceItem::ITEM_TYPE_SURCHARGE,
        ];
        $pendingCount = 0;
        $latePaymentCount = 0;
        $itemCountDistribution = [];

        $this->assertCount(2160, $items);
        $this->assertSame(13320, $items->flatten(1)->count());
        $this->assertSame(216, $items->flatten(1)->where('item_type', InvoiceItem::ITEM_TYPE_PARKING)->count());
        $this->assertSame(144, $items->flatten(1)->where('item_type', InvoiceItem::ITEM_TYPE_DISCOUNT)->count());
        $this->assertTrue($items->flatten(1)
            ->where('item_type', InvoiceItem::ITEM_TYPE_DISCOUNT)
            ->every(fn (object $item): bool => (float) $item->amount < 0));

        foreach ($invoices as $invoiceIndex => $invoice) {
            $this->assertMatchesRegularExpression(
                '/^SHOWCASE26-HDD-B\d{2}-P\d{3}-\d{6}$/',
                $invoice->invoice_code,
            );

            $period = CarbonImmutable::create((int) $invoice->billing_year, (int) $invoice->billing_month, 1);
            $periodItems = $items->get($invoice->id);
            $invoicePayments = $payments->get($invoice->id, collect());
            $buildingNumber = (int) substr($invoice->building_slug, -2);
            $roomNumber = (int) substr($invoice->room_number, 5);
            $scenario = $dataset->paymentScenario($buildingNumber, $roomNumber, $period);
            $confirmedPayments = $invoicePayments->filter(
                fn (object $payment): bool => (int) $payment->status === Payment::STATUS_CONFIRMED
                    && (int) $payment->is_internal_allocation === 0,
            );
            $pendingPayments = $invoicePayments->where('status', Payment::STATUS_PENDING_CONFIRMATION);

            $this->assertSame($period->startOfMonth()->toDateString(), $invoice->period_start);
            $this->assertSame($period->endOfMonth()->toDateString(), $invoice->period_end);
            $this->assertSame($period->endOfMonth()->setTime(8, 0)->format('Y-m-d H:i:s'), $invoice->issued_at);
            $this->assertSame($period->addMonth()->day(5)->toDateString(), $invoice->due_date);
            $this->assertSame(0.0, (float) $invoice->previous_debt_amount);
            $this->assertSame(1, (int) $invoice->revision);
            $this->assertNull($invoice->reissued_at);
            $this->assertNull($invoice->reissue_reason);
            $globalInvoiceNumber = $invoiceIndex + 1;
            $expectedItemTypes = $requiredItemTypes;

            if ($globalInvoiceNumber % 10 === 0) {
                $expectedItemTypes[] = InvoiceItem::ITEM_TYPE_PARKING;
            }

            if ($globalInvoiceNumber % 15 === 0) {
                $expectedItemTypes[] = InvoiceItem::ITEM_TYPE_DISCOUNT;
            }

            sort($expectedItemTypes);
            $this->assertSame($expectedItemTypes, $periodItems
                ->pluck('item_type')
                ->map(fn ($type): int => (int) $type)
                ->sort()
                ->values()
                ->all());
            $itemCountDistribution[$periodItems->count()] = ($itemCountDistribution[$periodItems->count()] ?? 0) + 1;
            $this->assertSame((float) $invoice->total_amount, $periodItems->sum(fn (object $item): float => (float) $item->amount));
            $this->assertTrue($periodItems->every(
                fn (object $item): bool => (float) $item->amount === (float) $item->quantity * (float) $item->unit_price,
            ));
            $this->assertEqualsCanonicalizing(
                ['Internet', 'Phí rác', 'Tiền nước', 'Tiền phòng', 'Tiền điện', 'Vệ sinh'],
                $periodItems
                    ->whereIn('item_type', $requiredItemTypes)
                    ->pluck('description')
                    ->map(fn (string $description): string => explode(' tháng ', $description)[0])
                    ->all(),
            );
            $this->assertSame(20.0, (float) $periodItems->firstWhere('item_type', InvoiceItem::ITEM_TYPE_TRASH)->quantity);
            $this->assertSame(
                (float) $invoice->paid_amount,
                (float) $confirmedPayments->sum(fn (object $payment): float => (float) $payment->amount),
            );
            $this->assertTrue($invoicePayments->every(
                fn (object $payment): bool => $payment->proof_image === null
                    && (int) $payment->is_internal_allocation === 0
                    && $payment->allocated_from_payment_id === null
                    && $payment->invoice_debt_rollover_id === null
                    && str_starts_with($payment->payment_code, 'SHOWCASE26-TT-'),
            ));

            if ($scenario === 'cancelled') {
                $this->assertSame(Invoice::STATUS_CANCELLED, (int) $invoice->status);
                $this->assertSame(0.0, (float) $invoice->paid_amount);
                $this->assertSame(0.0, (float) $invoice->remaining_amount);
                $this->assertCount(0, $confirmedPayments);
            } elseif (in_array($scenario, ['partial', 'unpaid'], true)) {
                $expectedPaidAmount = $scenario === 'partial'
                    ? floor((float) $invoice->total_amount * 0.5)
                    : 0.0;

                $this->assertSame(Invoice::STATUS_OVERDUE, (int) $invoice->status);
                $this->assertSame($expectedPaidAmount, (float) $invoice->paid_amount);
                $this->assertSame((float) $invoice->total_amount - $expectedPaidAmount, (float) $invoice->remaining_amount);
                $this->assertCount(1, $pendingPayments);
                $pendingCount++;
            } else {
                $this->assertSame(Invoice::STATUS_PAID, (int) $invoice->status);
                $this->assertSame((float) $invoice->total_amount, (float) $invoice->paid_amount);
                $this->assertSame(0.0, (float) $invoice->remaining_amount);
            }

            if ($scenario === 'paid_split') {
                $this->assertCount(2, $confirmedPayments);
                $this->assertSame(
                    [$period->day(20)->toDateString(), $period->endOfMonth()->toDateString()],
                    $confirmedPayments->pluck('payment_date')->map(fn (string $date): string => substr($date, 0, 10))->sort()->values()->all(),
                );
            }

            if ($scenario === 'paid_late') {
                $this->assertCount(1, $confirmedPayments);
                $this->assertSame(
                    $period->addMonth()->day(3)->toDateString(),
                    substr($confirmedPayments->sole()->payment_date, 0, 10),
                );
                $latePaymentCount++;
            }
        }

        $this->assertGreaterThan(0, $pendingCount);
        $this->assertGreaterThan(0, $latePaymentCount);
        ksort($itemCountDistribution);
        $this->assertSame([6 => 1872, 7 => 216, 8 => 72], $itemCountDistribution);

        $brokenUtilityItems = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('meter_readings', 'meter_readings.id', '=', 'invoice_items.meter_reading_id')
            ->join('meter_devices', 'meter_devices.id', '=', 'meter_readings.meter_device_id')
            ->where('invoices.invoice_code', 'like', 'SHOWCASE26-HDD-%')
            ->whereIn('invoice_items.item_type', [InvoiceItem::ITEM_TYPE_ELECTRIC, InvoiceItem::ITEM_TYPE_WATER])
            ->where(function ($query): void {
                $query->whereColumn('invoice_items.service_id', '!=', 'meter_devices.service_id')
                    ->orWhereColumn('meter_readings.contract_id', '!=', 'invoices.contract_id')
                    ->orWhereColumn('meter_devices.room_id', '!=', 'invoices.room_id')
                    ->orWhereColumn('meter_readings.billing_month', '!=', 'invoices.billing_month')
                    ->orWhereColumn('meter_readings.billing_year', '!=', 'invoices.billing_year')
                    ->orWhereRaw('invoice_items.amount != meter_readings.consumption * invoice_items.unit_price');
            })
            ->count();

        $this->assertSame(0, $brokenUtilityItems);
    }

    public function test_utilities_preflight_rejects_linked_showcase_rows_without_touching_legacy_data(): void
    {
        $timestamp = CarbonImmutable::parse('2024-05-06 07:08:09');
        $legacyAdminId = DB::table('admins')->insertGetId([
            'username' => 'legacy_utility_admin',
            'full_name' => 'Quản lý dịch vụ cũ',
            'email' => 'legacy.utility.admin@example.test',
            'phone' => '0944444444',
            'password' => Hash::make('legacy-password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'avatar_url' => '/legacy/utility-admin.png',
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'date_of_birth' => '1984-05-06',
            'address' => 'Địa chỉ quản lý dịch vụ cũ',
            'image_path_faceid' => '/legacy/utility-admin-face.jpg',
            'created_faceid_at' => $timestamp,
            'updated_faceid_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $legacyRegionId = DB::table('regions')->insertGetId([
            'parent_id' => null,
            'code' => 'LEGACY-UTILITY-HCM',
            'name' => 'Khu vực dịch vụ cũ',
            'path' => '/legacy-utility-hcm',
            'slug' => 'legacy-utility-hcm',
            'description' => 'Không được thay đổi',
            'is_active' => true,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $buildingId = DB::table('buildings')->insertGetId([
            'region_id' => $legacyRegionId,
            'manager_admin_id' => $legacyAdminId,
            'name' => 'Tòa nhà xung đột tiện ích',
            'slug' => 'showcase26-b01',
            'address' => 'Địa chỉ tòa nhà xung đột',
            'total_floors' => 1,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
            'description' => 'Không được thay đổi',
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $legacyServiceId = DB::table('services')->insertGetId([
            'name' => 'Dịch vụ cũ',
            'slug' => 'legacy-utility-service',
            'charge_method' => Service::CHARGE_METHOD_FIXED,
            'unit_name' => 'tháng',
            'is_required' => false,
            'is_active' => true,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $legacyPriceId = DB::table('service_prices')->insertGetId([
            'service_id' => $legacyServiceId,
            'building_id' => $buildingId,
            'price' => 99999,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'status' => ServicePrice::STATUS_ACTIVE,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $snapshots = [
            'buildings' => (array) DB::table('buildings')->find($buildingId),
            'services' => (array) DB::table('services')->find($legacyServiceId),
            'service_prices' => (array) DB::table('service_prices')->find($legacyPriceId),
        ];

        $exception = null;

        try {
            $this->seed(StayHubLargeFinancialDemoSeeder::class);
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('Dữ liệu SHOWCASE26 đã tồn tại', $exception->getMessage());

        foreach ($snapshots as $table => $snapshot) {
            $this->assertSame($snapshot, (array) DB::table($table)->find($snapshot['id']));
        }

        $this->assertSame(0, DB::table('admins')->where('username', 'showcase26_owner')->count());
        $this->assertSame(0, DB::table('meter_devices')->where('meter_code', 'SHOWCASE26-DIEN-B01-P101')->count());
    }

    public function test_invoice_preflight_rejects_linked_showcase_rows_without_writing_structure(): void
    {
        $timestamp = CarbonImmutable::parse('2024-06-07 08:09:10');
        $legacyAdminId = DB::table('admins')->insertGetId([
            'username' => 'legacy_invoice_admin',
            'full_name' => 'Quản lý hóa đơn cũ',
            'email' => 'legacy.invoice.admin@example.test',
            'phone' => '0955555555',
            'password' => Hash::make('legacy-password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'avatar_url' => null,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'date_of_birth' => '1984-06-07',
            'address' => 'TP.HCM',
            'image_path_faceid' => null,
            'created_faceid_at' => null,
            'updated_faceid_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $regionId = DB::table('regions')->insertGetId([
            'parent_id' => null,
            'code' => 'LEGACY-INVOICE-HCM',
            'name' => 'Khu vực hóa đơn cũ',
            'path' => '/legacy-invoice-hcm',
            'slug' => 'legacy-invoice-hcm',
            'description' => null,
            'is_active' => true,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $buildingId = DB::table('buildings')->insertGetId([
            'region_id' => $regionId,
            'manager_admin_id' => $legacyAdminId,
            'name' => 'Tòa hóa đơn cũ',
            'slug' => 'legacy-invoice-building',
            'address' => 'TP.HCM',
            'total_floors' => 1,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
            'description' => null,
            'status' => Building::STATUS_ACTIVE,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $roomTypeId = DB::table('room_types')->insertGetId([
            'name' => 'Phòng hóa đơn cũ',
            'slug' => 'legacy-invoice-room-type',
            'description' => null,
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $roomId = DB::table('rooms')->insertGetId([
            'building_id' => $buildingId,
            'room_type_id' => $roomTypeId,
            'room_number' => 'LEGACY-101',
            'slug' => 'legacy-invoice-room',
            'floor' => 1,
            'area_m2' => 20,
            'base_price' => 1000000,
            'max_occupants' => 1,
            'current_occupants' => 0,
            'status' => 1,
            'description' => null,
            'created_by' => $legacyAdminId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $contractId = DB::table('contracts')->insertGetId([
            'contract_code' => 'LEGACY-INVOICE-CONTRACT',
            'room_id' => $roomId,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'actual_end_date' => null,
            'room_price' => 1000000,
            'deposit_amount' => 2000000,
            'status' => Contract::STATUS_ACTIVE,
            'tenant_signed_at' => null,
            'tenant_signature_url' => 'signatures/legacy.png',
            'payment_status' => Contract::PAYMENT_STATUS_SUCCESS,
            'contract_files' => null,
            'note' => null,
            'created_by' => $legacyAdminId,
            'parent_contract_id' => null,
            'renew_from_contract_id' => null,
            'representative_tenant_id' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $invoiceId = DB::table('invoices')->insertGetId([
            'invoice_code' => 'SHOWCASE26-HDD-B01-P101-202501',
            'contract_id' => $contractId,
            'room_id' => $roomId,
            'billing_month' => 1,
            'billing_year' => 2025,
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-31',
            'previous_debt_amount' => 0,
            'total_amount' => 1,
            'paid_amount' => 0,
            'remaining_amount' => 1,
            'due_date' => '2025-02-05',
            'status' => Invoice::STATUS_OVERDUE,
            'issued_at' => '2025-01-31 08:00:00',
            'revision' => 1,
            'reissued_at' => null,
            'reissue_reason' => null,
            'created_by' => $legacyAdminId,
            'updated_by' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $snapshot = (array) DB::table('invoices')->find($invoiceId);

        $exception = null;

        try {
            $this->seed(StayHubLargeFinancialDemoSeeder::class);
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('Dữ liệu SHOWCASE26 đã tồn tại', $exception->getMessage());
        $this->assertSame($snapshot, (array) DB::table('invoices')->find($invoiceId));
        $this->assertSame(0, DB::table('admins')->where('username', 'showcase26_owner')->count());
        $this->assertSame(0, DB::table('buildings')->where('slug', 'like', 'showcase26-%')->count());
    }

    private function scenariosForPeriod(LargeFinancialDemoDataset $dataset, CarbonImmutable $period): array
    {
        $scenarios = [];

        foreach (range(1, 12) as $buildingNumber) {
            foreach (range(1, 10) as $roomPosition) {
                $roomNumber = $dataset->roomNumber($buildingNumber, $roomPosition);
                $scenarios[] = $dataset->paymentScenario($buildingNumber, $roomNumber, $period);
            }
        }

        return $scenarios;
    }

    private function assertExactKeys(array $expected, $query, string $column): void
    {
        $actual = $query->pluck($column)->all();
        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual, "Tập khóa {$column} không khớp chính xác.");
    }

    private function showcaseSnapshot(): array
    {
        $tables = [
            'admins',
            'regions',
            'buildings',
            'room_types',
            'rooms',
            'tenants',
            'contracts',
            'contract_tenants',
            'contract_deposit_transactions',
            'services',
            'service_prices',
            'room_services',
            'room_service_prices',
            'meter_devices',
            'meter_readings',
            'invoices',
            'invoice_items',
            'payments',
        ];
        $counts = collect($tables)->mapWithKeys(
            fn (string $table): array => [$table => DB::table($table)->count()],
        )->all();
        $owner = (array) DB::table('admins')->where('username', 'showcase26_owner')->first();
        $tenant = (array) DB::table('tenants')->where('username', 'showcase26_b01_p101_t01')->first();
        $invoice = (array) DB::table('invoices')->where('invoice_code', 'SHOWCASE26-HDD-B01-P101-202501')->first();

        return [
            'counts' => $counts,
            'owner' => [$owner['password'], $owner['created_at'], $owner['updated_at']],
            'tenant' => [$tenant['password'], $tenant['created_at'], $tenant['updated_at']],
            'invoice' => $invoice,
            'invoice_checksum' => DB::table('invoices')->where('invoice_code', 'like', 'SHOWCASE26-HDD-%')->sum('total_amount'),
            'payment_checksum' => DB::table('payments')->where('payment_code', 'like', 'SHOWCASE26-TT-%')->sum('amount'),
        ];
    }
}
