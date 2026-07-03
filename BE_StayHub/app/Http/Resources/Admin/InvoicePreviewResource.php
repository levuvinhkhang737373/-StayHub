<?php

namespace App\Http\Resources\Admin;

use App\Helpers\ImageHelper;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Contract $contract */
        $contract = $this->resource['contract'];
        /** @var Admin $admin */
        $admin = $this->resource['admin'];
        $room = $contract->room;
        $status = (int) $this->resource['status'];

        return [
            'is_preview' => true,
            'can_issue' => true,
            'id' => null,
            'invoice_code' => null,
            'invoice_code_note' => 'Mã hóa đơn sẽ được cấp khi phát hành',
            'contract_id' => $contract->id,
            'contract_code' => $contract->contract_code,
            'room_id' => $contract->room_id,
            'room_number' => $room?->room_number,
            'building_id' => $room?->building_id,
            'building_name' => $room?->relationLoaded('building') ? $room?->building?->name : null,
            'tenant_name' => $contract->relationLoaded('contractTenants') ? $contract->contractTenants?->first()?->tenant?->full_name : null,
            'room' => $room ? [
                'id' => $room->id,
                'building_id' => $room->building_id,
                'building_name' => $room->relationLoaded('building') ? $room->building?->name : null,
                'room_number' => $room->room_number,
                'floor' => $room->floor,
                'status' => $room->status,
            ] : null,
            'tenants' => $this->tenantSummaries($contract),
            'billing_month' => (int) $this->resource['period_start']->month,
            'billing_year' => (int) $this->resource['period_start']->year,
            'period_start' => $this->resource['period_start']->toDateString(),
            'period_end' => $this->resource['period_end']->toDateString(),
            'previous_debt_amount' => $this->resource['previous_debt_amount'],
            'total_amount' => $this->resource['total_amount'],
            'paid_amount' => '0.00',
            'remaining_amount' => $this->resource['total_amount'],
            'due_date' => $this->resource['due_date']->toDateString(),
            'status' => $status,
            'status_label' => Invoice::STATUS_LABELS[$status] ?? null,
            'issued_at' => null,
            'created_by' => $admin->id,
            'creator_name' => $admin->full_name,
            'payment_qr_url' => null,
            'items' => $this->previewItems(),
            'payments' => [],
            'transfer_cutoffs' => $this->resource['transfer_cutoffs'] ?? [],
            'items_count' => count($this->resource['items']),
            'payments_count' => 0,
            'created_at' => null,
            'updated_at' => null,
            'preview_generated_at' => now()->toDateTimeString(),
        ];
    }

    private function tenantSummaries(Contract $contract): array
    {
        if (! $contract->relationLoaded('contractTenants')) {
            return [];
        }

        return $contract->contractTenants
            ->map(fn ($contractTenant): array => [
                'id' => $contractTenant->tenant_id,
                'full_name' => $contractTenant->tenant?->full_name,
                'phone' => $contractTenant->tenant?->phone,
                'email' => $contractTenant->tenant?->email,
                'is_staying' => (bool) $contractTenant->is_staying,
            ])
            ->values()
            ->all();
    }

    private function previewItems(): array
    {
        $items = collect($this->resource['items']);
        $serviceIds = $items->pluck('service_id')->filter()->unique()->values();
        $meterReadingIds = $items->pluck('meter_reading_id')->filter()->unique()->values();

        $services = $serviceIds->isEmpty()
            ? collect()
            : Service::query()->whereIn('id', $serviceIds)->pluck('name', 'id');
        $meterReadings = $meterReadingIds->isEmpty()
            ? collect()
            : MeterReading::query()->whereIn('id', $meterReadingIds)->get()->keyBy('id');

        return $items
            ->values()
            ->map(function (array $item) use ($services, $meterReadings): array {
                $meterReading = $item['meter_reading_id'] ? $meterReadings->get($item['meter_reading_id']) : null;

                return [
                    'id' => null,
                    'invoice_id' => null,
                    'service_id' => $item['service_id'],
                    'service_name' => $item['service_id'] ? $services->get($item['service_id']) : null,
                    'meter_reading_id' => $item['meter_reading_id'],
                    'meter_reading' => $this->meterReadingSummary($meterReading),
                    'item_type' => $item['item_type'],
                    'item_type_label' => InvoiceItem::ITEM_TYPE_LABELS[$item['item_type']] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => $item['amount'],
                    'created_at' => null,
                    'updated_at' => null,
                ];
            })
            ->all();
    }

    private function meterReadingSummary(?MeterReading $meterReading): ?array
    {
        if (! $meterReading) {
            return null;
        }

        return [
            'id' => $meterReading->id,
            'meter_device_id' => $meterReading->meter_device_id,
            'previous_reading' => $meterReading->previous_reading,
            'current_reading' => $meterReading->current_reading,
            'consumption' => $meterReading->consumption,
            'reading_date' => optional($meterReading->reading_date)->toDateString(),
            'image_url' => $meterReading->image_path ? ImageHelper::load($meterReading->image_path) : null,
        ];
    }
}
