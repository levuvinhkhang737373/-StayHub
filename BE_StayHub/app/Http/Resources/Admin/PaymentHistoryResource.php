<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->resource['uid'] ?? null,
            'source_type' => $this->resource['source_type'] ?? null,
            'source_label' => $this->resource['source_label'] ?? null,
            'source_id' => $this->resource['source_id'] ?? null,
            'event_date' => $this->resource['event_date'] ?? null,
            'amount' => $this->resource['amount'] ?? '0.00',
            'signed_amount' => $this->resource['signed_amount'] ?? '0.00',
            'amount_direction' => $this->resource['amount_direction'] ?? null,
            'payment_method' => $this->resource['payment_method'] ?? null,
            'payment_method_label' => $this->resource['payment_method_label'] ?? null,
            'status_group' => $this->resource['status_group'] ?? null,
            'status_label' => $this->resource['status_label'] ?? null,
            'transaction_reference' => $this->resource['transaction_reference'] ?? null,
            'code' => $this->resource['code'] ?? null,
            'building' => $this->resource['building'] ?? null,
            'room' => $this->resource['room'] ?? null,
            'contract' => $this->resource['contract'] ?? null,
            'invoice' => $this->resource['invoice'] ?? null,
            'tenants' => $this->resource['tenants'] ?? [],
            'actor_name' => $this->resource['actor_name'] ?? null,
            'proof_image_url' => $this->resource['proof_image_url'] ?? null,
            'note' => $this->resource['note'] ?? null,
            'can_confirm' => (bool) ($this->resource['can_confirm'] ?? false),
            'metadata' => $this->resource['metadata'] ?? [],
        ];
    }
}
