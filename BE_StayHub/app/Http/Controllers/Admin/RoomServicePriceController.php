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
    // Danh sách bảng giá dịch vụ áp dụng cho các phòng
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
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Chi tiết bảng giá dịch vụ của một phòng cụ thể
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
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật/cấu hình bảng giá dịch vụ cho phòng
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
                $activeContract = $this->activeContractForRoom($roomModel);
                if ($activeContract && $this->contractEndsBeforePeriod($activeContract, $targetDate)) {
                    return ApiResponse::responseJson(false, 'Kỳ áp dụng giá vượt quá ngày kết thúc hợp đồng hiện tại của phòng.', 422, null, 422);
                }

                foreach ($validated['prices'] as $item) {
                    $roomService = $roomServices->get((int) $item['room_service_id']);
                    $contract = $this->contractForPriceItem($item, $roomModel, $activeContract);
                    if ($contract === false) {
                        return ApiResponse::responseJson(false, 'Hợp đồng áp dụng giá không thuộc phòng đang chọn.', 422, null, 422);
                    }

                    if ($contract && $this->contractEndsBeforePeriod($contract, $targetDate)) {
                        return ApiResponse::responseJson(false, 'Kỳ áp dụng giá vượt quá ngày kết thúc hợp đồng đang chọn.', 422, null, 422);
                    }

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
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo truy vấn phòng trong phạm vi quản lý
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

    // Các quan hệ liên kết của phòng cần load
    private function roomRelations(Carbon $targetDate): array
    {
        $periodEnd = $targetDate->copy()->endOfMonth();

        return [
            'building:id,name',
            'contracts:id,contract_code,room_id,status,start_date,end_date,actual_end_date',
            'roomServices' => fn ($query) => $this->nonUtilityServiceScope($query)
                ->select(['id', 'room_id', 'service_id'])
                ->with([
                    'service:id,name,slug,charge_method,unit_name,is_active',
                    'prices' => fn ($priceQuery) => $priceQuery
                        ->with([
                            'creator:id,full_name',
                            'contract:id,room_id,contract_code,status,start_date,end_date,actual_end_date',
                        ])
                        ->where(function (Builder $periodQuery) use ($targetDate, $periodEnd): void {
                            $today = now()->startOfDay()->toDateString();
                            $target = $targetDate->toDateString();
                            $targetEnd = $periodEnd->toDateString();

                            $periodQuery->where(function (Builder $query) use ($target, $targetEnd): void {
                                $query->whereDate('effective_from', '<=', $targetEnd)
                                    ->where(function (Builder $scope) use ($target): void {
                                        $scope->whereNull('effective_to')
                                            ->orWhereDate('effective_to', '>=', $target);
                                    });
                            })->orWhere(function (Builder $query) use ($today): void {
                                $query->whereDate('effective_from', '<=', $today)
                                    ->where(function (Builder $scope) use ($today): void {
                                        $scope->whereNull('effective_to')
                                            ->orWhereDate('effective_to', '>=', $today);
                                    });
                            });
                        })
                        ->orderByDesc('effective_from')
                        ->orderByDesc('id'),
                ])
                ->orderBy('id'),
        ];
    }

    // Lấy danh sách dịch vụ không phải điện nước
    private function nonUtilityServiceScope($query)
    {
        return $query->whereHas('service', fn (Builder $serviceQuery): Builder => $serviceQuery
            ->where('is_active', true)
            ->where('charge_method', '!=', Service::CHARGE_METHOD_BY_METER)
            ->whereNotIn('slug', Service::UTILITY_SLUGS));
    }

    // Lập lịch thay đổi giá dịch vụ trong tương lai
    private function schedulePrice(RoomService $roomService, Carbon $targetDate, string $price, Admin $admin, ?Contract $contract = null): RoomServicePrice
    {
        $effectiveFrom = $targetDate->toDateString();
        $previousEnd = $targetDate->copy()->subDay()->toDateString();
        $contractId = $contract?->id;
        $effectiveTo = $this->contractEffectiveTo($contract);

        $existing = RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->when($contractId, fn (Builder $query): Builder => $query->where('contract_id', $contractId), fn (Builder $query): Builder => $query->whereNull('contract_id'))
            ->whereDate('effective_from', $effectiveFrom)
            ->lockForUpdate()
            ->first();

        RoomServicePrice::query()
            ->where('room_service_id', $roomService->id)
            ->when($contractId, fn (Builder $query): Builder => $query->where('contract_id', $contractId), fn (Builder $query): Builder => $query->whereNull('contract_id'))
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
            'contract_id' => $contractId,
            'price' => $price,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'created_by' => $admin->id,
        ])->load('roomService.service');
    }

    // Tìm hợp đồng liên quan đến mục giá dịch vụ
    private function contractForPriceItem(array $item, Room $room, ?Contract $activeContract): Contract|false|null
    {
        if (! array_key_exists('contract_id', $item) || $item['contract_id'] === null || $item['contract_id'] === '') {
            return null;
        }

        $contractId = (int) $item['contract_id'];
        if ($activeContract && (int) $activeContract->id === $contractId) {
            return $activeContract;
        }

        return Contract::query()
            ->select(['id', 'room_id', 'status', 'start_date', 'end_date', 'actual_end_date'])
            ->whereKey($contractId)
            ->where('room_id', $room->id)
            ->first() ?: false;
    }

    // Tìm hợp đồng đang có hiệu lực của phòng
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

    // Kiểm tra hợp đồng kết thúc trước chu kỳ tính tiền không
    private function contractEndsBeforePeriod(Contract $contract, Carbon $targetDate): bool
    {
        $endDate = $contract->actual_end_date ?: $contract->end_date;

        return $endDate !== null && $endDate->copy()->startOfDay()->lt($targetDate->copy()->startOfDay());
    }

    // Xác định ngày kết thúc hiệu lực thực tế của hợp đồng trong kỳ
    private function contractEffectiveTo(?Contract $contract): ?string
    {
        $endDate = $contract?->actual_end_date ?: $contract?->end_date;

        return $endDate?->toDateString();
    }

    // Gửi thông báo thay đổi giá dịch vụ đến khách thuê trong phòng
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

    // Xác định ngày bắt đầu của chu kỳ tính tiền chỉ định
    private function periodStart(int $year, int $month): Carbon
    {
        return Carbon::create($year, $month, 1)->startOfDay();
    }

    // Kiểm tra chu kỳ tính tiền có phải trong tương lai không
    private function isFuturePeriod(Carbon $targetDate): bool
    {
        return $targetDate->greaterThan(now()->copy()->startOfMonth());
    }
}
