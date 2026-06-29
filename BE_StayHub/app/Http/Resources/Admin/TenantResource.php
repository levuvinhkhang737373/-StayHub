<?php

namespace App\Http\Resources\Admin;

use App\Helpers\ImageHelper;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
   
    public function toArray(Request $request): array
    {
        $currentRoom = $this->currentRoomPayload();

        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'room_id' => $currentRoom['room_id'] ?? null,
            'room_number' => $currentRoom['room_number'] ?? null,
            'building_id' => $currentRoom['building_id'] ?? $this->building_id,
            'building_name' => $currentRoom['building_name'] ?? ($this->relationLoaded('building') ? $this->building?->name : null),
            'current_room' => $currentRoom,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'username' => $this->creator?->username,
                'full_name' => $this->creator?->full_name,
                'email' => $this->creator?->email,
                'role' => $this->creator?->role,
            ]),
            'username' => $this->username,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar_url' => ImageHelper::temporaryUrlFromDisk($this->avatar_url),
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'gender' => $this->gender,
            'gender_label' => $this->gender ? (Tenant::GENDER_LABELS[$this->gender] ?? null) : null,
            'status' => $this->status,
            'status_label' => Tenant::STATUS_LABELS[$this->status] ?? null,
            'identity_type' => $this->identity_type,
            'identity_type_label' => $this->identity_type ? (Tenant::IDENTITY_TYPE_LABELS[$this->identity_type] ?? null) : null,
            'identity_number' => $this->identity_number,
            'identity_date' => $this->identity_date ? $this->identity_date->toDateString() : null,
            'identity_place' => $this->identity_place,
            'permanent_address' => $this->permanent_address,
            'front_image_url' => ImageHelper::temporaryUrlFromDisk($this->front_image_url),
            'back_image_url' => ImageHelper::temporaryUrlFromDisk($this->back_image_url),
            'identity_verified' => filled($this->identity_number),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'leave_date' => $this->leave_date,
            'current_contract' => $this->currentContractPayload(),
        ];
    }

    private function currentRoomPayload(): ?array
    {
        $room = $this->currentContractRoom();

        if (! $room) {
            return null;
        }

        $building = $room->relationLoaded('building') ? $room->building : null;

        return [
            'room_id' => $room->id,
            'room_number' => $room->room_number,
            'building_id' => $room->building_id,
            'building_name' => $building?->name,
        ];
    }

    private function currentContractRoom(): ?Room
    {
        $contract = $this->currentContractModel();

        return $contract?->relationLoaded('room') ? $contract->room : null;
    }

    private function currentContractPayload(): ?array
    {
        $contract = $this->currentContractModel();

        if (! $contract) {
            return null;
        }

        return [
            'id' => $contract->id,
            'contract_code' => $contract->contract_code,
            'room_id' => $contract->room_id,
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => optional($contract->end_date)->toDateString(),
            'room_price' => (string) $contract->room_price,
            'deposit_amount' => (string) $contract->deposit_amount,
            'deposit_balance' => (string) $contract->deposit_balance,
            'payment_status' => $contract->payment_status,
            'status' => $contract->status,
        ];
    }

    private function currentContractModel(): ?\App\Models\Contract
    {
        if (! $this->relationLoaded('contractTenants')) {
            return null;
        }

        foreach ($this->contractTenants as $contractTenant) {
            if (! $contractTenant->relationLoaded('contract')) {
                continue;
            }

            $contract = $contractTenant->contract;

            return $contract;
        }

        return null;
    }
}
