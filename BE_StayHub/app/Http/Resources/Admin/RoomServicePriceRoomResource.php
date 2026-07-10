<?php

namespace App\Http\Resources\Admin;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomServicePriceRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contract = $this->activeContractForSelectedPeriod($request);

        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'room_number' => $this->room_number,
            'floor' => $this->floor,
            'status' => $this->status,
            'active_contract_id' => $contract?->id,
            'active_contract_code' => $contract?->contract_code,
            'contract_status' => $contract?->status,
            'contract_status_label' => $contract ? (Contract::STATUS_LABELS[$contract->status] ?? null) : null,
            'contract_is_ended' => $contract ? $this->contractEnded($contract) : false,
            'services' => $this->whenLoaded('roomServices', fn () => $this->roomServices
                ->map(fn ($roomService): array => (new RoomServicePriceResource($roomService))
                    ->selectedContract($contract)
                    ->resolve($request))
                ->values()),
        ];
    }

    private function activeContractForSelectedPeriod(Request $request): ?Contract
    {
        if (! $this->relationLoaded('contracts')) {
            return null;
        }

        [$periodStart, $periodEnd] = $this->targetPeriod($request);

        return $this->contracts
            ->filter(function (Contract $contract) use ($periodStart, $periodEnd): bool {
                $contractEnd = $contract->actual_end_date ?: $contract->end_date;

                return $contract->start_date->toDateString() <= $periodEnd
                    && ($contractEnd === null || $contractEnd->toDateString() >= $periodStart);
            })
            ->sortByDesc(fn (Contract $contract): string => sprintf(
                '%d-%s-%010d',
                in_array((int) $contract->status, Contract::RESERVED_STATUSES, true) ? 1 : 0,
                optional($contract->start_date)->toDateString() ?? '',
                (int) $contract->id
            ))
            ->first();
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

    private function contractEnded(Contract $contract): bool
    {
        if (! in_array((int) $contract->status, Contract::RESERVED_STATUSES, true)) {
            return true;
        }

        $endDate = $contract->actual_end_date ?: $contract->end_date;

        return $endDate !== null && $endDate->copy()->startOfDay()->lt(now()->startOfDay());
    }
}
