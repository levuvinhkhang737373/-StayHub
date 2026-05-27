<?php

namespace App\Http\Resources\Admin;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role,
            'role_label' => Admin::ROLE_LABELS[$this->role] ?? null,
            'status' => $this->status,
            'status_label' => Admin::STATUS_LABELS[$this->status] ?? null,
            'gender' => $this->gender,
            'gender_label' => $this->gender ? (Admin::GENDER_LABELS[$this->gender] ?? null) : null,
            'address' => $this->address,
            'has_faceid' => filled($this->image_path_faceid),
            'managed_buildings_count' => $this->whenCounted('managedBuildings'),
            'managed_buildings' => $this->whenLoaded('managedBuildings', fn () => $this->managedBuildings->map(fn ($building): array => [
                'id' => $building->id,
                'name' => $building->name,
                'slug' => $building->slug,
                'address' => $building->address,
                'status' => $building->status,
            ])->values()),
            'managed_building_names' => $this->whenLoaded('managedBuildings', fn () => $this->managedBuildings->pluck('name')->filter()->values()),
            'logs_count' => $this->whenCounted('logs'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
