<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    /**
     * Chuyển dữ liệu khu vực sang dạng tối ưu cho API danh sách.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->whenLoaded('parent', fn (): ?string => $this->parent?->name),
            'slug' => $this->slug,
            'path' => $this->path,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'status' => (bool) $this->is_active,
            'level' => $this->parent_id ? 'ward' : 'city',
            'sort_order' => 0,
            'children_count' => $this->whenCounted('children'),
            'buildings_count' => $this->whenCounted('buildings'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
