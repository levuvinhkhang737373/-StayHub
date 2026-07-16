<?php

namespace Database\Seeders;

use Database\Seeders\Support\LargeFinancialDemoDataset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StayHubLargeFinancialDemoRollbackSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(private readonly LargeFinancialDemoDataset $dataset) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $keys = $this->naturalKeys();
            $adminIds = DB::table('admins')->whereIn('username', $keys['admins'])->pluck('id');
            $managerIds = DB::table('admins')->whereIn('username', $keys['managers'])->pluck('id');
            $buildingIds = DB::table('buildings')->whereIn('slug', $keys['buildings'])->pluck('id');
            $roomIds = DB::table('rooms')->whereIn('slug', $keys['rooms'])->pluck('id');
            $tenantIds = DB::table('tenants')->whereIn('username', $keys['tenants'])->pluck('id');
            $contractIds = DB::table('contracts')->whereIn('contract_code', $keys['contracts'])->pluck('id');
            $meterIds = DB::table('meter_devices')->whereIn('meter_code', $keys['meters'])->pluck('id');
            $invoiceIds = DB::table('invoices')->whereIn('invoice_code', $keys['invoices'])->pluck('id');
            $paymentIds = DB::table('payments')->whereIn('payment_code', $keys['payments'])->pluck('id');
            $roomServiceIds = DB::table('room_services')->whereIn('room_id', $roomIds)->pluck('id');

            DB::table('payments')
                ->whereIn('allocated_from_payment_id', $paymentIds)
                ->update(['allocated_from_payment_id' => null]);
            DB::table('payments')->whereIn('payment_code', $keys['payments'])->delete();
            DB::table('invoice_reminder_logs')->whereIn('invoice_id', $invoiceIds)->delete();
            DB::table('invoice_debt_rollovers')
                ->whereIn('source_invoice_id', $invoiceIds)
                ->orWhereIn('target_invoice_id', $invoiceIds)
                ->delete();
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
            DB::table('contract_vehicles')->whereIn('contract_id', $contractIds)->delete();
            DB::table('contract_tenants')->whereIn('contract_id', $contractIds)->delete();
            DB::table('room_movements')
                ->whereIn('contract_id', $contractIds)
                ->orWhereIn('source_contract_id', $contractIds)
                ->orWhereIn('destination_contract_id', $contractIds)
                ->delete();
            DB::table('contracts')->whereIn('contract_code', $keys['contracts'])->delete();

            $conversationIds = DB::table('chat_conversations')
                ->whereIn('manager_admin_id', $managerIds)
                ->orWhereIn('super_admin_id', $adminIds)
                ->orWhereIn('building_id', $buildingIds)
                ->orWhereIn('room_id', $roomIds)
                ->orWhereIn('tenant_id', $tenantIds)
                ->pluck('id');
            DB::table('chat_conversations')->whereIn('id', $conversationIds)->update(['last_message_id' => null]);
            DB::table('chat_messages')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('chat_conversations')->whereIn('id', $conversationIds)->delete();

            DB::table('vehicles')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('tenants')->whereIn('username', $keys['tenants'])->delete();
            DB::table('rooms')->whereIn('slug', $keys['rooms'])->delete();
            DB::table('buildings')->whereIn('slug', $keys['buildings'])->delete();
            DB::table('room_types')->where('slug', 'showcase26-ky-tuc-xa-20-nguoi')->delete();

            DB::table('services')
                ->whereIn('slug', ['electric', 'water', 'showcase26-internet', 'showcase26-trash', 'showcase26-cleaning'])
                ->where('created_by', DB::table('admins')->where('username', 'showcase26_owner')->value('id'))
                ->delete();

            $legacyRegionId = DB::table('regions')->where('code', 'SHOWCASE26-HCM')->value('id');

            if ($legacyRegionId !== null && ! DB::table('buildings')->where('region_id', $legacyRegionId)->exists()) {
                DB::table('regions')->where('id', $legacyRegionId)->delete();
            }

            DB::table('admins')->whereIn('username', $keys['managers'])->delete();
            DB::table('admins')->where('username', 'showcase26_owner')->delete();
        });
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
