<?php

namespace App\Http\Resources\Admin;

use App\Models\ContractDepositTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractDepositTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'transaction_type' => $this->transaction_type,
            'transaction_type_label' => ContractDepositTransaction::TRANSACTION_TYPE_LABELS[$this->transaction_type] ?? null,
            'amount' => $this->amount === null ? null : (string) $this->amount,
            'transaction_date' => optional($this->transaction_date)->toDateString(),
            'payment_method' => $this->payment_method,
            'payment_method_label' => ContractDepositTransaction::PAYMENT_METHOD_LABELS[$this->payment_method] ?? null,
            'transaction_reference' => $this->transaction_reference,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
