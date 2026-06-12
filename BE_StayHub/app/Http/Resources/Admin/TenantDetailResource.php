<?php

namespace App\Http\Resources\Admin;

use App\Helpers\ImageHelper;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantDetailResource extends JsonResource
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
                'phone' => $this->creator?->phone,
                'role' => $this->creator?->role,
                'status' => $this->creator?->status,
            ]),
            'username' => $this->username,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar_url' => ImageHelper::temporaryUrlFromDisk($this->avatar_url),
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'gender' => $this->gender,
            'gender_label' => $this->gender ? (Tenant::GENDER_LABELS[$this->gender] ?? null) : null,
            'permanent_address' => $this->permanent_address,
            'current_address' => $this->current_address,
            'status' => $this->status,
            'status_label' => Tenant::STATUS_LABELS[$this->status] ?? null,
            'identity_type' => $this->identity_type,
            'identity_type_label' => $this->identity_type ? (Tenant::IDENTITY_TYPE_LABELS[$this->identity_type] ?? null) : null,
            'identity_number' => $this->identity_number,
            'front_image_url' => ImageHelper::temporaryUrlFromDisk($this->front_image_url),
            'back_image_url' => ImageHelper::temporaryUrlFromDisk($this->back_image_url),
            'identity_verified' => filled($this->identity_number),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'notification_reads_count' => $this->whenCounted('notificationReads'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
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
        if (! $this->relationLoaded('contractTenants')) {
            return null;
        }

        foreach ($this->contractTenants as $contractTenant) {
            if (! $contractTenant->relationLoaded('contract')) {
                continue;
            }

            $contract = $contractTenant->contract;

            if (! $contract?->relationLoaded('room')) {
                continue;
            }

            return $contract->room;
        }

        return null;
    }
}
