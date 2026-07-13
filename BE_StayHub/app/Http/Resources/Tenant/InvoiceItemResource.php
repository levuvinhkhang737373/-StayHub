<?php

namespace App\Http\Resources\Tenant;

use App\Helpers\ImageHelper;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'service_name' => $this->whenLoaded('service', fn (): ?string => $this->service?->name),
            'item_type' => $this->item_type,
            'item_type_label' => InvoiceItem::ITEM_TYPE_LABELS[$this->item_type] ?? null,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
            'meter_reading_id' => $this->meter_reading_id,
            'meter_reading' => $this->whenLoaded('meterReading', fn (): ?array => $this->meterReading ? [
                'id' => $this->meterReading->id,
                'contract_id' => $this->meterReading->contract_id,
                'previous_reading' => $this->meterReading->previous_reading,
                'current_reading' => $this->meterReading->current_reading,
                'consumption' => $this->meterReading->consumption,
                'reading_date' => optional($this->meterReading->reading_date)->toDateString(),
                'image_url' => $this->meterReading->image_path ? ImageHelper::load($this->meterReading->image_path) : null,
            ] : null),
        ];
    }
}
