<?php

namespace App\Http\Resources\Admin;

use App\Models\AssetTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'name' => $this->name,
            'slug' => $this->slug,
            'default_unit_name' => $this->default_unit_name,
            'default_unit_label' => AssetTemplate::UNIT_LABELS[$this->default_unit_name] ?? null,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => AssetTemplate::STATUS_LABELS[$this->status] ?? null,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'room_assets_count' => $this->whenCounted('roomAssets'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
