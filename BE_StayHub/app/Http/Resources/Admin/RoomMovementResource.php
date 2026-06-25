<?php

namespace App\Http\Resources\Admin;

use App\Helpers\DecimalMoney;
use App\Helpers\VietQRHelper;
use App\Models\RoomMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_code' => $this->transfer_code,
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant?->id,
                'username' => $this->tenant?->username,
                'full_name' => $this->tenant?->full_name,
                'phone' => $this->tenant?->phone,
                'email' => $this->tenant?->email,
            ]),
            'contract_id' => $this->contract_id,
            'contract' => $this->whenLoaded('contract', fn () => [
                'id' => $this->contract?->id,
                'contract_code' => $this->contract?->contract_code,
                'room_id' => $this->contract?->room_id,
                'status' => $this->contract?->status,
                'payment_status' => $this->contract?->payment_status,
            ]),
            'source_contract_id' => $this->source_contract_id,
            'source_contract' => $this->contractPayload('sourceContract'),
            'destination_contract_id' => $this->destination_contract_id,
            'destination_contract' => $this->contractPayload('destinationContract'),
            'from_room_id' => $this->from_room_id,
            'from_room' => $this->roomPayload('fromRoom'),
            'to_room_id' => $this->to_room_id,
            'to_room' => $this->roomPayload('toRoom'),
            'movement_type' => $this->movement_type,
            'movement_type_label' => RoomMovement::MOVEMENT_TYPE_LABELS[$this->movement_type] ?? null,
            'status' => $this->status,
            'status_label' => RoomMovement::STATUS_LABELS[$this->status] ?? null,
            'movement_date' => optional($this->movement_date)->toDateTimeString(),
            'old_room_final_amount' => $this->old_room_final_amount === null ? null : (string) $this->old_room_final_amount,
            'transfer_fee' => $this->transfer_fee === null ? null : (string) $this->transfer_fee,
            'deposit_transfer_amount' => $this->deposit_transfer_amount === null ? null : (string) $this->deposit_transfer_amount,
            'deposit_refund_amount' => $this->deposit_refund_amount === null ? null : (string) $this->deposit_refund_amount,
            'deduction_amount' => $this->deduction_amount === null ? null : (string) $this->deduction_amount,
            'manual_refund_amount' => $this->manual_refund_amount === null ? null : (string) $this->manual_refund_amount,
            'deposit_due_amount' => $this->deposit_due_amount === null ? null : (string) $this->deposit_due_amount,
            'extra_charge_amount' => $this->extra_charge_amount === null ? null : (string) $this->extra_charge_amount,
            'settlement_due_amount' => $this->settlement_due_amount === null ? null : (string) $this->settlement_due_amount,
            'settlement_paid_amount' => $this->settlement_paid_amount === null ? null : (string) $this->settlement_paid_amount,
            'settlement_remaining_amount' => $this->settlementRemainingAmount(),
            'settlement_payment_status' => $this->settlement_payment_status,
            'settlement_payment_status_label' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_LABELS[$this->settlement_payment_status] ?? null,
            'settlement_qr_url' => $this->settlementQrUrl(),
            'settlement_payment_references' => $this->settlement_payment_references ?? [],
            'final_electric_reading' => $this->final_electric_reading === null ? null : (string) $this->final_electric_reading,
            'final_water_reading' => $this->final_water_reading === null ? null : (string) $this->final_water_reading,
            'note' => $this->note,
            'scheduled_payload' => $this->scheduled_payload,
            'executed_at' => optional($this->executed_at)->toDateTimeString(),
            'failure_reason' => $this->failure_reason,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name ?? $this->creator?->username),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }

    private function contractPayload(string $relation): ?array
    {
        if (! $this->relationLoaded($relation)) {
            return null;
        }

        $contract = $this->{$relation};

        if (! $contract) {
            return null;
        }

        return [
            'id' => $contract->id,
            'contract_code' => $contract->contract_code,
            'room_id' => $contract->room_id,
            'status' => $contract->status,
            'payment_status' => $contract->payment_status,
        ];
    }

    private function roomPayload(string $relation): ?array
    {
        if (! $this->relationLoaded($relation)) {
            return null;
        }

        $room = $this->{$relation};

        if (! $room) {
            return null;
        }

        return [
            'id' => $room->id,
            'building_id' => $room->building_id,
            'building_name' => $room->relationLoaded('building') ? $room->building?->name : null,
            'room_number' => $room->room_number,
            'floor' => $room->floor === null ? null : (int) $room->floor,
            'status' => $room->status,
        ];
    }

    private function settlementRemainingAmount(): string
    {
        return DecimalMoney::maxZero(DecimalMoney::subtract($this->settlement_due_amount ?? '0', $this->settlement_paid_amount ?? '0'));
    }

    private function settlementQrUrl(): ?string
    {
        $remainingAmount = $this->settlementRemainingAmount();

        if (! $this->transfer_code || ! DecimalMoney::isPositive($remainingAmount)) {
            return null;
        }

        if ((int) $this->status !== RoomMovement::STATUS_EXECUTED) {
            return null;
        }

        if ((int) $this->settlement_payment_status === RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID) {
            return null;
        }

        return VietQRHelper::generateLink(null, null, null, $remainingAmount, $this->transfer_code);
    }
}
