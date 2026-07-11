<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\RoomServicePrice;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomServiceLifecycleService
{
    public const INACTIVE_SCHEDULE_MESSAGE = 'Dịch vụ phòng đã ngừng hoạt động, không thể lên lịch thay đổi giá.';
    public const INACTIVE_REASON = 'Dịch vụ phòng đã ngừng hoạt động.';
    public const NO_CONTRACT_REASON = 'Phòng chưa có hợp đồng hiệu lực trong kỳ áp dụng.';

    public function deactivateAfterFullTransfer(Room $room, Carbon $endedAt): void
    {
        $endDate = $endedAt->toDateString();
        $serviceIds = $this->nonUtilityRoomServicesQuery($room)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        if ($serviceIds->isEmpty()) {
            return;
        }

        RoomService::query()
            ->whereIn('id', $serviceIds->all())
            ->update([
                'is_active' => false,
                'ended_at' => $endDate,
                'updated_at' => now(),
            ]);

        RoomServicePrice::query()
            ->whereIn('room_service_id', $serviceIds->all())
            ->whereDate('effective_from', '>', $endDate)
            ->delete();

        RoomServicePrice::query()
            ->whereIn('room_service_id', $serviceIds->all())
            ->whereDate('effective_from', '<=', $endDate)
            ->where(function (Builder $query) use ($endDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>', $endDate);
            })
            ->update([
                'effective_to' => $endDate,
                'updated_at' => now(),
            ]);
    }

    public function reactivateForContract(Room $room, array|Collection $serviceIds, Carbon $startDate): void
    {
        collect($serviceIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->each(function (int $serviceId) use ($room, $startDate): void {
                RoomService::query()->updateOrCreate(
                    [
                        'room_id' => $room->id,
                        'service_id' => $serviceId,
                    ],
                    [
                        'is_active' => true,
                        'ended_at' => null,
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function schedulability(RoomService $roomService, ?Contract $contract = null, ?Carbon $targetDate = null): array
    {
        if (! $roomService->is_active) {
            return [false, self::INACTIVE_REASON];
        }

        if (! $contract) {
            return [false, self::NO_CONTRACT_REASON];
        }

        if (! in_array((int) $contract->status, Contract::RESERVED_STATUSES, true)) {
            return [false, self::NO_CONTRACT_REASON];
        }

        if ($targetDate && $this->contractEndsBefore($contract, $targetDate)) {
            return [false, 'Kỳ áp dụng giá vượt quá ngày kết thúc hợp đồng đang chọn.'];
        }

        return [true, null];
    }

    public function assertSchedulable(RoomService $roomService, ?Contract $contract = null, ?Carbon $targetDate = null): ?string
    {
        [$canSchedule, $reason] = $this->schedulability($roomService, $contract, $targetDate);

        if ($canSchedule) {
            return null;
        }

        return $reason === self::INACTIVE_REASON ? self::INACTIVE_SCHEDULE_MESSAGE : $reason;
    }

    public function shouldChargeInPeriod(RoomService $roomService, Carbon $periodStart, Carbon $periodEnd): bool
    {
        if ((bool) $roomService->is_active) {
            return true;
        }

        return $roomService->ended_at !== null
            && $roomService->ended_at->copy()->startOfDay()->betweenIncluded($periodStart, $periodEnd);
    }

    private function nonUtilityRoomServicesQuery(Room $room): Builder
    {
        return RoomService::query()
            ->where('room_id', $room->id)
            ->whereHas('service', fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->where('charge_method', '!=', Service::CHARGE_METHOD_BY_METER)
                ->whereNotIn('slug', Service::UTILITY_SLUGS));
    }

    private function contractEndsBefore(Contract $contract, Carbon $targetDate): bool
    {
        $endDate = $contract->actual_end_date ?: $contract->end_date;

        return $endDate !== null && $endDate->copy()->startOfDay()->lt($targetDate->copy()->startOfDay());
    }
}
