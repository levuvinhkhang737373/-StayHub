<?php

namespace App\Services;

use App\Helpers\DecimalMoney;
use App\Models\Contract;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\RoomServicePrice;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomServicePriceResolver
{
    // Lấy giá dịch vụ phòng có hiệu lực tại thời điểm chỉ định
    public function effectiveRoomServicePrice(RoomService $roomService, Carbon $targetDate, ?Contract $contract = null): ?RoomServicePrice
    {
        return RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->forContractOrDefault($contract?->id)
            ->effectiveFor($targetDate)
            ->priorityForContract($contract?->id)
            ->first();
    }

    // Lấy số tiền giá dịch vụ có hiệu lực
    public function effectivePriceAmount(RoomService $roomService, Carbon $targetDate, ?Contract $contract = null): ?string
    {
        $price = $this->effectiveRoomServicePrice($roomService, $targetDate, $contract);

        return $price ? DecimalMoney::normalize($price->price) : null;
    }

    // Lấy giá dịch vụ mặc định của phòng
    public function defaultRoomServicePrice(RoomService $roomService, Carbon $targetDate): ?RoomServicePrice
    {
        return RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->whereNull('contract_id')
            ->effectiveFor($targetDate)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    // Đảm bảo dịch vụ phòng được cấu hình đầy đủ
    public function ensureRoomService(Room $room, Service|int $service): RoomService
    {
        $serviceId = $service instanceof Service ? $service->id : $service;

        return RoomService::query()->updateOrCreate(
            [
                'room_id' => $room->id,
                'service_id' => (int) $serviceId,
            ],
            [
                'is_active' => true,
                'ended_at' => null,
            ]
        );
    }

    // Lấy giá mặc định từ bảng giá dịch vụ
    public function ensureDefaultPriceFromServicePrice(Room $room, ServicePrice $servicePrice, ?int $createdBy = null): ?RoomServicePrice
    {
        $service = $servicePrice->relationLoaded('service') ? $servicePrice->service : $servicePrice->service()->first();
        if (! $service || $service->isMeteredUtility()) {
            return null;
        }

        return DB::transaction(function () use ($room, $servicePrice, $createdBy): RoomServicePrice {
            $roomService = $this->ensureRoomService($room, (int) $servicePrice->service_id);
            $effectiveFrom = $servicePrice->effective_from?->toDateString() ?? now()->toDateString();

            return RoomServicePrice::query()->updateOrCreate(
                [
                    'room_service_id' => $roomService->id,
                    'contract_id' => null,
                    'effective_from' => $effectiveFrom,
                ],
                [
                    'price' => DecimalMoney::normalize($servicePrice->price),
                    'effective_to' => $servicePrice->effective_to?->toDateString(),
                    'created_by' => $createdBy,
                ]
            );
        });
    }

    // Lấy danh sách giá dịch vụ áp dụng cho hợp đồng
    public function servicePricesForContract(Room $room, Contract $contract, Carbon $targetDate): Collection
    {
        return RoomService::query()
            ->with(['service:id,name,slug,charge_method,unit_name,is_active'])
            ->where('room_id', $room->id)
            ->usableForPeriod($targetDate, $targetDate)
            ->whereHas('service', fn (Builder $query): Builder => $query->where('is_active', true))
            ->get()
            ->map(function (RoomService $roomService) use ($contract, $targetDate): array {
                return [
                    'room_service' => $roomService,
                    'service' => $roomService->service,
                    'price' => $this->effectivePriceAmount($roomService, $targetDate, $contract),
                ];
            })
            ->filter(fn (array $item): bool => $item['service'] && $item['price'] !== null)
            ->values();
    }
}
