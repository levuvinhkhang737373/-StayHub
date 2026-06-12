<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingDetailResource extends JsonResource
{
    /**
     * Dữ liệu tòa nhà đầy đủ theo schema hiện tại.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'region_id' => $this->region_id,
            'region' => new RegionResource($this->whenLoaded('region')),
            'manager_admin_id' => $this->manager_admin_id,
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager?->id,
                'username' => $this->manager?->username,
                'full_name' => $this->manager?->full_name,
                'email' => $this->manager?->email,
                'phone' => $this->manager?->phone,
                'role' => $this->manager?->role,
                'status' => $this->manager?->status,
            ]),
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'total_floors' => $this->total_floors === null ? null : (int) $this->total_floors,
            'gender_policy' => $this->gender_policy,
            'description' => $this->description,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'primary_image' => new BuildingImageResource($this->whenLoaded('primaryImage')),
            'images' => BuildingImageResource::collection($this->whenLoaded('images')),
            'room_types' => RoomTypeResource::collection($this->whenLoaded('roomTypes')),
            'rooms' => RoomResource::collection($this->whenLoaded('rooms')),
            'asset_templates' => AssetTemplateResource::collection($this->whenLoaded('assetTemplates')),
            'service_prices' => ServicePriceResource::collection($this->whenLoaded('servicePrices')),
            'settings' => SettingResource::collection($this->whenLoaded('settings')),
            'images_count' => $this->whenCounted('images'),
            'rooms_count' => $this->whenCounted('rooms'),
            'room_types_count' => $this->whenCounted('roomTypes'),
            'asset_templates_count' => $this->whenCounted('assetTemplates'),
            'service_prices_count' => $this->whenCounted('servicePrices'),
            'settings_count' => $this->whenCounted('settings'),
            'notifications_count' => $this->whenCounted('notifications'),
            'expenses_count' => $this->whenCounted('expenses'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
        ];
    }
}
