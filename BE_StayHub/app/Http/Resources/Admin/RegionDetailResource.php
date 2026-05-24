<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionDetailResource extends JsonResource
{
    /**
     * Chuyển dữ liệu khu vực sang dạng đầy đủ cho API chi tiết.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'parent' => new RegionResource($this->whenLoaded('parent')),
            'slug' => $this->slug,
            'path' => $this->path,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'status' => (bool) $this->is_active,
            'level' => $this->parent_id ? 'ward' : 'city',
            'sort_order' => 0,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'children_count' => $this->whenCounted('children'),
            'buildings_count' => $this->whenCounted('buildings'),
            'children' => RegionResource::collection($this->whenLoaded('children')),
            'buildings' => $this->whenLoaded('buildings', fn () => $this->buildings->map(fn ($building): array => [
                'id' => $building->id,
                'name' => $building->name,
                'slug' => $building->slug,
                'address' => $building->address,
                'status' => $building->status,
            ])),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
