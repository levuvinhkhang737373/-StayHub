<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'invoice' => $this->resource['invoice'] ?? null,
            'contract' => $this->resource['contract'] ?? null,
            'room' => $this->resource['room'] ?? null,
            'building' => $this->resource['building'] ?? null,
            'tenants' => $this->resource['tenants'] ?? [],
            'amounts' => $this->resource['amounts'] ?? [],
            'debt' => $this->resource['debt'] ?? [],
            'rollover' => $this->resource['rollover'] ?? [],
            'period' => $this->resource['period'] ?? [],
        ];
    }
}
