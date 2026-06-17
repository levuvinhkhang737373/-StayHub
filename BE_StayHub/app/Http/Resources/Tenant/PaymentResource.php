<?php

namespace App\Http\Resources\Tenant;

use App\Helpers\ImageHelper;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_code' => $this->payment_code,
            'amount' => $this->amount,
            'payment_date' => optional($this->payment_date)->toDateTimeString(),
            'payment_method' => $this->payment_method,
            'payment_method_label' => Payment::PAYMENT_METHOD_LABELS[$this->payment_method] ?? null,
            'transaction_reference' => $this->transaction_reference,
            'status' => $this->status,
            'status_label' => Payment::STATUS_LABELS[$this->status] ?? null,
            'proof_image' => $this->proof_image,
            'proof_image_url' => $this->proof_image ? ImageHelper::load($this->proof_image) : null,
            'note' => $this->note,
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
