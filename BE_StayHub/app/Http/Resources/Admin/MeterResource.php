<?php

namespace App\Http\Resources\Admin;

use App\Models\MeterDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room_number' => $this->relationLoaded('room') ? $this->room?->room_number : null,
            'service_id' => $this->service_id,
            'service_name' => $this->relationLoaded('service') ? $this->service?->name : null,
            'meter_code' => $this->meter_code,
            'meter_type' => (int) $this->meter_type,
            'meter_type_label' => MeterDevice::METER_TYPE_LABELS[$this->meter_type] ?? null,
            'initial_reading' => $this->initial_reading === null ? null : (string) $this->initial_reading,
            'final_reading' => $this->final_reading === null ? null : (string) $this->final_reading,
            'installed_at' => optional($this->installed_at)->toDateString(),
            'status' => (int) $this->status,
            'status_label' => MeterDevice::STATUS_LABELS[$this->status] ?? null,
            'image_path' => $this->image_path,
            'note' => $this->note,
            'replaced_by_meter_id' => $this->replaced_by_meter_id,
            'replacement_meter_code' => $this->relationLoaded('replacementMeter') ? $this->replacementMeter?->meter_code : null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
