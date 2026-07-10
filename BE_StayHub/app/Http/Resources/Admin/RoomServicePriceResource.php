<?php

namespace App\Http\Resources\Admin;

use App\Models\RoomServicePrice;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomServicePriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $targetDate = $this->targetDate();
        $effectivePrice = $this->effectivePriceFor($targetDate);
        $scheduledPrice = $this->scheduledPriceFor($targetDate);
        $service = $this->service;

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'service_id' => $this->service_id,
            'service_name' => $service?->name,
            'service_slug' => $service?->slug,
            'charge_method' => $service?->charge_method,
            'charge_method_label' => $service ? (Service::CHARGE_METHOD_LABELS[$service->charge_method] ?? null) : null,
            'unit_name' => $service?->unit_name,
            'base_price' => $effectivePrice ? (string) $effectivePrice->price : '0.00',
            'effective_price' => $effectivePrice ? (string) $effectivePrice->price : '0.00',
            'scheduled_price' => $scheduledPrice ? (string) $scheduledPrice->price : null,
            'effective_from' => optional($effectivePrice?->effective_from)->toDateString(),
            'effective_to' => optional($effectivePrice?->effective_to)->toDateString(),
            'status_label' => $this->statusLabel($effectivePrice),
            'created_by' => $scheduledPrice?->created_by,
            'creator_name' => $scheduledPrice?->relationLoaded('creator') ? $scheduledPrice?->creator?->full_name : null,
            'created_at' => optional($scheduledPrice?->created_at)->toDateTimeString(),
        ];
    }

    private function targetDate(): string
    {
        $month = (int) request()->query('billing_month', now()->addMonthNoOverflow()->month);
        $year = (int) request()->query('billing_year', now()->addMonthNoOverflow()->year);

        return now()->setDate($year, $month, 1)->startOfDay()->toDateString();
    }

    private function effectivePriceFor(string $targetDate): ?RoomServicePrice
    {
        return $this->relationLoaded('prices')
            ? $this->prices
                ->filter(fn (RoomServicePrice $price): bool => $price->contract_id === null && $price->effective_from->toDateString() <= $targetDate && ($price->effective_to === null || $price->effective_to->toDateString() >= $targetDate))
                ->sortByDesc(fn (RoomServicePrice $price): string => $price->effective_from->toDateString())
                ->first()
            : null;
    }

    private function scheduledPriceFor(string $targetDate): ?RoomServicePrice
    {
        return $this->relationLoaded('prices')
            ? $this->prices->first(fn (RoomServicePrice $price): bool => $price->contract_id === null && $price->effective_from->toDateString() === $targetDate)
            : null;
    }

    private function statusLabel(?RoomServicePrice $price): string
    {
        if (! $price) {
            return 'Giá mặc định';
        }

        $today = now()->startOfDay();
        if ($price->effective_from->copy()->startOfDay()->gt($today)) {
            return 'Đã lên lịch';
        }

        if ($price->effective_to && $price->effective_to->copy()->startOfDay()->lt($today)) {
            return 'Hết hiệu lực';
        }

        return 'Đang hiệu lực';
    }
}
