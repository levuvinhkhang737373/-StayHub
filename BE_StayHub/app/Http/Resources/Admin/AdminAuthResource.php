<?php

namespace App\Http\Resources\Admin;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAuthResource extends JsonResource
{
    /**
     * Dữ liệu admin sau đăng nhập theo schema hiện tại.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'image_path_faceid' => $this->image_path_faceid,
            'has_faceid' => filled($this->image_path_faceid),
            'role' => $this->role,
            'role_label' => Admin::ROLE_LABELS[$this->role] ?? null,
            'status' => $this->status,
            'gender' => $this->gender,
            'address' => $this->address,
            'managed_buildings_count' => $this->whenCounted('managedBuildings'),
            'managed_buildings' => $this->whenLoaded('managedBuildings', fn () => $this->managedBuildings->map(fn ($building): array => [
                'id' => $building->id,
                'name' => $building->name,
                'slug' => $building->slug,
                'gender_policy' => $building->gender_policy,
                'status' => $building->status,
            ])->values()),
            'managed_building_names' => $this->whenLoaded('managedBuildings', fn () => $this->managedBuildings->pluck('name')->filter()->values()),
            'created_faceid_at' => optional($this->created_faceid_at)->toDateTimeString(),
            'updated_faceid_at' => optional($this->updated_faceid_at)->toDateTimeString(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
