<?php

namespace App\Http\Resources\Admin;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseCategoryDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isActive = (bool) $this->is_active;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $isActive,
            'status' => $isActive,
            'status_label' => ExpenseCategory::ACTIVE_LABELS[$isActive] ?? null,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'full_name' => $this->creator?->full_name,
            ]),
            'expenses_count' => $this->whenCounted('expenses'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
