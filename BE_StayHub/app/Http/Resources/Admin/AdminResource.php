<?php

namespace App\Http\Resources\Admin;

use App\Helpers\ImageHelper;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    /**
     * Dữ liệu admin tối ưu cho danh sách/dropdown.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url ? ImageHelper::load($this->avatar_url) : null,
            'role' => $this->role,
            'role_label' => Admin::ROLE_LABELS[$this->role] ?? null,
            'status' => $this->status,
            'managed_buildings_count' => $this->whenCounted('managedBuildings'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
