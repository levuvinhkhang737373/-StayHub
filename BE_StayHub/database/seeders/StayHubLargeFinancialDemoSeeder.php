<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\LargeFinancialDemoDataset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StayHubLargeFinancialDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = '12345678';

    private CarbonImmutable $timestamp;

    public function __construct(private readonly LargeFinancialDemoDataset $dataset)
    {
        $this->timestamp = CarbonImmutable::parse('2026-07-16 00:00:00');
    }

    public function run(): void
    {
        $this->assertCanonicalServicesAreCompatible();
        $namespaceState = $this->namespaceState();

        if ($namespaceState === 'complete') {
            return;
        }

        if ($namespaceState === 'partial') {
            throw new RuntimeException('Dữ liệu SHOWCASE26 đã tồn tại nhưng không hoàn chỉnh; seeder không thể tiếp tục an toàn.');
        }

        DB::transaction(function (): void {
            $password = Hash::make(self::PASSWORD);
            [$ownerId, $managerIds] = $this->seedAdmins($password);
            $regionId = $this->seedRegion($ownerId);
            $buildingIds = $this->seedBuildings($ownerId, $managerIds, $regionId);
            $roomTypeId = $this->seedRoomType($ownerId);
            $rooms = $this->seedRooms($managerIds, $buildingIds, $roomTypeId);
            $tenantIds = $this->seedTenants($managerIds, $buildingIds, $password);
            $contracts = $this->seedContracts($managerIds, $rooms, $tenantIds);
            $this->seedContractTenants($managerIds, $contracts, $tenantIds);
            $this->seedDepositTransactions($managerIds, $contracts);
            $services = $this->resolveServices($ownerId);
            $this->seedServicePrices($buildingIds, $services, $managerIds);
            $roomServices = $this->seedRoomServices($rooms, $services, $managerIds);
            $meters = $this->seedMeters($rooms, $services);
            $readings = $this->seedMeterReadings($meters, $contracts, $managerIds);
            $invoices = $this->seedInvoicesAndItems(
                $contracts,
                $rooms,
                $readings,
                $services,
                $managerIds,
                $buildingIds,
            );
            $this->seedPayments($invoices, $managerIds);
            $this->assertCompleteNamespaceIsValid($this->namespaceCounts());
        });
    }

    private function seedAdmins(string $password): array
    {
        $rows = [[
            'username' => 'showcase26_owner',
            'full_name' => 'Chủ hệ thống SHOWCASE26',
            'email' => 'showcase26_owner@demo.example.test',
            'phone' => '0972600000',
            'password' => $password,
            'role' => Admin::ROLE_SUPER_ADMIN,
            'avatar_url' => null,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'date_of_birth' => '1980-01-01',
            'address' => 'TP.HCM',
            'image_path_faceid' => null,
            'created_faceid_at' => null,
            'updated_faceid_at' => null,
            ...$this->timestamps(),
        ]];

        foreach ($this->dataset->buildings() as $index => $building) {
            $number = $index + 1;
            $rows[] = [
                'username' => sprintf('showcase26_manager_b%02d', $number),
                'full_name' => $building['manager']['full_name'],
                'email' => sprintf('quanly.b%02d@demo.example.test', $number),
                'phone' => sprintf('09726%05d', $number),
                'password' => $password,
                'role' => Admin::ROLE_BUILDING_MANAGER,
                'avatar_url' => null,
                'status' => Admin::STATUS_ACTIVE,
                'gender' => $building['manager']['gender'],
                'date_of_birth' => sprintf('198%d-%02d-15', $number % 10, (($number - 1) % 12) + 1),
                'address' => $building['address'],
                'image_path_faceid' => null,
                'created_faceid_at' => null,
                'updated_faceid_at' => null,
                ...$this->timestamps(),
            ];
        }

        DB::table('admins')->insertOrIgnore($rows);

        $ownerId = (int) DB::table('admins')
            ->where('username', 'showcase26_owner')
            ->value('id');
        $managerIds = DB::table('admins')
            ->whereIn('username', array_map(
                fn (int $number): string => sprintf('showcase26_manager_b%02d', $number),
                range(1, LargeFinancialDemoDataset::BUILDING_COUNT),
            ))
            ->pluck('id', 'username')
            ->mapWithKeys(fn (int $id, string $username): array => [(int) substr($username, -2) => $id])
            ->all();
        $this->assertCount($managerIds, LargeFinancialDemoDataset::BUILDING_COUNT, 'quản lý tòa nhà');

        if ($ownerId === 0) {
            throw new RuntimeException('Không thể tạo chủ sở hữu SHOWCASE26.');
        }

        return [$ownerId, $managerIds];
    }

    private function seedRegion(int $ownerId): int
    {
        DB::table('regions')->insertOrIgnore([
            'parent_id' => null,
            'code' => 'SHOWCASE26-HCM',
            'name' => 'Khu ký túc xá SHOWCASE26 TP.HCM',
            'path' => '/showcase26-hcm',
            'slug' => 'showcase26-hcm',
            'description' => 'Khu vực riêng cho dữ liệu demo tài chính SHOWCASE26.',
            'is_active' => true,
            'created_by' => $ownerId,
            ...$this->timestamps(),
        ]);

        $regionId = (int) DB::table('regions')
            ->where('code', 'SHOWCASE26-HCM')
            ->value('id');

        if ($regionId === 0) {
            throw new RuntimeException('Không thể tạo khu vực SHOWCASE26.');
        }

        return $regionId;
    }

    private function seedBuildings(int $ownerId, array $managerIds, int $regionId): array
    {
        $rows = [];

        foreach ($this->dataset->buildings() as $index => $building) {
            $number = $index + 1;
            $rows[] = [
                'region_id' => $regionId,
                'manager_admin_id' => $managerIds[$number],
                'name' => $building['name'],
                'slug' => sprintf('showcase26-b%02d', $number),
                'address' => $building['address'],
                'total_floors' => 5,
                'gender_policy' => Building::GENDER_POLICY_MIXED,
                'description' => 'Ký túc xá 20 người mỗi phòng thuộc bộ dữ liệu SHOWCASE26.',
                'status' => Building::STATUS_ACTIVE,
                'created_by' => $ownerId,
                ...$this->timestamps(),
            ];
        }

        DB::table('buildings')->insertOrIgnore($rows);

        $buildingIds = DB::table('buildings')
            ->whereIn('slug', array_map(
                fn (int $number): string => sprintf('showcase26-b%02d', $number),
                range(1, LargeFinancialDemoDataset::BUILDING_COUNT),
            ))
            ->pluck('id', 'slug')
            ->mapWithKeys(fn (int $id, string $slug): array => [(int) substr($slug, -2) => $id])
            ->all();
        $this->assertCount($buildingIds, LargeFinancialDemoDataset::BUILDING_COUNT, 'tòa nhà');

        return $buildingIds;
    }

    private function seedRoomType(int $ownerId): int
    {
        DB::table('room_types')->insertOrIgnore([
            'name' => 'Ký túc xá 20 người SHOWCASE26',
            'slug' => 'showcase26-ky-tuc-xa-20-nguoi',
            'description' => 'Loại phòng riêng cho bộ dữ liệu demo tài chính SHOWCASE26.',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $ownerId,
            ...$this->timestamps(),
        ]);

        $roomTypeId = (int) DB::table('room_types')
            ->where('slug', 'showcase26-ky-tuc-xa-20-nguoi')
            ->value('id');

        if ($roomTypeId === 0) {
            throw new RuntimeException('Không thể tạo loại phòng SHOWCASE26.');
        }

        return $roomTypeId;
    }

    private function seedRooms(array $managerIds, array $buildingIds, int $roomTypeId): array
    {
        $rows = [];
        $roomSlugs = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $roomSlug = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);
                $roomSlugs[] = $roomSlug;
                $rows[] = [
                    'building_id' => $buildingIds[$buildingNumber],
                    'room_type_id' => $roomTypeId,
                    'room_number' => sprintf('B%02d-P%d', $buildingNumber, $roomNumber),
                    'slug' => $roomSlug,
                    'floor' => intdiv($roomPosition - 1, 2) + 1,
                    'area_m2' => 80 + ($roomPosition % 2) * 5,
                    'base_price' => $this->dataset->roomPrice($buildingNumber, $roomPosition),
                    'max_occupants' => LargeFinancialDemoDataset::TENANTS_PER_ROOM,
                    'current_occupants' => LargeFinancialDemoDataset::TENANTS_PER_ROOM,
                    'status' => Room::STATUS_ACTIVE,
                    'description' => 'Phòng ký túc xá rộng rãi, sức chứa 20 người.',
                    'created_by' => $managerIds[$buildingNumber],
                    ...$this->timestamps(),
                ];
            }
        }

        DB::table('rooms')->insertOrIgnore($rows);

        $rooms = DB::table('rooms')
            ->whereIn('building_id', array_values($buildingIds))
            ->whereIn('slug', $roomSlugs)
            ->get(['id', 'slug', 'base_price'])
            ->mapWithKeys(fn (object $room): array => [$room->slug => [
                'id' => (int) $room->id,
                'base_price' => (float) $room->base_price,
            ]])
            ->all();
        $this->assertCount(
            $rooms,
            LargeFinancialDemoDataset::BUILDING_COUNT * LargeFinancialDemoDataset::ROOMS_PER_BUILDING,
            'phòng',
        );

        return $rooms;
    }

    private function seedTenants(array $managerIds, array $buildingIds, string $password): array
    {
        $rows = [];
        $usernames = [];
        $globalTenantNumber = 0;

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);

                foreach (range(1, LargeFinancialDemoDataset::TENANTS_PER_ROOM) as $tenantPosition) {
                    $globalTenantNumber++;
                    $username = $this->dataset->tenantUsername($buildingNumber, $roomNumber, $tenantPosition);
                    $usernames[] = $username;
                    $rows[] = [
                        'building_id' => $buildingIds[$buildingNumber],
                        'created_by' => $managerIds[$buildingNumber],
                        'full_name' => $this->dataset->tenantName($globalTenantNumber),
                        'gender' => $globalTenantNumber % 2 === 0 ? Tenant::GENDER_FEMALE : Tenant::GENDER_MALE,
                        'date_of_birth' => sprintf('200%d-%02d-%02d', $globalTenantNumber % 6, (($globalTenantNumber - 1) % 12) + 1, (($globalTenantNumber - 1) % 28) + 1),
                        'phone' => $this->dataset->tenantPhone($globalTenantNumber),
                        'email' => $this->dataset->tenantEmail($buildingNumber, $roomNumber, $tenantPosition),
                        'username' => $username,
                        'password' => $password,
                        'permanent_address' => 'TP.HCM',
                        'current_address' => 'TP.HCM',
                        'avatar_url' => null,
                        'status' => Tenant::STATUS_RENTING,
                        'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
                        'identity_date' => '2022-01-01',
                        'identity_place' => 'Cục Cảnh sát quản lý hành chính về trật tự xã hội',
                        'identity_number' => $this->dataset->tenantIdentityNumber($globalTenantNumber),
                        'front_image_url' => null,
                        'back_image_url' => null,
                        ...$this->timestamps(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('tenants')->insertOrIgnore($chunk);
        }

        $tenantIds = DB::table('tenants')
            ->whereIn('username', $usernames)
            ->pluck('id', 'username')
            ->map(fn (int $id): int => $id)
            ->all();
        $this->assertCount(
            $tenantIds,
            LargeFinancialDemoDataset::BUILDING_COUNT
                * LargeFinancialDemoDataset::ROOMS_PER_BUILDING
                * LargeFinancialDemoDataset::TENANTS_PER_ROOM,
            'khách thuê',
        );

        return $tenantIds;
    }

    private function seedContracts(array $managerIds, array $rooms, array $tenantIds): array
    {
        $rows = [];
        $contractCodes = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $roomKey = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);
                $contractCode = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);
                $contractCodes[] = $contractCode;
                $representativeUsername = $this->dataset->tenantUsername($buildingNumber, $roomNumber, 1);
                $depositAmount = $rooms[$roomKey]['base_price'] * 2;
                $rows[] = [
                    'contract_code' => $contractCode,
                    'room_id' => $rooms[$roomKey]['id'],
                    'start_date' => '2025-01-01',
                    'end_date' => '2026-12-31',
                    'actual_end_date' => null,
                    'room_price' => $rooms[$roomKey]['base_price'],
                    'deposit_amount' => $depositAmount,
                    'status' => Contract::STATUS_ACTIVE,
                    'tenant_signed_at' => $this->timestamp,
                    'tenant_signature_url' => 'signatures/placeholder.png',
                    'payment_status' => Contract::PAYMENT_STATUS_SUCCESS,
                    'contract_files' => null,
                    'note' => 'Hợp đồng demo tài chính SHOWCASE26.',
                    'created_by' => $managerIds[$buildingNumber],
                    'parent_contract_id' => null,
                    'renew_from_contract_id' => null,
                    'representative_tenant_id' => $tenantIds[$representativeUsername],
                    ...$this->timestamps(),
                ];
            }
        }

        DB::table('contracts')->insertOrIgnore($rows);

        $contracts = DB::table('contracts')
            ->whereIn('contract_code', $contractCodes)
            ->get(['id', 'contract_code', 'deposit_amount'])
            ->mapWithKeys(fn (object $contract): array => [$contract->contract_code => [
                'id' => (int) $contract->id,
                'deposit_amount' => (float) $contract->deposit_amount,
            ]])
            ->all();
        $this->assertCount(
            $contracts,
            LargeFinancialDemoDataset::BUILDING_COUNT * LargeFinancialDemoDataset::ROOMS_PER_BUILDING,
            'hợp đồng',
        );

        return $contracts;
    }

    private function seedContractTenants(array $managerIds, array $contracts, array $tenantIds): void
    {
        $rows = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $contractCode = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);

                foreach (range(1, LargeFinancialDemoDataset::TENANTS_PER_ROOM) as $tenantPosition) {
                    $username = $this->dataset->tenantUsername($buildingNumber, $roomNumber, $tenantPosition);
                    $rows[] = [
                        'contract_id' => $contracts[$contractCode]['id'],
                        'tenant_id' => $tenantIds[$username],
                        'join_date' => '2025-01-01',
                        'leave_date' => null,
                        'billing_start_date' => '2025-01-01',
                        'billing_end_date' => null,
                        'is_staying' => true,
                        'created_by' => $managerIds[$buildingNumber],
                        ...$this->timestamps(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('contract_tenants')->insertOrIgnore($chunk);
        }

        $pivotCount = DB::table('contract_tenants')
            ->whereIn('contract_id', array_column($contracts, 'id'))
            ->count();

        if ($pivotCount !== count($rows)) {
            throw new RuntimeException('Không thể tạo đủ cư trú hợp đồng SHOWCASE26.');
        }
    }

    private function seedDepositTransactions(array $managerIds, array $contracts): void
    {
        $rows = [];
        $references = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $contractCode = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);
                $reference = sprintf('SHOWCASE26-COC-B%02d-P%d', $buildingNumber, $roomNumber);
                $references[] = $reference;
                $rows[] = [
                    'contract_id' => $contracts[$contractCode]['id'],
                    'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                    'amount' => $contracts[$contractCode]['deposit_amount'],
                    'transaction_date' => '2025-01-01',
                    'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                    'transaction_reference' => $reference,
                    'note' => 'Thu đủ tiền cọc hợp đồng SHOWCASE26.',
                    'created_by' => $managerIds[$buildingNumber],
                    'created_at' => $this->timestamp,
                ];
            }
        }

        DB::table('contract_deposit_transactions')->insertOrIgnore($rows);

        $depositCount = DB::table('contract_deposit_transactions')
            ->whereIn('transaction_reference', $references)
            ->count();

        if ($depositCount !== count($rows)) {
            throw new RuntimeException('Không thể tạo đủ giao dịch cọc SHOWCASE26.');
        }
    }

    private function resolveServices(int $ownerId): array
    {
        $definitions = $this->serviceDefinitions();
        $rows = [];
        $resolvedSlugs = [];

        foreach ($definitions as $logicalKey => $definition) {
            $canonicalService = DB::table('services')
                ->where('slug', $definition['canonical_slug'])
                ->where('charge_method', $definition['charge_method'])
                ->first(['slug']);
            $resolvedSlug = $canonicalService?->slug ?? $definition['fallback_slug'];
            $resolvedSlugs[$logicalKey] = $resolvedSlug;

            if (DB::table('services')->where('slug', $resolvedSlug)->exists()) {
                continue;
            }

            $rows[] = [
                'name' => $definition['name'],
                'slug' => $resolvedSlug,
                'charge_method' => $definition['charge_method'],
                'unit_name' => $definition['unit_name'],
                'is_required' => true,
                'is_active' => Service::ACTIVE,
                'created_by' => $ownerId,
                ...$this->timestamps(),
            ];
        }

        if ($rows !== []) {
            DB::table('services')->insertOrIgnore($rows);
        }

        $services = DB::table('services')
            ->whereIn('slug', array_values($resolvedSlugs))
            ->get(['id', 'slug'])
            ->keyBy('slug');
        $services = collect($resolvedSlugs)
            ->mapWithKeys(fn (string $slug, string $logicalKey): array => [
                $logicalKey => (int) $services[$slug]->id,
            ])
            ->all();
        $this->assertCount($services, count($definitions), 'dịch vụ');

        return $services;
    }

    private function seedServicePrices(array $buildingIds, array $services, array $managerIds): void
    {
        $rows = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach ($this->serviceDefinitions() as $logicalKey => $definition) {
                $rows[] = [
                    'service_id' => $services[$logicalKey],
                    'building_id' => $buildingIds[$buildingNumber],
                    'price' => $this->buildingServicePrice($logicalKey, $buildingNumber),
                    'effective_from' => '2025-01-01',
                    'effective_to' => null,
                    'status' => ServicePrice::STATUS_ACTIVE,
                    'created_by' => $managerIds[$buildingNumber],
                    ...$this->timestamps(),
                ];
            }
        }

        DB::table('service_prices')->insertOrIgnore($rows);

        $priceCount = DB::table('service_prices')
            ->whereIn('building_id', array_values($buildingIds))
            ->whereIn('service_id', array_values($services))
            ->whereDate('effective_from', '2025-01-01')
            ->count();

        if ($priceCount !== count($rows)) {
            throw new RuntimeException('Không thể tạo đủ giá dịch vụ SHOWCASE26.');
        }
    }

    private function seedRoomServices(array $rooms, array $services, array $managerIds): array
    {
        $rows = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $roomSlug = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);

                foreach ($services as $serviceId) {
                    $rows[] = [
                        'room_id' => $rooms[$roomSlug]['id'],
                        'service_id' => $serviceId,
                        'is_active' => true,
                        'ended_at' => null,
                        ...$this->timestamps(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('room_services')->insertOrIgnore($chunk);
        }

        $roomServices = DB::table('room_services')
            ->whereIn('room_id', array_column($rooms, 'id'))
            ->whereIn('service_id', array_values($services))
            ->get(['id', 'room_id', 'service_id'])
            ->mapWithKeys(fn (object $roomService): array => [
                $roomService->room_id.'-'.$roomService->service_id => (int) $roomService->id,
            ])
            ->all();
        $this->assertCount($roomServices, count($rows), 'dịch vụ phòng');

        $this->seedRoomServicePrices($rooms, $services, $roomServices, $managerIds);

        return $roomServices;
    }

    private function seedRoomServicePrices(
        array $rooms,
        array $services,
        array $roomServices,
        array $managerIds,
    ): void {
        $defaultPrices = [
            'internet' => 120_000,
            'trash' => 30_000,
            'cleaning' => 60_000,
        ];
        $rows = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $roomSlug = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);
                $roomId = $rooms[$roomSlug]['id'];

                foreach ($defaultPrices as $logicalKey => $price) {
                    $serviceId = $services[$logicalKey];
                    $rows[] = [
                        'room_service_id' => $roomServices[$roomId.'-'.$serviceId],
                        'contract_id' => null,
                        'price' => $price,
                        'effective_from' => '2025-01-01',
                        'effective_to' => null,
                        'created_by' => $managerIds[$buildingNumber],
                        ...$this->timestamps(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('room_service_prices')->insertOrIgnore($chunk);
        }

        $priceCount = DB::table('room_service_prices')
            ->whereIn('room_service_id', array_values($roomServices))
            ->whereNull('contract_id')
            ->whereDate('effective_from', '2025-01-01')
            ->count();

        if ($priceCount !== count($rows)) {
            throw new RuntimeException('Không thể tạo đủ giá dịch vụ phòng SHOWCASE26.');
        }
    }

    private function seedMeters(array $rooms, array $services): array
    {
        $rows = [];
        $meterCodes = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $roomSlug = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);
                $initialReadings = [
                    'electric' => 1000 + ($buildingNumber * 100) + ($roomPosition * 10),
                    'water' => 200 + ($buildingNumber * 20) + ($roomPosition * 5),
                ];

                foreach ($initialReadings as $utility => $initialReading) {
                    $isElectric = $utility === 'electric';
                    $meterCode = sprintf(
                        'SHOWCASE26-%s-B%02d-P%d',
                        $isElectric ? 'DIEN' : 'NUOC',
                        $buildingNumber,
                        $roomNumber,
                    );
                    $meterCodes[] = $meterCode;
                    $rows[] = [
                        'room_id' => $rooms[$roomSlug]['id'],
                        'service_id' => $services[$utility],
                        'meter_code' => $meterCode,
                        'meter_type' => $isElectric
                            ? MeterDevice::METER_TYPE_ELECTRIC
                            : MeterDevice::METER_TYPE_WATER,
                        'initial_reading' => $initialReading,
                        'installed_at' => '2025-01-01',
                        'replaced_by_meter_id' => null,
                        'status' => MeterDevice::STATUS_ACTIVE,
                        'image_path' => null,
                        'note' => 'Đồng hồ thuộc bộ dữ liệu SHOWCASE26.',
                        ...$this->timestamps(),
                    ];
                }
            }
        }

        DB::table('meter_devices')->insertOrIgnore($rows);

        $meters = DB::table('meter_devices')
            ->whereIn('meter_code', $meterCodes)
            ->get(['id', 'meter_code', 'initial_reading'])
            ->mapWithKeys(fn (object $meter): array => [$meter->meter_code => [
                'id' => (int) $meter->id,
                'initial_reading' => (float) $meter->initial_reading,
            ]])
            ->all();
        $this->assertCount($meters, count($rows), 'đồng hồ');

        return $meters;
    }

    private function seedMeterReadings(array $meters, array $contracts, array $managerIds): array
    {
        $rows = [];
        $readingKeys = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $contractCode = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);

                foreach (['DIEN', 'NUOC'] as $utility) {
                    $meterCode = sprintf('SHOWCASE26-%s-B%02d-P%d', $utility, $buildingNumber, $roomNumber);
                    $previousReading = $meters[$meterCode]['initial_reading'];

                    foreach ($this->dataset->periods() as $period) {
                        $consumption = $utility === 'DIEN'
                            ? $this->dataset->electricConsumption($buildingNumber, $roomNumber, $period)
                            : $this->dataset->waterConsumption($buildingNumber, $roomNumber, $period);
                        $currentReading = $previousReading + $consumption;
                        $readingKey = sprintf('%s-%s', $meterCode, $period->format('Ym'));
                        $readingKeys[] = $readingKey;
                        $rows[] = [
                            'meter_device_id' => $meters[$meterCode]['id'],
                            'contract_id' => $contracts[$contractCode]['id'],
                            'billing_month' => $period->month,
                            'billing_year' => $period->year,
                            'previous_reading' => $previousReading,
                            'current_reading' => $currentReading,
                            'consumption' => $consumption,
                            'reading_date' => $period->endOfMonth()->toDateString(),
                            'status' => MeterReading::STATUS_INVOICED,
                            'image_path' => null,
                            'note' => 'Chỉ số demo kỳ '.$period->format('m/Y').'.',
                            'created_by' => $managerIds[$buildingNumber],
                            ...$this->timestamps(),
                        ];
                        $previousReading = $currentReading;
                    }
                }
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('meter_readings')->insertOrIgnore($chunk);
        }

        $readings = DB::table('meter_readings')
            ->join('meter_devices', 'meter_devices.id', '=', 'meter_readings.meter_device_id')
            ->whereIn('meter_devices.meter_code', array_keys($meters))
            ->get([
                'meter_readings.id',
                'meter_readings.billing_month',
                'meter_readings.billing_year',
                'meter_devices.meter_code',
            ])
            ->mapWithKeys(fn (object $reading): array => [
                sprintf('%s-%04d%02d', $reading->meter_code, $reading->billing_year, $reading->billing_month) => (int) $reading->id,
            ])
            ->all();
        $this->assertCount($readings, count($readingKeys), 'chỉ số đồng hồ');

        return $readings;
    }

    private function seedInvoicesAndItems(
        array $contracts,
        array $rooms,
        array $readings,
        array $services,
        array $managerIds,
        array $buildingIds,
    ): array {
        $servicePrices = DB::table('service_prices')
            ->whereIn('building_id', array_values($buildingIds))
            ->whereIn('service_id', [$services['electric'], $services['water']])
            ->whereDate('effective_from', '2025-01-01')
            ->get(['building_id', 'service_id', 'price'])
            ->mapWithKeys(fn (object $price): array => [
                $price->building_id.'-'.$price->service_id => (int) $price->price,
            ])
            ->all();
        $invoiceRows = [];
        $invoiceData = [];
        $globalInvoiceNumber = 0;

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $roomSlug = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);
                $contractCode = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);

                foreach ($this->dataset->periods() as $period) {
                    $globalInvoiceNumber++;
                    $invoiceCode = sprintf(
                        'SHOWCASE26-HDD-B%02d-P%d-%s',
                        $buildingNumber,
                        $roomNumber,
                        $period->format('Ym'),
                    );
                    $electricityPrice = $servicePrices[$buildingIds[$buildingNumber].'-'.$services['electric']];
                    $waterPrice = $servicePrices[$buildingIds[$buildingNumber].'-'.$services['water']];
                    $electricityConsumption = $this->dataset->electricConsumption(
                        $buildingNumber,
                        $roomNumber,
                        $period,
                    );
                    $waterConsumption = $this->dataset->waterConsumption(
                        $buildingNumber,
                        $roomNumber,
                        $period,
                    );
                    $totalAmount = (int) $rooms[$roomSlug]['base_price']
                        + ($electricityConsumption * $electricityPrice)
                        + ($waterConsumption * $waterPrice)
                        + 120_000
                        + (LargeFinancialDemoDataset::TENANTS_PER_ROOM * 30_000)
                        + 60_000;

                    if ($globalInvoiceNumber % 10 === 0) {
                        $totalAmount += 150_000;
                    }

                    if ($globalInvoiceNumber % 15 === 0) {
                        $totalAmount -= 100_000;
                    }

                    $scenario = $this->dataset->paymentScenario($buildingNumber, $roomNumber, $period);
                    [$status, $paidAmount, $remainingAmount] = $this->invoiceStatusAndAmounts(
                        $scenario,
                        $totalAmount,
                    );
                    $invoiceRows[] = [
                        'invoice_code' => $invoiceCode,
                        'contract_id' => $contracts[$contractCode]['id'],
                        'room_id' => $rooms[$roomSlug]['id'],
                        'billing_month' => $period->month,
                        'billing_year' => $period->year,
                        'period_start' => $period->startOfMonth()->toDateString(),
                        'period_end' => $period->endOfMonth()->toDateString(),
                        'previous_debt_amount' => $this->money(0),
                        'total_amount' => $this->money($totalAmount),
                        'paid_amount' => $this->money($paidAmount),
                        'remaining_amount' => $this->money($remainingAmount),
                        'due_date' => $period->addMonth()->day(5)->toDateString(),
                        'status' => $status,
                        'issued_at' => $period->endOfMonth()->setTime(8, 0),
                        'revision' => 1,
                        'reissued_at' => null,
                        'reissue_reason' => null,
                        'created_by' => $managerIds[$buildingNumber],
                        'updated_by' => null,
                        ...$this->timestamps(),
                    ];
                    $invoiceData[$invoiceCode] = [
                        'scenario' => $scenario,
                        'period' => $period,
                        'building_number' => $buildingNumber,
                        'room_number' => $roomNumber,
                        'room_price' => (int) $rooms[$roomSlug]['base_price'],
                        'electricity_price' => $electricityPrice,
                        'water_price' => $waterPrice,
                        'electricity_consumption' => $electricityConsumption,
                        'water_consumption' => $waterConsumption,
                        'global_invoice_number' => $globalInvoiceNumber,
                        'total_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                    ];
                }
            }
        }

        foreach (array_chunk($invoiceRows, 250) as $chunk) {
            DB::table('invoices')->insertOrIgnore($chunk);
        }

        $invoiceIds = DB::table('invoices')
            ->whereIn('invoice_code', array_keys($invoiceData))
            ->pluck('id', 'invoice_code')
            ->map(fn (int $id): int => $id)
            ->all();
        $this->assertCount($invoiceIds, count($invoiceRows), 'hóa đơn');
        $itemRows = [];

        foreach ($invoiceData as $invoiceCode => $data) {
            $periodLabel = $data['period']->format('m/Y');
            $periodKey = $data['period']->format('Ym');
            $electricityReadingCode = sprintf(
                'SHOWCASE26-DIEN-B%02d-P%d-%s',
                $data['building_number'],
                $data['room_number'],
                $periodKey,
            );
            $waterReadingCode = sprintf(
                'SHOWCASE26-NUOC-B%02d-P%d-%s',
                $data['building_number'],
                $data['room_number'],
                $periodKey,
            );
            $items = [
                [null, null, InvoiceItem::ITEM_TYPE_ROOM, "Tiền phòng tháng {$periodLabel}", 1, $data['room_price']],
                [$services['electric'], $readings[$electricityReadingCode], InvoiceItem::ITEM_TYPE_ELECTRIC, "Tiền điện tháng {$periodLabel}", $data['electricity_consumption'], $data['electricity_price']],
                [$services['water'], $readings[$waterReadingCode], InvoiceItem::ITEM_TYPE_WATER, "Tiền nước tháng {$periodLabel}", $data['water_consumption'], $data['water_price']],
                [$services['internet'], null, InvoiceItem::ITEM_TYPE_INTERNET, "Internet tháng {$periodLabel}", 1, 120_000],
                [$services['trash'], null, InvoiceItem::ITEM_TYPE_TRASH, "Phí rác tháng {$periodLabel}", LargeFinancialDemoDataset::TENANTS_PER_ROOM, 30_000],
                [$services['cleaning'], null, InvoiceItem::ITEM_TYPE_SURCHARGE, "Vệ sinh tháng {$periodLabel}", 1, 60_000],
            ];

            if ($data['global_invoice_number'] % 10 === 0) {
                $items[] = [null, null, InvoiceItem::ITEM_TYPE_PARKING, "Gửi xe tháng {$periodLabel}", 1, 150_000];
            }

            if ($data['global_invoice_number'] % 15 === 0) {
                $items[] = [null, null, InvoiceItem::ITEM_TYPE_DISCOUNT, "Giảm trừ tháng {$periodLabel}", 1, -100_000];
            }

            foreach ($items as [$serviceId, $readingId, $itemType, $description, $quantity, $unitPrice]) {
                $itemRows[] = [
                    'invoice_id' => $invoiceIds[$invoiceCode],
                    'service_id' => $serviceId,
                    'meter_reading_id' => $readingId,
                    'item_type' => $itemType,
                    'description' => $description,
                    'quantity' => $this->money($quantity),
                    'unit_price' => $this->money($unitPrice),
                    'amount' => $this->money($quantity * $unitPrice),
                    ...$this->timestamps(),
                ];
            }
        }

        foreach (array_chunk($itemRows, 500) as $chunk) {
            DB::table('invoice_items')->insertOrIgnore($chunk);
        }

        if (DB::table('invoice_items')->whereIn('invoice_id', array_values($invoiceIds))->count() !== count($itemRows)) {
            throw new RuntimeException('Không thể tạo đủ khoản mục hóa đơn SHOWCASE26.');
        }

        foreach ($invoiceData as $invoiceCode => &$data) {
            $data['id'] = $invoiceIds[$invoiceCode];
            $data['invoice_code'] = $invoiceCode;
        }

        unset($data);

        return $invoiceData;
    }

    private function seedPayments(array $invoices, array $managerIds): void
    {
        $rows = [];

        foreach ($invoices as $invoice) {
            $basePaymentCode = str_replace('SHOWCASE26-HDD-', 'SHOWCASE26-TT-', $invoice['invoice_code']);
            $paymentParts = match ($invoice['scenario']) {
                'paid_split' => [
                    ['01', intdiv($invoice['total_amount'], 2), $invoice['period']->day(20), Payment::STATUS_CONFIRMED],
                    ['02', $invoice['total_amount'] - intdiv($invoice['total_amount'], 2), $invoice['period']->endOfMonth(), Payment::STATUS_CONFIRMED],
                ],
                'paid_late' => [
                    ['01', $invoice['total_amount'], $invoice['period']->addMonth()->day(3), Payment::STATUS_CONFIRMED],
                ],
                'paid' => [
                    ['01', $invoice['total_amount'], $invoice['period']->endOfMonth(), Payment::STATUS_CONFIRMED],
                ],
                'partial' => [
                    ['01', $invoice['paid_amount'], $invoice['period']->endOfMonth(), Payment::STATUS_CONFIRMED],
                    ['PENDING', $invoice['total_amount'] - $invoice['paid_amount'], $invoice['period']->addMonth()->day(4), Payment::STATUS_PENDING_CONFIRMATION],
                ],
                'unpaid' => [
                    ['PENDING', $invoice['total_amount'], $invoice['period']->addMonth()->day(4), Payment::STATUS_PENDING_CONFIRMATION],
                ],
                'cancelled' => [
                    ['CANCELLED', $invoice['total_amount'], $invoice['period']->endOfMonth(), Payment::STATUS_CANCELLED],
                ],
            };

            foreach ($paymentParts as [$suffix, $amount, $paymentDate, $status]) {
                $paymentCode = $basePaymentCode.'-'.$suffix;
                $paymentMethod = ($invoice['building_number'] + $invoice['room_number'] + $paymentDate->month)
                    % 2 === 0
                    ? Payment::PAYMENT_METHOD_BANK_TRANSFER
                    : Payment::PAYMENT_METHOD_CASH;
                $rows[] = [
                    'payment_code' => $paymentCode,
                    'invoice_id' => $invoice['id'],
                    'allocated_from_payment_id' => null,
                    'invoice_debt_rollover_id' => null,
                    'is_internal_allocation' => false,
                    'amount' => $this->money($amount),
                    'payment_date' => $paymentDate->setTime(10, 0),
                    'payment_method' => $paymentMethod,
                    'transaction_reference' => $paymentMethod === Payment::PAYMENT_METHOD_BANK_TRANSFER
                        ? 'FAKE-'.$paymentCode
                        : null,
                    'status' => $status,
                    'proof_image' => null,
                    'note' => match ($status) {
                        Payment::STATUS_CONFIRMED => 'Thanh toán demo đã xác nhận.',
                        Payment::STATUS_PENDING_CONFIRMATION => 'Thanh toán demo chờ xác nhận.',
                        default => 'Thanh toán demo đã hủy.',
                    },
                    'collected_by' => $managerIds[$invoice['building_number']],
                    ...$this->timestamps(),
                ];
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('payments')->insertOrIgnore($chunk);
        }

        $paymentCount = DB::table('payments')->whereIn('payment_code', array_column($rows, 'payment_code'))->count();

        if ($paymentCount !== count($rows)) {
            throw new RuntimeException('Không thể tạo đủ thanh toán SHOWCASE26.');
        }
    }

    private function invoiceStatusAndAmounts(string $scenario, int $totalAmount): array
    {
        return match ($scenario) {
            'cancelled' => [Invoice::STATUS_CANCELLED, 0, 0],
            'partial' => [Invoice::STATUS_OVERDUE, intdiv($totalAmount, 2), $totalAmount - intdiv($totalAmount, 2)],
            'unpaid' => [Invoice::STATUS_OVERDUE, 0, $totalAmount],
            default => [Invoice::STATUS_PAID, $totalAmount, 0],
        };
    }

    private function money(int|float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function serviceDefinitions(): array
    {
        return [
            'electric' => [
                'name' => 'Điện sinh hoạt',
                'canonical_slug' => 'electric',
                'fallback_slug' => 'electric',
                'charge_method' => Service::CHARGE_METHOD_BY_METER,
                'unit_name' => 'kWh',
            ],
            'water' => [
                'name' => 'Nước sinh hoạt',
                'canonical_slug' => 'water',
                'fallback_slug' => 'water',
                'charge_method' => Service::CHARGE_METHOD_BY_METER,
                'unit_name' => 'm³',
            ],
            'internet' => [
                'name' => 'Internet SHOWCASE26',
                'canonical_slug' => 'internet',
                'fallback_slug' => 'showcase26-internet',
                'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
                'unit_name' => 'phòng',
            ],
            'trash' => [
                'name' => 'Phí rác SHOWCASE26',
                'canonical_slug' => 'trash',
                'fallback_slug' => 'showcase26-trash',
                'charge_method' => Service::CHARGE_METHOD_BY_PERSON,
                'unit_name' => 'người',
            ],
            'cleaning' => [
                'name' => 'Vệ sinh SHOWCASE26',
                'canonical_slug' => 'cleaning',
                'fallback_slug' => 'showcase26-cleaning',
                'charge_method' => Service::CHARGE_METHOD_BY_ROOM,
                'unit_name' => 'phòng',
            ],
        ];
    }

    private function buildingServicePrice(string $serviceSlug, int $buildingNumber): int
    {
        return match ($serviceSlug) {
            'electric' => 3800 + (int) round((($buildingNumber - 1) * 400) / 11),
            'water' => 16_000 + (int) round((($buildingNumber - 1) * 3000) / 11),
            'internet' => 120_000,
            'trash' => 30_000,
            'cleaning' => 60_000,
        };
    }

    private function namespaceState(): string
    {
        $counts = $this->namespaceCounts();

        if (array_sum($counts) === 0) {
            return 'none';
        }

        try {
            $this->assertCompleteNamespaceIsValid($counts);

            return 'complete';
        } catch (RuntimeException) {
            return 'partial';
        }
    }

    private function namespaceCounts(): array
    {
        $keys = $this->naturalKeys();
        $buildingIds = DB::table('buildings')->whereIn('slug', $keys['buildings'])->pluck('id');
        $roomIds = DB::table('rooms')->whereIn('building_id', $buildingIds)->pluck('id');
        $contractIds = DB::table('contracts')->whereIn('room_id', $roomIds)->pluck('id');
        $invoiceIds = DB::table('invoices')->whereIn('contract_id', $contractIds)->pluck('id');

        return [
            'admins' => count($this->literalNamespaceKeys('admins', 'username', 'showcase26_')),
            'regions' => DB::table('regions')->where('code', 'SHOWCASE26-HCM')->count(),
            'room_types' => DB::table('room_types')->where('slug', 'showcase26-ky-tuc-xa-20-nguoi')->count(),
            'buildings' => count($this->literalNamespaceKeys('buildings', 'slug', 'showcase26-')),
            'rooms' => count($this->literalNamespaceKeys('rooms', 'slug', 'showcase26-')),
            'tenants' => count($this->literalNamespaceKeys('tenants', 'username', 'showcase26_')),
            'contracts' => count($this->literalNamespaceKeys('contracts', 'contract_code', 'SHOWCASE26-')),
            'contract_tenants' => DB::table('contract_tenants')
                ->whereIn('contract_id', $contractIds)
                ->count(),
            'deposits' => DB::table('contract_deposit_transactions')
                ->whereIn('transaction_reference', $keys['deposits'])
                ->count(),
            'services' => count($this->literalNamespaceKeys('services', 'slug', 'showcase26-')),
            'service_prices' => DB::table('service_prices')
                ->whereIn('building_id', $buildingIds)
                ->count(),
            'room_services' => DB::table('room_services')
                ->whereIn('room_id', $roomIds)
                ->count(),
            'room_service_prices' => DB::table('room_service_prices')
                ->join('room_services', 'room_services.id', '=', 'room_service_prices.room_service_id')
                ->whereIn('room_services.room_id', $roomIds)
                ->count(),
            'meters' => count($this->literalNamespaceKeys('meter_devices', 'meter_code', 'SHOWCASE26-')),
            'readings' => DB::table('meter_readings')
                ->join('meter_devices', 'meter_devices.id', '=', 'meter_readings.meter_device_id')
                ->whereIn('meter_devices.meter_code', $keys['meters'])
                ->count(),
            'invoices' => count($this->literalNamespaceKeys('invoices', 'invoice_code', 'SHOWCASE26-')),
            'invoice_items' => DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->count(),
            'payments' => count($this->literalNamespaceKeys('payments', 'payment_code', 'SHOWCASE26-')),
        ];
    }

    private function assertCompleteNamespaceIsValid(array $counts): void
    {
        $expectedCounts = [
            'admins' => 13,
            'regions' => 1,
            'room_types' => 1,
            'buildings' => 12,
            'rooms' => 120,
            'tenants' => 2400,
            'contracts' => 120,
            'contract_tenants' => 2400,
            'deposits' => 120,
            'services' => count($this->expectedNamespacedServiceSlugs()),
            'service_prices' => 60,
            'room_services' => 600,
            'room_service_prices' => 360,
            'meters' => 240,
            'readings' => 4320,
            'invoices' => 2160,
            'invoice_items' => 13320,
            'payments' => $this->expectedPaymentCount(),
        ];

        if ($counts !== $expectedCounts) {
            throw new RuntimeException('Số lượng bản ghi SHOWCASE26 không hoàn chỉnh.');
        }

        $this->assertExactNaturalKeys();

        $contracts = DB::table('contracts')
            ->whereIn('contract_code', $this->naturalKeys()['contracts'])
            ->get(['id', 'room_id']);
        $tenantCounts = DB::table('contract_tenants')
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->selectRaw('contract_id, COUNT(*) as aggregate')
            ->groupBy('contract_id')
            ->pluck('aggregate', 'contract_id');

        if ($contracts->contains(fn (object $contract): bool => (int) ($tenantCounts[$contract->id] ?? 0) !== 20)) {
            throw new RuntimeException('Phân bổ người thuê SHOWCASE26 không hợp lệ.');
        }

        $invalidReadings = DB::table('meter_readings')
            ->join('meter_devices', 'meter_devices.id', '=', 'meter_readings.meter_device_id')
            ->join('contracts', 'contracts.id', '=', 'meter_readings.contract_id')
            ->whereIn('meter_devices.meter_code', $this->naturalKeys()['meters'])
            ->get([
                'meter_devices.room_id as meter_room_id',
                'contracts.room_id as contract_room_id',
                'meter_readings.previous_reading',
                'meter_readings.current_reading',
                'meter_readings.consumption',
            ])
            ->contains(fn (object $reading): bool => (int) $reading->meter_room_id !== (int) $reading->contract_room_id
                || (float) $reading->current_reading - (float) $reading->previous_reading !== (float) $reading->consumption);

        if ($invalidReadings) {
            throw new RuntimeException('Chỉ số đồng hồ SHOWCASE26 không hợp lệ.');
        }

        $invoices = DB::table('invoices')
            ->whereIn('invoice_code', $this->naturalKeys()['invoices'])
            ->get(['id', 'status', 'total_amount', 'paid_amount', 'remaining_amount']);
        $itemAmounts = DB::table('invoice_items')
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->selectRaw('invoice_id, SUM(amount) as aggregate')
            ->groupBy('invoice_id')
            ->pluck('aggregate', 'invoice_id');
        $confirmedPayments = DB::table('payments')
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->whereIn('payment_code', $this->naturalKeys()['payments'])
            ->where('status', Payment::STATUS_CONFIRMED)
            ->selectRaw('invoice_id, SUM(amount) as aggregate')
            ->groupBy('invoice_id')
            ->pluck('aggregate', 'invoice_id');

        foreach ($invoices as $invoice) {
            $total = (float) $invoice->total_amount;
            $paid = (float) $invoice->paid_amount;
            $remaining = (float) $invoice->remaining_amount;

            if ((float) ($itemAmounts[$invoice->id] ?? -1) !== $total
                || (float) ($confirmedPayments[$invoice->id] ?? 0) !== $paid
                || ((int) $invoice->status === Invoice::STATUS_CANCELLED
                    ? $paid !== 0.0 || $remaining !== 0.0
                    : $remaining !== $total - $paid)) {
                throw new RuntimeException('Tổng hợp tài chính SHOWCASE26 không hợp lệ.');
            }
        }

        $actualStatusCounts = $invoices->countBy(fn (object $invoice): int => (int) $invoice->status)->all();
        $expectedStatusCounts = $this->expectedInvoiceStatusCounts();
        ksort($actualStatusCounts);
        ksort($expectedStatusCounts);

        if ($actualStatusCounts !== $expectedStatusCounts) {
            throw new RuntimeException('Phân bổ trạng thái hóa đơn SHOWCASE26 không hợp lệ.');
        }
    }

    private function expectedPaymentCount(): int
    {
        $count = 0;

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);

                foreach ($this->dataset->periods() as $period) {
                    $scenario = $this->dataset->paymentScenario($buildingNumber, $roomNumber, $period);
                    $count += in_array($scenario, ['paid_split', 'partial'], true) ? 2 : 1;
                }
            }
        }

        return $count;
    }

    private function naturalKeys(): array
    {
        $keys = [
            'admins' => ['showcase26_owner'],
            'buildings' => [],
            'rooms' => [],
            'tenants' => [],
            'contracts' => [],
            'deposits' => [],
            'meters' => [],
            'invoices' => [],
            'payments' => [],
        ];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            $keys['admins'][] = sprintf('showcase26_manager_b%02d', $buildingNumber);
            $keys['buildings'][] = sprintf('showcase26-b%02d', $buildingNumber);

            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $keys['rooms'][] = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);
                $keys['contracts'][] = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);
                $keys['deposits'][] = sprintf('SHOWCASE26-COC-B%02d-P%d', $buildingNumber, $roomNumber);
                $keys['meters'][] = sprintf('SHOWCASE26-DIEN-B%02d-P%d', $buildingNumber, $roomNumber);
                $keys['meters'][] = sprintf('SHOWCASE26-NUOC-B%02d-P%d', $buildingNumber, $roomNumber);

                foreach (range(1, LargeFinancialDemoDataset::TENANTS_PER_ROOM) as $tenantPosition) {
                    $keys['tenants'][] = $this->dataset->tenantUsername(
                        $buildingNumber,
                        $roomNumber,
                        $tenantPosition,
                    );
                }

                foreach ($this->dataset->periods() as $period) {
                    $invoiceCode = sprintf(
                        'SHOWCASE26-HDD-B%02d-P%d-%s',
                        $buildingNumber,
                        $roomNumber,
                        $period->format('Ym'),
                    );
                    $keys['invoices'][] = $invoiceCode;
                    $paymentBase = str_replace('SHOWCASE26-HDD-', 'SHOWCASE26-TT-', $invoiceCode);
                    $scenario = $this->dataset->paymentScenario($buildingNumber, $roomNumber, $period);
                    $suffixes = match ($scenario) {
                        'paid_split' => ['01', '02'],
                        'partial' => ['01', 'PENDING'],
                        'unpaid' => ['PENDING'],
                        'cancelled' => ['CANCELLED'],
                        default => ['01'],
                    };

                    foreach ($suffixes as $suffix) {
                        $keys['payments'][] = $paymentBase.'-'.$suffix;
                    }
                }
            }
        }

        return $keys;
    }

    private function assertExactNaturalKeys(): void
    {
        $keys = $this->naturalKeys();

        $this->assertExactKeys('admins', 'username', 'showcase26_', $keys['admins']);
        $this->assertExactKeys('buildings', 'slug', 'showcase26-', $keys['buildings']);
        $this->assertExactKeys('rooms', 'slug', 'showcase26-', $keys['rooms']);
        $this->assertExactKeys('tenants', 'username', 'showcase26_', $keys['tenants']);
        $this->assertExactKeys('contracts', 'contract_code', 'SHOWCASE26-', $keys['contracts']);
        $this->assertExactKeys('meter_devices', 'meter_code', 'SHOWCASE26-', $keys['meters']);
        $this->assertExactKeys('invoices', 'invoice_code', 'SHOWCASE26-', $keys['invoices']);
        $this->assertExactKeys('payments', 'payment_code', 'SHOWCASE26-', $keys['payments']);
        $this->assertExactKeys(
            'services',
            'slug',
            'showcase26-',
            $this->expectedNamespacedServiceSlugs(),
        );
    }

    private function assertExactKeys(string $table, string $column, string $prefix, array $expected): void
    {
        $actual = $this->literalNamespaceKeys($table, $column, $prefix);
        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            throw new RuntimeException("Tập khóa {$column} SHOWCASE26 không hợp lệ.");
        }
    }

    private function literalNamespaceKeys(string $table, string $column, string $prefix): array
    {
        $queryPrefix = rtrim($prefix, '_-');

        return DB::table($table)
            ->where($column, 'like', $queryPrefix.'%')
            ->pluck($column)
            ->filter(fn (string $value): bool => str_starts_with($value, $prefix))
            ->values()
            ->all();
    }

    private function expectedNamespacedServiceSlugs(): array
    {
        return collect($this->serviceDefinitions())
            ->filter(fn (array $definition): bool => ! DB::table('services')
                ->where('slug', $definition['canonical_slug'])
                ->where('charge_method', $definition['charge_method'])
                ->exists())
            ->pluck('fallback_slug')
            ->filter(fn (string $slug): bool => str_starts_with($slug, 'showcase26-'))
            ->values()
            ->all();
    }

    private function expectedInvoiceStatusCounts(): array
    {
        $counts = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);

                foreach ($this->dataset->periods() as $period) {
                    $scenario = $this->dataset->paymentScenario($buildingNumber, $roomNumber, $period);
                    $status = match ($scenario) {
                        'cancelled' => Invoice::STATUS_CANCELLED,
                        'partial', 'unpaid' => Invoice::STATUS_OVERDUE,
                        default => Invoice::STATUS_PAID,
                    };
                    $counts[$status] = ($counts[$status] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    private function assertCanonicalServicesAreCompatible(): void
    {
        $incompatibleSlug = DB::table('services')
            ->whereIn('slug', ['electric', 'water'])
            ->where('charge_method', '!=', Service::CHARGE_METHOD_BY_METER)
            ->value('slug');

        if ($incompatibleSlug !== null) {
            throw new RuntimeException(
                "Dịch vụ {$incompatibleSlug} phải tính theo chỉ số để tạo dữ liệu SHOWCASE26.",
            );
        }
    }

    private function assertCount(array $records, int $expected, string $entity): void
    {
        if (count($records) !== $expected) {
            throw new RuntimeException("Không thể tạo đủ {$entity} SHOWCASE26.");
        }
    }

    private function timestamps(): array
    {
        return [
            'created_at' => $this->timestamp,
            'updated_at' => $this->timestamp,
        ];
    }
}
