<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantDetailResource extends JsonResource
{
    /**
     * Dữ liệu khách thuê đầy đủ cho chi tiết.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'gender' => $this->gender,
            'permanent_address' => $this->permanent_address,
            'current_address' => $this->current_address,
            'status' => $this->status,
            'identity_type' => $this->identity_type,
            'identity_number' => $this->identity_number,
            'front_image_url' => $this->front_image_url,
            'back_image_url' => $this->back_image_url,
            'identity_verified' => filled($this->identity_number),
            'represented_contracts_count' => $this->whenCounted('representedContracts'),
            'contract_tenants_count' => $this->whenCounted('contractTenants'),
            'active_contracts_count' => $this->when(isset($this->active_contracts_count), $this->active_contracts_count),
            'room_movements_count' => $this->whenCounted('roomMovements'),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'maintenance_requests_count' => $this->whenCounted('maintenanceRequests'),
            'maintenance_feedbacks_count' => $this->whenCounted('maintenanceFeedbacks'),
            'notification_reads_count' => $this->whenCounted('notificationReads'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
        ];
    }
}
