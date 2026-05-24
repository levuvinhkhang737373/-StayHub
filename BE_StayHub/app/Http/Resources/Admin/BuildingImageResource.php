<?php

namespace App\Http\Resources\Admin;

use App\Helpers\ImageHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingImageResource extends JsonResource
{
    /**
     * Dữ liệu ảnh tòa nhà trả về cho API.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'image_path' => $this->image_path,
            'image_url' => ImageHelper::load($this->image_path),
            'is_primary' => (bool) $this->is_primary,
            'sort_order' => (int) $this->sort_order,
            'status' => $this->status,
            'uploaded_by' => $this->uploaded_by,
            'uploader_name' => $this->whenLoaded('uploader', fn (): ?string => $this->uploader?->full_name),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
