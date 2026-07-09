<?php

namespace App\Http\Resources\Tenant;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $room = $this->currentRoom();

        // Trả về định dạng giống với đối tượng Tenant trong Flutter auth_controller
        return [
            'id' => $this->id,
            'building_id' => $room?->building_id,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth ? $this->date_of_birth->toDateString() : null,
            'phone' => $this->phone,
            'email' => $this->email,
            'username' => $this->username,
            'permanent_address' => $this->permanent_address,
            'current_address' => $this->current_address,
            'status' => $this->status,
            'room_number' => $room?->room_number,
            'building_name' => $room?->building?->name,
            'identity_type' => $this->identity_type,
            'identity_number' => $this->identity_number,
            'identity_date' => $this->identity_date ? $this->identity_date->toDateString() : null,
            'identity_place' => $this->identity_place,
            'avatar_url' => $this->avatar_url ? \App\Helpers\ImageHelper::temporaryUrlFromDisk($this->avatar_url) : null,
        ];
    }

    private function currentRoom(): ?Room
    {
        if (! $this->resource->relationLoaded('currentContractTenant')) {
            return null;
        }

        $contractTenant = $this->resource->currentContractTenant;

        if (! $contractTenant || ! $contractTenant->relationLoaded('contract')) {
            return null;
        }

        $contract = $contractTenant->contract;

        return $contract?->relationLoaded('room') ? $contract->room : null;
    }
}
