<?php

namespace Database\Seeders;

use Database\Seeders\Support\LargeFinancialDemoDataset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StayHubLargeFinancialDemoRollbackSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(private readonly LargeFinancialDemoDataset $dataset) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $keys = $this->naturalKeys();
            $buildingIds = DB::table('buildings')->whereIn('slug', $keys['buildings'])->pluck('id');
            $roomIds = DB::table('rooms')->whereIn('slug', $keys['rooms'])->pluck('id');
            $tenantIds = DB::table('tenants')->whereIn('username', $keys['tenants'])->pluck('id');
            $contractIds = DB::table('contracts')->whereIn('contract_code', $keys['contracts'])->pluck('id');
            $meterIds = DB::table('meter_devices')->whereIn('meter_code', $keys['meters'])->pluck('id');
            $invoiceIds = DB::table('invoices')->whereIn('invoice_code', $keys['invoices'])->pluck('id');
            $paymentIds = DB::table('payments')->whereIn('payment_code', $keys['payments'])->pluck('id');
            $readingIds = DB::table('meter_readings')->whereIn('meter_device_id', $meterIds)->pluck('id');
            $roomServiceIds = DB::table('room_services')->whereIn('room_id', $roomIds)->pluck('id');
            $legacyGeneratedRegionId = $this->legacyGeneratedRegionId($buildingIds);

            $this->assertSeededGraphIsComplete(
                $keys,
                $buildingIds,
                $roomIds,
                $tenantIds,
                $contractIds,
                $meterIds,
                $readingIds,
                $invoiceIds,
                $paymentIds,
                $roomServiceIds,
            );
            $this->assertNoExternalDependencies(
                $buildingIds,
                $roomIds,
                $tenantIds,
                $contractIds,
                $meterIds,
                $readingIds,
                $invoiceIds,
                $paymentIds,
            );

            DB::table('payments')->whereIn('payment_code', $keys['payments'])->delete();
            DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
            DB::table('invoices')->whereIn('invoice_code', $keys['invoices'])->delete();
            DB::table('meter_readings')->whereIn('meter_device_id', $meterIds)->delete();
            DB::table('meter_devices')->whereIn('meter_code', $keys['meters'])->delete();
            DB::table('room_service_prices')->whereIn('room_service_id', $roomServiceIds)->delete();
            DB::table('room_services')->whereIn('id', $roomServiceIds)->delete();
            DB::table('service_prices')->whereIn('building_id', $buildingIds)->delete();
            DB::table('contract_deposit_transactions')
                ->whereIn('transaction_reference', $keys['deposits'])
                ->delete();
            DB::table('contract_tenants')->whereIn('contract_id', $contractIds)->delete();
            DB::table('contracts')->whereIn('contract_code', $keys['contracts'])->delete();
            DB::table('tenants')->whereIn('username', $keys['tenants'])->delete();
            DB::table('rooms')->whereIn('slug', $keys['rooms'])->delete();
            DB::table('buildings')->whereIn('slug', $keys['buildings'])->delete();
            DB::table('room_types')->where('slug', 'showcase26-ky-tuc-xa-20-nguoi')->delete();

            if ($legacyGeneratedRegionId !== null) {
                DB::table('regions')->where('id', $legacyGeneratedRegionId)->delete();
            }
        });
    }

    private function assertSeededGraphIsComplete(
        array $keys,
        $buildingIds,
        $roomIds,
        $tenantIds,
        $contractIds,
        $meterIds,
        $readingIds,
        $invoiceIds,
        $paymentIds,
        $roomServiceIds,
    ): void {
        if ($buildingIds->isEmpty()
            && $roomIds->isEmpty()
            && $tenantIds->isEmpty()
            && $contractIds->isEmpty()
            && $meterIds->isEmpty()
            && $invoiceIds->isEmpty()
            && $paymentIds->isEmpty()
        ) {
            return;
        }

        $this->assertExactNamespaceKeys('buildings', 'slug', 'showcase26-', $keys['buildings']);
        $this->assertExactNamespaceKeys('rooms', 'slug', 'showcase26-', $keys['rooms']);
        $this->assertExactNamespaceKeys('tenants', 'username', 'showcase26_', $keys['tenants']);
        $this->assertExactNamespaceKeys('contracts', 'contract_code', 'SHOWCASE26-', $keys['contracts']);
        $this->assertExactNamespaceKeys('meter_devices', 'meter_code', 'SHOWCASE26-', $keys['meters']);
        $this->assertExactNamespaceKeys('invoices', 'invoice_code', 'SHOWCASE26-', $keys['invoices']);
        $this->assertExactNamespaceKeys('payments', 'payment_code', 'SHOWCASE26-', $keys['payments']);

        $serviceSlugs = DB::table('room_services')
            ->join('services', 'services.id', '=', 'room_services.service_id')
            ->whereIn('room_services.room_id', $roomIds)
            ->distinct()
            ->pluck('services.slug')
            ->all();
        $logicalServices = [
            'electric' => ['electric'],
            'water' => ['water'],
            'internet' => ['internet', 'showcase26-internet'],
            'trash' => ['trash', 'showcase26-trash'],
            'cleaning' => ['cleaning', 'showcase26-cleaning'],
        ];

        if (count($serviceSlugs) !== 5
            || collect($logicalServices)->contains(
                fn (array $allowedSlugs): bool => count(array_intersect($allowedSlugs, $serviceSlugs)) !== 1,
            )
        ) {
            $this->incompleteGraph();
        }

        $expectedContractTenants = [];

        foreach (range(1, LargeFinancialDemoDataset::BUILDING_COUNT) as $buildingNumber) {
            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $contractCode = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);

                foreach (range(1, LargeFinancialDemoDataset::TENANTS_PER_ROOM) as $tenantPosition) {
                    $expectedContractTenants[] = $contractCode.'|'.$this->dataset->tenantUsername(
                        $buildingNumber,
                        $roomNumber,
                        $tenantPosition,
                    );
                }
            }
        }

        $actualContractTenants = DB::table('contract_tenants')
            ->join('contracts', 'contracts.id', '=', 'contract_tenants.contract_id')
            ->join('tenants', 'tenants.id', '=', 'contract_tenants.tenant_id')
            ->whereIn('contract_tenants.contract_id', $contractIds)
            ->get(['contracts.contract_code', 'tenants.username'])
            ->map(fn (object $row): string => $row->contract_code.'|'.$row->username)
            ->all();
        $this->assertSameSet($actualContractTenants, $expectedContractTenants);

        $actualDeposits = DB::table('contract_deposit_transactions')
            ->whereIn('contract_id', $contractIds)
            ->pluck('transaction_reference')
            ->all();
        $this->assertSameSet($actualDeposits, $keys['deposits']);

        $expectedServicePrices = [];
        $expectedRoomServices = [];
        $expectedRoomServicePrices = [];
        $nonMeterSlugs = array_values(array_intersect(
            $serviceSlugs,
            ['internet', 'showcase26-internet', 'trash', 'showcase26-trash', 'cleaning', 'showcase26-cleaning'],
        ));

        foreach ($keys['buildings'] as $buildingSlug) {
            foreach ($serviceSlugs as $serviceSlug) {
                $expectedServicePrices[] = $buildingSlug.'|'.$serviceSlug.'|2025-01-01';
            }
        }

        foreach ($keys['rooms'] as $roomSlug) {
            foreach ($serviceSlugs as $serviceSlug) {
                $expectedRoomServices[] = $roomSlug.'|'.$serviceSlug;
            }

            foreach ($nonMeterSlugs as $serviceSlug) {
                $expectedRoomServicePrices[] = $roomSlug.'|'.$serviceSlug.'|2025-01-01';
            }
        }

        $actualServicePrices = DB::table('service_prices')
            ->join('buildings', 'buildings.id', '=', 'service_prices.building_id')
            ->join('services', 'services.id', '=', 'service_prices.service_id')
            ->whereIn('service_prices.building_id', $buildingIds)
            ->get(['buildings.slug as building_slug', 'services.slug as service_slug', 'service_prices.effective_from'])
            ->map(fn (object $row): string => $row->building_slug.'|'.$row->service_slug.'|'.$row->effective_from)
            ->all();
        $this->assertSameSet($actualServicePrices, $expectedServicePrices);

        $actualRoomServices = DB::table('room_services')
            ->join('rooms', 'rooms.id', '=', 'room_services.room_id')
            ->join('services', 'services.id', '=', 'room_services.service_id')
            ->whereIn('room_services.room_id', $roomIds)
            ->get(['rooms.slug as room_slug', 'services.slug as service_slug'])
            ->map(fn (object $row): string => $row->room_slug.'|'.$row->service_slug)
            ->all();
        $this->assertSameSet($actualRoomServices, $expectedRoomServices);

        $actualRoomServicePrices = DB::table('room_service_prices')
            ->join('room_services', 'room_services.id', '=', 'room_service_prices.room_service_id')
            ->join('rooms', 'rooms.id', '=', 'room_services.room_id')
            ->join('services', 'services.id', '=', 'room_services.service_id')
            ->whereIn('room_service_prices.room_service_id', $roomServiceIds)
            ->get([
                'rooms.slug as room_slug',
                'services.slug as service_slug',
                'room_service_prices.effective_from',
                'room_service_prices.contract_id',
            ]);

        if ($actualRoomServicePrices->contains(fn (object $row): bool => $row->contract_id !== null)) {
            $this->incompleteGraph();
        }

        $this->assertSameSet(
            $actualRoomServicePrices
                ->map(fn (object $row): string => $row->room_slug.'|'.$row->service_slug.'|'.$row->effective_from)
                ->all(),
            $expectedRoomServicePrices,
        );

        $expectedReadings = [];

        foreach ($keys['meters'] as $meterCode) {
            foreach ($this->dataset->periods() as $period) {
                $expectedReadings[] = $meterCode.'|'.$period->format('Ym');
            }
        }

        $actualReadings = DB::table('meter_readings')
            ->join('meter_devices', 'meter_devices.id', '=', 'meter_readings.meter_device_id')
            ->whereIn('meter_readings.meter_device_id', $meterIds)
            ->get(['meter_devices.meter_code', 'meter_readings.billing_year', 'meter_readings.billing_month'])
            ->map(fn (object $row): string => sprintf(
                '%s|%04d%02d',
                $row->meter_code,
                $row->billing_year,
                $row->billing_month,
            ))
            ->all();
        $this->assertSameSet($actualReadings, $expectedReadings);

        $expectedInvoiceItems = [];

        foreach ($keys['invoices'] as $invoiceIndex => $invoiceCode) {
            $itemTypes = [1, 2, 3, 4, 5, 7];
            $globalInvoiceNumber = $invoiceIndex + 1;

            if ($globalInvoiceNumber % 10 === 0) {
                $itemTypes[] = 6;
            }

            if ($globalInvoiceNumber % 15 === 0) {
                $itemTypes[] = 8;
            }

            foreach ($itemTypes as $itemType) {
                $expectedInvoiceItems[] = $invoiceCode.'|'.$itemType;
            }
        }

        $actualInvoiceItems = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->get(['invoices.invoice_code', 'invoice_items.item_type'])
            ->map(fn (object $row): string => $row->invoice_code.'|'.$row->item_type)
            ->all();
        $this->assertSameSet($actualInvoiceItems, $expectedInvoiceItems);

        $actualPaymentCodes = DB::table('payments')->whereIn('invoice_id', $invoiceIds)->pluck('payment_code')->all();
        $this->assertSameSet($actualPaymentCodes, $keys['payments']);
    }

    private function assertExactNamespaceKeys(string $table, string $column, string $prefix, array $expected): void
    {
        $actual = DB::table($table)
            ->where($column, 'like', rtrim($prefix, '_-').'%')
            ->pluck($column)
            ->filter(fn (string $value): bool => str_starts_with($value, $prefix))
            ->values()
            ->all();

        $this->assertSameSet($actual, $expected);
    }

    private function assertSameSet(array $actual, array $expected): void
    {
        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            $this->incompleteGraph();
        }
    }

    private function incompleteGraph(): never
    {
        throw new RuntimeException(
            'Không thể rollback SHOWCASE26 vì dữ liệu seed không còn nguyên vẹn hoặc có bản ghi phát sinh.',
        );
    }

    private function assertNoExternalDependencies(
        $buildingIds,
        $roomIds,
        $tenantIds,
        $contractIds,
        $meterIds,
        $readingIds,
        $invoiceIds,
        $paymentIds,
    ): void {
        $checks = [
            'hình ảnh tòa nhà' => DB::table('building_images')->whereIn('building_id', $buildingIds),
            'hình ảnh phòng' => DB::table('room_images')->whereIn('room_id', $roomIds),
            'tài sản phòng' => DB::table('room_assets')->whereIn('room_id', $roomIds),
            'phương tiện' => DB::table('vehicles')->whereIn('tenant_id', $tenantIds),
            'phương tiện hợp đồng' => DB::table('contract_vehicles')->whereIn('contract_id', $contractIds),
            'yêu cầu bảo trì' => DB::table('maintenance_requests')
                ->whereIn('tenant_id', $tenantIds)
                ->orWhereIn('room_id', $roomIds),
            'phản hồi bảo trì' => DB::table('maintenance_feedbacks')->whereIn('tenant_id', $tenantIds),
            'thông báo' => DB::table('notifications')
                ->whereIn('building_id', $buildingIds)
                ->orWhereIn('room_id', $roomIds)
                ->orWhereIn('tenant_id', $tenantIds),
            'lượt đọc thông báo' => DB::table('notification_reads')->whereIn('tenant_id', $tenantIds),
            'chi phí' => DB::table('expenses')
                ->whereIn('building_id', $buildingIds)
                ->orWhereIn('room_id', $roomIds),
            'cài đặt tòa nhà' => DB::table('settings')->whereIn('building_id', $buildingIds),
            'hội thoại gắn dữ liệu demo' => DB::table('chat_conversations')
                ->whereIn('building_id', $buildingIds)
                ->orWhereIn('room_id', $roomIds)
                ->orWhereIn('tenant_id', $tenantIds),
            'lịch sử chuyển phòng' => DB::table('room_movements')
                ->whereIn('tenant_id', $tenantIds)
                ->orWhereIn('from_room_id', $roomIds)
                ->orWhereIn('to_room_id', $roomIds)
                ->orWhereIn('contract_id', $contractIds)
                ->orWhereIn('source_contract_id', $contractIds)
                ->orWhereIn('destination_contract_id', $contractIds),
            'hợp đồng gia hạn' => DB::table('contracts')
                ->whereNotIn('id', $contractIds)
                ->where(function ($query) use ($contractIds): void {
                    $query->whereIn('parent_contract_id', $contractIds)
                        ->orWhereIn('renew_from_contract_id', $contractIds);
                }),
            'hợp đồng ngoài demo' => DB::table('contracts')
                ->whereNotIn('id', $contractIds)
                ->where(function ($query) use ($roomIds, $tenantIds): void {
                    $query->whereIn('room_id', $roomIds)
                        ->orWhereIn('representative_tenant_id', $tenantIds);
                }),
            'người thuê thuộc hợp đồng ngoài demo' => DB::table('contract_tenants')
                ->whereNotIn('contract_id', $contractIds)
                ->whereIn('tenant_id', $tenantIds),
            'giá dịch vụ phòng phát sinh' => DB::table('room_service_prices')
                ->whereIn('contract_id', $contractIds)
                ->whereNotIn('room_service_id', DB::table('room_services')->whereIn('room_id', $roomIds)->select('id')),
            'chỉ số đồng hồ phát sinh' => DB::table('meter_readings')
                ->whereIn('contract_id', $contractIds)
                ->whereNotIn('meter_device_id', $meterIds),
            'hóa đơn phát sinh ngoài demo' => DB::table('invoices')
                ->whereNotIn('id', $invoiceIds)
                ->where(function ($query) use ($contractIds, $roomIds): void {
                    $query->whereIn('contract_id', $contractIds)
                        ->orWhereIn('room_id', $roomIds);
                }),
            'hạng mục hóa đơn ngoài demo tham chiếu chỉ số seed' => DB::table('invoice_items')
                ->whereNotIn('invoice_id', $invoiceIds)
                ->whereIn('meter_reading_id', $readingIds),
            'nhắc hóa đơn' => DB::table('invoice_reminder_logs')
                ->where(function ($query) use ($invoiceIds, $contractIds, $roomIds): void {
                    $query->whereIn('invoice_id', $invoiceIds)
                        ->orWhereIn('contract_id', $contractIds)
                        ->orWhereIn('room_id', $roomIds);
                }),
            'chuyển nợ hóa đơn' => DB::table('invoice_debt_rollovers')
                ->whereIn('source_invoice_id', $invoiceIds)
                ->orWhereIn('target_invoice_id', $invoiceIds),
            'thanh toán phân bổ ngoài demo' => DB::table('payments')
                ->whereNotIn('id', $paymentIds)
                ->whereIn('allocated_from_payment_id', $paymentIds),
            'đồng hồ thay thế ngoài demo' => DB::table('meter_devices')
                ->whereNotIn('id', $meterIds)
                ->whereIn('replaced_by_meter_id', $meterIds),
        ];

        foreach ($checks as $label => $query) {
            if ($query->exists()) {
                throw new RuntimeException(
                    "Không thể rollback SHOWCASE26 vì có {$label} phát sinh ngoài bộ dữ liệu seed.",
                );
            }
        }
    }

    private function legacyGeneratedRegionId($buildingIds): ?int
    {
        $ownerId = DB::table('admins')->where('username', 'showcase26_owner')->value('id');

        if ($ownerId === null || $buildingIds->count() !== LargeFinancialDemoDataset::BUILDING_COUNT) {
            return null;
        }

        $region = DB::table('regions')
            ->where('code', 'SHOWCASE26-HCM')
            ->where('path', '/showcase26-hcm')
            ->where('description', 'Khu vực riêng cho dữ liệu demo tài chính SHOWCASE26.')
            ->where('created_by', $ownerId)
            ->where('created_at', '2026-07-16 00:00:00')
            ->first(['id']);

        if ($region === null
            || DB::table('regions')->where('parent_id', $region->id)->exists()
            || DB::table('buildings')->where('region_id', $region->id)->whereNotIn('id', $buildingIds)->exists()
            || DB::table('buildings')->whereIn('id', $buildingIds)->where('region_id', '!=', $region->id)->exists()
        ) {
            return null;
        }

        return (int) $region->id;
    }

    private function naturalKeys(): array
    {
        $keys = [
            'admins' => ['showcase26_owner'],
            'managers' => [],
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
            $managerUsername = sprintf('showcase26_manager_b%02d', $buildingNumber);
            $keys['admins'][] = $managerUsername;
            $keys['managers'][] = $managerUsername;
            $keys['buildings'][] = sprintf('showcase26-b%02d', $buildingNumber);

            foreach (range(1, LargeFinancialDemoDataset::ROOMS_PER_BUILDING) as $roomPosition) {
                $roomNumber = $this->dataset->roomNumber($buildingNumber, $roomPosition);
                $keys['rooms'][] = sprintf('showcase26-b%02d-p%d', $buildingNumber, $roomNumber);
                $keys['contracts'][] = sprintf('SHOWCASE26-HD-B%02d-P%d', $buildingNumber, $roomNumber);
                $keys['deposits'][] = sprintf('SHOWCASE26-COC-B%02d-P%d', $buildingNumber, $roomNumber);
                $keys['meters'][] = sprintf('SHOWCASE26-DIEN-B%02d-P%d', $buildingNumber, $roomNumber);
                $keys['meters'][] = sprintf('SHOWCASE26-NUOC-B%02d-P%d', $buildingNumber, $roomNumber);

                foreach (range(1, LargeFinancialDemoDataset::TENANTS_PER_ROOM) as $tenantPosition) {
                    $keys['tenants'][] = $this->dataset->tenantUsername($buildingNumber, $roomNumber, $tenantPosition);
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
                    $suffixes = match ($this->dataset->paymentScenario($buildingNumber, $roomNumber, $period)) {
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
}
