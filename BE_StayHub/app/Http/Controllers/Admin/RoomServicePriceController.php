<?php

namespace App\Http\Controllers\Admin;

use App\Events\NotificationSent;
use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomServicePrice\IndexRequest;
use App\Http\Requests\Admin\RoomServicePrice\ShowRequest;
use App\Http\Requests\Admin\RoomServicePrice\UpdateRequest;
use App\Http\Resources\Admin\RoomServicePriceRoomResource;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Notification;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\RoomServicePrice;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomServicePriceController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem phòng của tòa nhà này', 403, null, 403);
            }

            $targetDate = $this->periodStart((int) $validated['billing_year'], (int) $validated['billing_month']);
            $rooms = $this->roomQuery($validated, $admin, $targetDate)
                ->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách giá dịch vụ phòng', 200, [
                'data' => RoomServicePriceRoomResource::collection($rooms->getCollection()),
                'current_page' => $rooms->currentPage(),
                'per_page' => $rooms->perPage(),
                'last_page' => $rooms->lastPage(),
                'total' => $rooms->total(),
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(ShowRequest $request, int $room): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            $targetDate = $this->periodStart((int) $validated['billing_year'], (int) $validated['billing_month']);
            $roomModel = $this->roomQuery(['room_id' => $room] + $validated, $admin, $targetDate)->find($room);

            if (! $roomModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phòng hoặc bạn không có quyền truy cập', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết giá dịch vụ phòng', 200, new RoomServicePriceRoomResource($roomModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $room): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            $targetDate = $this->periodStart((int) $validated['billing_year'], (int) $validated['billing_month']);
            if (! $this->isFuturePeriod($targetDate)) {
                return ApiResponse::responseJson(false, 'Chỉ được lên lịch giá dịch vụ phòng cho tháng sau hoặc tương lai.', 422, null, 422);
            }

            $roomModel = Room::query()->with('building:id,name')->find($room);
            if (! $roomModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phòng', 404, null, 404);
            }

            if (! AdminScope::ensureBuildingAccess($admin, (int) $roomModel->building_id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật giá dịch vụ của phòng này', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $targetDate, $roomModel, $admin, $request): JsonResponse {
                $ids = collect($validated['prices'])->pluck('room_service_id')->map(fn ($id): int => (int) $id)->all();
                $roomServices = RoomService::query()
                    ->with(['service:id,name,slug,charge_method,unit_name,is_active'])
                    ->where('room_id', $roomModel->id)
                    ->whereIn('id', $ids)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($roomServices->count() !== count($ids)) {
                    return ApiResponse::responseJson(false, 'Dịch vụ phòng không thuộc phòng đang chọn.', 422, null, 422);
                }

                if ($roomServices->contains(fn (RoomService $roomService): bool => ! $roomService->service || $roomService->service->isMeteredUtility())) {
                    return ApiResponse::responseJson(false, 'Không thể lên lịch giá điện/nước theo từng phòng.', 422, null, 422);
                }

                $updatedPrices = collect();
                $contract = $this->activeContractForRoom($roomModel);
                if ($contract && $this->contractEndsBeforePeriod($contract, $targetDate)) {
                    return ApiResponse::responseJson(false, 'Kỳ áp dụng giá vượt quá ngày kết thúc hợp đồng hiện tại của phòng.', 422, null, 422);
                }

                foreach ($validated['prices'] as $item) {
                    $roomService = $roomServices->get((int) $item['room_service_id']);
                    $price = DecimalMoney::normalize($item['price']);
                    $updatedPrices->push($this->schedulePrice($roomService, $targetDate, $price, $admin, $contract));
                }

                $this->notifyTenants($roomModel, $updatedPrices, $targetDate, $admin);

                AdminActivityLogger::write($admin, 'Lên lịch giá dịch vụ phòng', Room::class, $roomModel->id, null, [
                    'room_id' => $roomModel->id,
                    'billing_month' => $targetDate->month,
                    'billing_year' => $targetDate->year,
                    'prices' => $updatedPrices->map(fn (RoomServicePrice $price): array => [
                        'room_service_id' => $price->room_service_id,
                        'price' => $price->price,
                        'effective_from' => $price->effective_from?->toDateString(),
                    ])->values()->all(),
                ], $request);

                $roomModel->load($this->roomRelations($targetDate));

                return ApiResponse::responseJson(true, 'Lên lịch giá dịch vụ phòng thành công', 200, new RoomServicePriceRoomResource($roomModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function roomQuery(array $validated, Admin $admin, Carbon $targetDate): Builder
    {
        $keyword = trim((string) ($validated['keyword'] ?? ''));

        return AdminScope::applyBuildingScope(Room::query(), $admin)
            ->select(['id', 'building_id', 'room_type_id', 'room_number', 'floor', 'status'])
            ->with($this->roomRelations($targetDate))
            ->whereHas('roomServices', fn (Builder $query): Builder => $this->nonUtilityServiceScope($query))
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $query->where('building_id', (int) $validated['building_id']))
            ->when(isset($validated['room_id']), fn (Builder $query): Builder => $query->whereKey((int) $validated['room_id']))
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where('room_number', 'like', "%{$keyword}%"))
            ->orderBy('building_id')
            ->orderBy('floor')
            ->orderBy('room_number');
    }

    private function roomRelations(Carbon $targetDate): array
    {
        return [
            'building:id,name',
            'roomServices' => fn ($query) => $this->nonUtilityServiceScope($query)
                ->select(['id', 'room_id', 'service_id', 'price'])
                ->with([
                    'service:id,name,slug,charge_method,unit_name,is_active',
                    'prices' => fn ($priceQuery) => $priceQuery
                        ->with('creator:id,full_name')
                        ->whereDate('effective_from', '<=', $targetDate->toDateString())
                        ->where(function (Builder $query) use ($targetDate): void {
                            $query->whereNull('effective_to')
                                ->orWhereDate('effective_to', '>=', $targetDate->toDateString());
                        })
                        ->orderByDesc('effective_from')
                        ->orderByDesc('id'),
                ])
                ->orderBy('id'),
        ];
    }

    private function nonUtilityServiceScope($query)
    {
        return $query->whereHas('service', fn (Builder $serviceQuery): Builder => $serviceQuery
            ->where('is_active', true)
            ->where('charge_method', '!=', Service::CHARGE_METHOD_BY_METER)
            ->whereNotIn('slug', Service::UTILITY_SLUGS));
    }

    private function schedulePrice(RoomService $roomService, Carbon $targetDate, string $price, Admin $admin, ?Contract $contract = null): RoomServicePrice
    {
        $effectiveFrom = $targetDate->toDateString();
        $previousEnd = $targetDate->copy()->subDay()->toDateString();
        $effectiveTo = $this->contractEffectiveTo($contract);

        $existing = RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->where('contract_id', $contract?->id)
            ->whereDate('effective_from', $effectiveFrom)
            ->lockForUpdate()
            ->first();

        RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->where('contract_id', $contract?->id)
            ->whereDate('effective_from', '<', $effectiveFrom)
            ->where(function (Builder $query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveFrom);
            })
            ->update([
                'effective_to' => $previousEnd,
                'updated_at' => now(),
            ]);

        if ($existing) {
            $existing->forceFill([
                'price' => $price,
                'effective_to' => $effectiveTo,
                'created_by' => $admin->id,
            ])->save();

            return $existing->fresh(['roomService.service']);
        }

        return RoomServicePrice::query()->create([
            'room_service_id' => $roomService->id,
            'contract_id' => $contract?->id,
            'price' => $price,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'created_by' => $admin->id,
        ])->load('roomService.service');
    }

    private function activeContractForRoom(Room $room): ?Contract
    {
        return Contract::query()
            ->select(['id', 'room_id', 'status', 'start_date', 'end_date', 'actual_end_date'])
            ->where('room_id', $room->id)
            ->whereIn('status', [Contract::STATUS_PENDING_SIGN, Contract::STATUS_ACTIVE])
            ->orderByDesc('status')
            ->orderByDesc('start_date')
            ->first();
    }

    private function contractEndsBeforePeriod(Contract $contract, Carbon $targetDate): bool
    {
        $endDate = $contract->actual_end_date ?: $contract->end_date;

        return $endDate !== null && $endDate->copy()->startOfDay()->lt($targetDate->copy()->startOfDay());
    }

    private function contractEffectiveTo(?Contract $contract): ?string
    {
        $endDate = $contract?->actual_end_date ?: $contract?->end_date;

        return $endDate?->toDateString();
    }

    private function notifyTenants(Room $room, Collection $prices, Carbon $targetDate, Admin $admin): void
    {
        $tenantIds = ContractTenant::query()
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', fn (Builder $query): Builder => $query
                ->where('room_id', $room->id)
                ->where('status', Contract::STATUS_ACTIVE))
            ->pluck('tenant_id')
            ->unique()
            ->values();

        if ($tenantIds->isEmpty()) {
            return;
        }

        $serviceSummary = $prices
            ->map(fn (RoomServicePrice $price): string => ($price->roomService?->service?->name ?? 'Dịch vụ').' '.number_format(DecimalMoney::toIntegerAmount($price->price), 0, ',', '.').' đ')
            ->implode(', ');

        foreach ($tenantIds as $tenantId) {
            $notification = Notification::query()->create([
                'title' => 'Lên lịch thay đổi giá dịch vụ phòng',
                'content' => "Phòng {$room->room_number} đã được lên lịch thay đổi giá dịch vụ từ tháng {$targetDate->month}/{$targetDate->year}: {$serviceSummary}.",
                'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                'target_type' => Notification::TARGET_TYPE_TENANT,
                'building_id' => $room->building_id,
                'room_id' => $room->id,
                'tenant_id' => (int) $tenantId,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]);

            broadcast(new NotificationSent($notification));
        }
    }

    private function periodStart(int $year, int $month): Carbon
    {
        return Carbon::create($year, $month, 1)->startOfDay();
    }

    private function isFuturePeriod(Carbon $targetDate): bool
    {
        return $targetDate->greaterThan(now()->copy()->startOfMonth());
    }
}
