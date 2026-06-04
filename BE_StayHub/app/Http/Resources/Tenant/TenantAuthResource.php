<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Trả về định dạng giống với đối tượng Tenant trong Flutter auth_controller
        return [
            'id' => $this->id,
            'building_id' => $this->room?->building_id,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,
            'username' => $this->username,
            'permanent_address' => $this->permanent_address,
            'current_address' => $this->current_address,
            'status' => $this->status,
            'room_number' => $this->room?->room_number ?? '101',
            'building_name' => $this->room?->building?->name ?? 'StayHub Building',
            'identity_type' => $this->identity_type,
            'identity_number' => $this->identity_number,
            'avatar_url' => $this->avatar_url,
        ];
    }
}
