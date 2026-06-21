<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeterImageAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => (bool) ($this->resource['success'] ?? false),
            'reading_value' => $this->resource['reading_value'] ?? null,
            'confidence' => $this->resource['confidence'] ?? null,
            'warning' => $this->resource['warning'] ?? null,
            'anomaly_warning' => $this->resource['anomaly_warning'] ?? null,
            'error' => $this->resource['error'] ?? null,
            'image_path' => $this->resource['image_path'] ?? null,
            'image_url' => $this->resource['image_url'] ?? null,
        ];
    }
}
