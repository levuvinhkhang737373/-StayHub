<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractTenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant?->id,
                'full_name' => $this->tenant?->full_name,
                'phone' => $this->tenant?->phone,
                'email' => $this->tenant?->email,
                'identity_number' => $this->tenant?->identity_number,
                'status' => $this->tenant?->status,
            ]),
            'join_date' => optional($this->join_date)->toDateString(),
            'leave_date' => optional($this->leave_date)->toDateString(),
            'billing_start_date' => optional($this->billing_start_date)->toDateString(),
            'billing_end_date' => optional($this->billing_end_date)->toDateString(),
            'is_staying' => (bool) $this->is_staying,
            'is_staying_label' => $this->is_staying ? 'Còn đang ở' : 'Đã rời đi',
            'created_by' => $this->created_by,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
