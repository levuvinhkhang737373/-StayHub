<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingResource extends JsonResource
{
    /**
     * Dữ liệu tòa nhà tối ưu cho danh sách.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'region_id' => $this->region_id,
            'region_name' => $this->whenLoaded('region', fn (): ?string => $this->region?->name),
            'manager_admin_id' => $this->manager_admin_id,
            'manager_name' => $this->whenLoaded('manager', fn (): ?string => $this->manager?->full_name),
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'gender_policy' => $this->gender_policy,
            'status' => $this->status,
            'total_floors' => $this->total_floors === null ? null : (int) $this->total_floors,
            'description' => $this->description,
            'primary_image' => new BuildingImageResource($this->whenLoaded('primaryImage')),
            'images' => BuildingImageResource::collection($this->whenLoaded('images')),
            'images_count' => $this->whenCounted('images'),
            'rooms_count' => $this->whenCounted('rooms'),
            'asset_templates_count' => $this->whenCounted('assetTemplates'),
            'service_prices_count' => $this->whenCounted('servicePrices'),
            'notifications_count' => $this->whenCounted('notifications'),
            'expenses_count' => $this->whenCounted('expenses'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
