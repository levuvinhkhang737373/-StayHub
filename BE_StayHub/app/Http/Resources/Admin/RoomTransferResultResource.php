<?php

namespace App\Http\Resources\Admin;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTransferResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $movement = (new RoomMovementResource($this->resource['movement']))->resolve($request);

        return array_merge($movement, [
            'transfer_code' => $this->resource['transfer_code'] ?? ($movement['transfer_code'] ?? null),
            'movement' => $movement,
            'movements' => isset($this->resource['movements']) ? RoomMovementResource::collection($this->resource['movements'])->resolve($request) : [$movement],
            'old_invoice' => isset($this->resource['old_invoice']) && $this->resource['old_invoice'] ? new InvoiceDetailResource($this->resource['old_invoice']) : null,
            'new_contract' => $this->newContractPayload(),
            'deposit' => $this->resource['deposit'] ?? null,
            'scheduled_payload' => $this->resource['scheduled_payload'] ?? null,
            'execute_result' => $this->resource['execute_result'] ?? null,
            'executed_immediately' => $this->resource['executed_immediately'] ?? false,
            'blocked_immediately' => $this->resource['blocked_immediately'] ?? false,
        ]);
    }

    private function newContractPayload(): ?array
    {
        $contract = $this->resource['new_contract'] ?? null;

        if (! $contract) {
            return null;
        }

        return [
            'id' => $contract->id,
            'contract_code' => $contract->contract_code,
            'room_id' => $contract->room_id,
            'room_number' => $contract->room?->room_number,
            'building_name' => $contract->room?->building?->name,
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => optional($contract->end_date)->toDateString(),
            'room_price' => (string) $contract->room_price,
            'deposit_amount' => (string) $contract->deposit_amount,
            'deposit_balance' => (string) $contract->deposit_balance,
            'payment_status' => $contract->payment_status,
            'payment_status_label' => Contract::PAYMENT_STATUS_LABELS[$contract->payment_status] ?? null,
            'status' => $contract->status,
            'status_label' => Contract::STATUS_LABELS[$contract->status] ?? null,
        ];
    }
}
