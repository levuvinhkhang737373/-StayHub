<?php

namespace App\Http\Resources\Admin;

use App\Models\RoomMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'from_room_id' => $this->from_room_id,
            'from_room' => $this->roomPayload('fromRoom'),
            'to_room_id' => $this->to_room_id,
            'to_room' => $this->roomPayload('toRoom'),
            'movement_type' => $this->movement_type,
            'movement_type_label' => RoomMovement::MOVEMENT_TYPE_LABELS[$this->movement_type] ?? null,
            'movement_date' => optional($this->movement_date)->toDateTimeString(),
            'old_room_final_amount' => $this->old_room_final_amount === null ? null : (string) $this->old_room_final_amount,
            'transfer_fee' => $this->transfer_fee === null ? null : (string) $this->transfer_fee,
            'deposit_transfer_amount' => $this->deposit_transfer_amount === null ? null : (string) $this->deposit_transfer_amount,
            'deposit_refund_amount' => $this->deposit_refund_amount === null ? null : (string) $this->deposit_refund_amount,
            'deduction_amount' => $this->deduction_amount === null ? null : (string) $this->deduction_amount,
            'final_electric_reading' => $this->final_electric_reading === null ? null : (string) $this->final_electric_reading,
            'final_water_reading' => $this->final_water_reading === null ? null : (string) $this->final_water_reading,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name ?? $this->creator?->username),
            'created_at' => optional($this->created_at)->toDateTimeString(),
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
}
