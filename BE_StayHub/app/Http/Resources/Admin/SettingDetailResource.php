<?php

namespace App\Http\Resources\Admin;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isPublic = (bool) $this->is_public;

        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'building' => $this->whenLoaded('building', fn () => [
                'id' => $this->building?->id,
                'name' => $this->building?->name,
                'slug' => $this->building?->slug,
                'address' => $this->building?->address,
                'manager_admin_id' => $this->building?->manager_admin_id,
                'status' => $this->building?->status,
            ]),
            'setting_label' => $this->setting_label,
            'setting_name' => $this->setting_name,
            'setting_value' => $this->setting_value,
            'description' => $this->description,
            'is_public' => $isPublic,
            'is_public_label' => Setting::PUBLIC_LABELS[$isPublic] ?? null,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'full_name' => $this->creator?->full_name,
            ]),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
