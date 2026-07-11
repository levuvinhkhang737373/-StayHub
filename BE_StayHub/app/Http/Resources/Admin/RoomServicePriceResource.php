<?php

namespace App\Http\Resources\Admin;

use App\Models\Contract;
use App\Models\RoomServicePrice;
use App\Models\Service;
use App\Services\RoomServiceLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class RoomServicePriceResource extends JsonResource
{
    private ?Contract $selectedContract = null;

    public function selectedContract(?Contract $contract): self
    {
        $this->selectedContract = $contract;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $currentDate = now()->startOfDay()->toDateString();
        $targetDate = $this->targetDate($request);
        [$periodStart, $periodEnd] = $this->targetPeriod($request);
        $currentPrice = $this->effectivePriceFor($currentDate);
        $selectedContractId = $this->selectedContract?->id;
        $currentContractPrice = $this->contractPriceFor($currentDate, $currentDate, $selectedContractId);
        $currentDisplayPrice = $currentContractPrice ?: $currentPrice;
        $effectivePrice = $this->effectivePriceFor($targetDate);
        $scheduledPrice = $this->scheduledPriceFor($targetDate);
        $contractPrice = $this->contractPriceFor($periodStart, $periodEnd, $selectedContractId);
        $scheduledContractPrice = $contractPrice?->effective_from?->toDateString() === $targetDate ? $contractPrice : null;
        $targetScheduledPrice = $scheduledPrice ?: $scheduledContractPrice;
        $contract = $contractPrice?->contract ?? $this->selectedContract;
        $contractEnded = $contract ? $this->contractEnded($contract) : false;
        [$canSchedulePrice, $scheduleBlockReason] = app(RoomServiceLifecycleService::class)
            ->schedulability($this->resource, $contract, Carbon::parse($targetDate));
        $isActive = (bool) $this->is_active;
        if (! $isActive) {
            $targetScheduledPrice = null;
        }
        $displayPrice = $contractPrice ?: $effectivePrice;
        $service = $this->service;

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'service_id' => $this->service_id,
            'is_active' => $isActive,
            'ended_at' => optional($this->ended_at)->toDateString(),
            'can_schedule_price' => $canSchedulePrice,
            'schedule_block_reason' => $scheduleBlockReason,
            'service_name' => $service?->name,
            'service_slug' => $service?->slug,
            'charge_method' => $service?->charge_method,
            'charge_method_label' => $service ? (Service::CHARGE_METHOD_LABELS[$service->charge_method] ?? null) : null,
            'unit_name' => $service?->unit_name,
            'base_price' => $currentPrice ? (string) $currentPrice->price : '0.00',
            'current_price' => $currentDisplayPrice ? (string) $currentDisplayPrice->price : '0.00',
            'old_price' => $currentDisplayPrice ? (string) $currentDisplayPrice->price : '0.00',
            'effective_price' => $effectivePrice ? (string) $effectivePrice->price : '0.00',
            'display_price' => $displayPrice ? (string) $displayPrice->price : '0.00',
            'display_price_source' => $contractPrice ? 'contract' : 'room',
            'new_price' => $targetScheduledPrice ? (string) $targetScheduledPrice->price : null,
            'scheduled_price' => $targetScheduledPrice ? (string) $targetScheduledPrice->price : null,
            'active_contract_id' => $contract?->id ?? $contractPrice?->contract_id,
            'active_contract_code' => $contract?->contract_code,
            'contract_status' => $contract?->status,
            'contract_status_label' => $contract ? (Contract::STATUS_LABELS[$contract->status] ?? null) : null,
            'contract_is_ended' => $contractEnded,
            'contract_price' => $contractPrice ? (string) $contractPrice->price : null,
            'contract_effective_from' => optional($contractPrice?->effective_from)->toDateString(),
            'contract_effective_to' => optional($contractPrice?->effective_to)->toDateString(),
            'effective_from' => optional($effectivePrice?->effective_from)->toDateString(),
            'effective_to' => optional($effectivePrice?->effective_to)->toDateString(),
            'status_label' => $this->statusLabelForState($isActive, $contractEnded, $effectivePrice),
            'created_by' => $isActive ? $scheduledPrice?->created_by : null,
            'creator_name' => $isActive && $scheduledPrice?->relationLoaded('creator') ? $scheduledPrice?->creator?->full_name : null,
            'created_at' => $isActive ? optional($scheduledPrice?->created_at)->toDateTimeString() : null,
        ];
    }

    private function targetDate(Request $request): string
    {
        return $this->targetPeriod($request)[0];
    }

    private function targetPeriod(Request $request): array
    {
        $month = (int) $request->input('billing_month', now()->addMonthNoOverflow()->month);
        $year = (int) $request->input('billing_year', now()->addMonthNoOverflow()->year);
        $periodStart = now()->setDate($year, $month, 1)->startOfDay();

        return [
            $periodStart->toDateString(),
            $periodStart->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function effectivePriceFor(string $targetDate, ?int $contractId = null): ?RoomServicePrice
    {
        return $this->relationLoaded('prices')
            ? $this->prices
                ->filter(fn (RoomServicePrice $price): bool => (int) $price->contract_id === (int) $contractId && $price->effective_from->toDateString() <= $targetDate && ($price->effective_to === null || $price->effective_to->toDateString() >= $targetDate))
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

    private function contractPriceFor(string $periodStart, string $periodEnd, ?int $contractId = null): ?RoomServicePrice
    {
        return $this->relationLoaded('prices')
            ? $this->prices
                ->filter(fn (RoomServicePrice $price): bool => $price->contract_id !== null
                    && ($contractId === null || (int) $price->contract_id === $contractId)
                    && $price->effective_from->toDateString() <= $periodEnd
                    && ($price->effective_to === null || $price->effective_to->toDateString() >= $periodStart))
                ->sortByDesc(fn (RoomServicePrice $price): string => sprintf(
                    '%d-%s-%010d',
                    $price->contract && in_array((int) $price->contract->status, Contract::RESERVED_STATUSES, true) ? 1 : 0,
                    optional($price->contract?->start_date)->toDateString() ?? $price->effective_from->toDateString(),
                    (int) $price->id
                ))
                ->first()
            : null;
    }

    private function contractEnded(Contract $contract): bool
    {
        if (! in_array((int) $contract->status, Contract::RESERVED_STATUSES, true)) {
            return true;
        }

        $endDate = $contract->actual_end_date ?: $contract->end_date;

        return $endDate !== null && $endDate->copy()->startOfDay()->lt(now()->startOfDay());
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

    private function statusLabelForState(bool $isActive, bool $contractEnded, ?RoomServicePrice $price): string
    {
        if (! $isActive) {
            return 'Ngừng hoạt động';
        }

        return $contractEnded ? 'Hết hiệu lực' : $this->statusLabel($price);
    }
}
