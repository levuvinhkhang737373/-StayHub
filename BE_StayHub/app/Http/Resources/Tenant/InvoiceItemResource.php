<?php

namespace App\Http\Resources\Tenant;

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
        ];
    }
}
