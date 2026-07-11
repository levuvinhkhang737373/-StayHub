<?php

namespace App\Support\BusinessRules;

use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class OperationalStateGuard
{
    public static function roomStatusTransitionBlockReason(Room $room, int $nextStatus): ?string
    {
        if (! in_array($nextStatus, [Room::STATUS_INACTIVE, Room::STATUS_MAINTENANCE], true)) {
            return null;
        }

        $statusLabel = self::roomStatusLabel($nextStatus);

        if ((int) $room->current_occupants > 0) {
            return "Không thể chuyển phòng sang trạng thái {$statusLabel} khi đang có khách ở.";
        }

        if (Contract::query()->where('room_id', $room->id)->where('status', Contract::STATUS_ACTIVE)->exists()) {
            return "Không thể chuyển phòng sang trạng thái {$statusLabel} khi đang có hợp đồng hiệu lực.";
        }

        if (Contract::query()->where('room_id', $room->id)->where('status', Contract::STATUS_PENDING_SIGN)->exists()) {
            return "Không thể chuyển phòng sang trạng thái {$statusLabel} khi đang có hợp đồng chờ ký.";
        }

        if (RoomMovement::query()
            ->where(function (Builder $query) use ($room): void {
                $query->where('from_room_id', $room->id)->orWhere('to_room_id', $room->id);
            })
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->exists()) {
            return "Không thể chuyển phòng sang trạng thái {$statusLabel} khi đang có lịch chuyển/trả phòng chờ xử lý.";
        }

        return null;
    }

    public static function buildingStatusTransitionBlockReason(Building $building, int $nextStatus): ?string
    {
        if (! in_array($nextStatus, [Building::STATUS_INACTIVE, Building::STATUS_MAINTENANCE], true)) {
            return null;
        }

        $statusLabel = self::buildingStatusLabel($nextStatus);

        if (Room::query()->where('building_id', $building->id)->where('current_occupants', '>', 0)->exists()) {
            return "Không thể chuyển tòa nhà sang trạng thái {$statusLabel} khi còn phòng đang có khách ở.";
        }

        if (Contract::query()
            ->whereIn('status', Contract::RESERVED_STATUSES)
            ->whereHas('room', fn (Builder $query): Builder => $query->where('building_id', $building->id))
            ->exists()) {
            return "Không thể chuyển tòa nhà sang trạng thái {$statusLabel} khi còn hợp đồng chờ ký hoặc đang hiệu lực.";
        }

        if (RoomMovement::query()
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->where(function (Builder $query) use ($building): void {
                $query->whereHas('fromRoom', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $building->id))
                    ->orWhereHas('toRoom', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $building->id));
            })
            ->exists()) {
            return "Không thể chuyển tòa nhà sang trạng thái {$statusLabel} khi còn lịch chuyển/trả phòng chờ xử lý.";
        }

        return null;
    }

    public static function roomRentableBlockReason(Room $room): ?string
    {
        if ((int) $room->status !== Room::STATUS_ACTIVE) {
            return 'Chỉ có thể lập hợp đồng cho phòng đang hoạt động.';
        }

        if (! $room->relationLoaded('building')) {
            $room->load('building:id,status');
        }

        if (! $room->building || (int) $room->building->status !== Building::STATUS_ACTIVE) {
            return 'Không thể lập hợp đồng vì tòa nhà đã ngừng hoạt động hoặc đang bảo trì.';
        }

        return null;
    }

    public static function destinationRoomBlockReason(Room $room): ?string
    {
        $reason = self::roomRentableBlockReason($room);

        if ($reason === null) {
            return null;
        }

        if ((int) $room->status !== Room::STATUS_ACTIVE) {
            return 'Phòng đích đang không ở trạng thái cho thuê được.';
        }

        return 'Không thể chuyển phòng vì tòa nhà đích đã ngừng hoạt động hoặc đang bảo trì.';
    }

    public static function tenantCreationBlockReason(Building $building): ?string
    {
        if ((int) $building->status !== Building::STATUS_ACTIVE) {
            return 'Không thể tạo khách thuê cho tòa nhà đã ngừng hoạt động hoặc đang bảo trì.';
        }

        return null;
    }

    public static function tenantStatusBlockReason(Tenant $tenant, int $nextStatus): ?string
    {
        if ($nextStatus !== Tenant::STATUS_STOPPED_RENTING) {
            return null;
        }

        if (self::tenantHasActiveStay($tenant)) {
            return 'Không thể chuyển khách thuê sang ngừng thuê khi còn hợp đồng đang hiệu lực hoặc còn đang ở phòng.';
        }

        return null;
    }

    public static function tenantGenderBlockReason(Tenant $tenant, ?int $nextGender): ?string
    {
        if ($nextGender === null) {
            return 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.';
        }

        $building = self::activeStayBuilding($tenant);

        if (! $building && $tenant->building_id) {
            $building = Building::query()->select(['id', 'gender_policy'])->find($tenant->building_id);
        }

        if ($building && ! $building->allowsTenantGender($nextGender)) {
            return 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.';
        }

        return null;
    }

    public static function vehicleMutationBlockReason(Vehicle $vehicle): ?string
    {
        if (ContractVehicle::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('is_active', true)
            ->whereHas('contract', fn (Builder $query): Builder => $query->whereIn('status', Contract::RESERVED_STATUSES))
            ->exists()) {
            return 'Không thể cập nhật phương tiện đang được dùng trong hợp đồng chờ ký hoặc đang hiệu lực.';
        }

        return null;
    }

    public static function meterCreationBlockReason(Room $room, Service $service): ?string
    {
        $reason = self::roomRentableBlockReason($room);
        if ($reason !== null) {
            return (int) $room->status !== Room::STATUS_ACTIVE
                ? 'Không thể tạo đồng hồ cho phòng đang bảo trì hoặc ngừng hoạt động.'
                : 'Không thể tạo đồng hồ vì tòa nhà đã ngừng hoạt động hoặc đang bảo trì.';
        }

        if (! (bool) $service->is_active) {
            return 'Không thể tạo đồng hồ cho dịch vụ đã ngừng hoạt động.';
        }

        return null;
    }

    public static function meterReadingBlockReason(MeterDevice $meter, ?MeterReading $existingReading = null): ?string
    {
        if ((int) $meter->status !== MeterDevice::STATUS_ACTIVE) {
            return 'Chỉ có thể chốt chỉ số cho đồng hồ đang sử dụng.';
        }

        if (! $meter->relationLoaded('room.building')) {
            $meter->load('room.building');
        }

        if ($meter->room && self::roomRentableBlockReason($meter->room) !== null) {
            return 'Chỉ có thể chốt chỉ số cho phòng và tòa nhà đang hoạt động.';
        }

        if ($existingReading && (int) $existingReading->status === MeterReading::STATUS_INVOICED) {
            return 'Không thể cập nhật chỉ số đã được lập hóa đơn.';
        }

        return null;
    }

    public static function roomServicePriceBlockReason(Room $room, ?CarbonInterface $targetDate = null): ?string
    {
        if ($targetDate && self::roomHasReservedContractCoveringPeriod($room, $targetDate)) {
            return null;
        }

        $reason = self::roomRentableBlockReason($room);

        if ($reason === null) {
            return null;
        }

        if ((int) $room->status !== Room::STATUS_ACTIVE) {
            return 'Không thể cập nhật giá dịch vụ cho phòng đang bảo trì hoặc ngừng hoạt động.';
        }

        return 'Không thể cập nhật giá dịch vụ vì tòa nhà đã ngừng hoạt động hoặc đang bảo trì.';
    }

    public static function invoiceIssuanceBlockReason(Contract $contract, CarbonInterface $periodStart): ?string
    {
        if (! $contract->relationLoaded('room.building')) {
            $contract->load('room.building');
        }

        $room = $contract->room;
        if (! $room) {
            return 'Không tìm thấy phòng của hợp đồng.';
        }

        $roomInactive = (int) $room->status !== Room::STATUS_ACTIVE;
        $buildingInactive = ! $room->building || (int) $room->building->status !== Building::STATUS_ACTIVE;

        if (! $roomInactive && ! $buildingInactive) {
            return null;
        }

        $contractEndDate = $contract->actual_end_date ?: $contract->end_date;
        if ($contractEndDate && $periodStart->copy()->startOfDay()->greaterThan($contractEndDate->copy()->endOfMonth()->startOfDay())) {
            return 'Không thể lập hóa đơn kỳ tương lai cho phòng hoặc tòa nhà đang ngừng hoạt động/bảo trì sau khi hợp đồng đã kết thúc.';
        }

        return null;
    }

    public static function activeStayBuilding(Tenant $tenant): ?Building
    {
        $row = ContractTenant::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', fn (Builder $query): Builder => $query->where('status', Contract::STATUS_ACTIVE))
            ->with('contract.room.building:id,gender_policy,status')
            ->first();

        return $row?->contract?->room?->building;
    }

    private static function tenantHasActiveStay(Tenant $tenant): bool
    {
        return ContractTenant::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', fn (Builder $query): Builder => $query->whereIn('status', Contract::RESERVED_STATUSES))
            ->exists();
    }

    private static function roomHasReservedContractCoveringPeriod(Room $room, CarbonInterface $targetDate): bool
    {
        $periodEnd = $targetDate->copy()->endOfMonth()->startOfDay()->toDateString();

        return Contract::query()
            ->where('room_id', $room->id)
            ->whereIn('status', Contract::RESERVED_STATUSES)
            ->where(function (Builder $query) use ($periodEnd): void {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $periodEnd);
            })
            ->where(function (Builder $query) use ($targetDate): void {
                $query->where(function (Builder $actualEndQuery) use ($targetDate): void {
                    $actualEndQuery->whereNotNull('actual_end_date')
                        ->whereDate('actual_end_date', '>=', $targetDate->copy()->startOfDay()->toDateString());
                })->orWhere(function (Builder $endDateQuery) use ($targetDate): void {
                    $endDateQuery->whereNull('actual_end_date')
                        ->where(function (Builder $query) use ($targetDate): void {
                            $query->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $targetDate->copy()->startOfDay()->toDateString());
                        });
                });
            })
            ->exists();
    }

    private static function roomStatusLabel(int $status): string
    {
        return $status === Room::STATUS_INACTIVE ? 'ngưng hoạt động' : 'bảo trì';
    }

    private static function buildingStatusLabel(int $status): string
    {
        return $status === Building::STATUS_INACTIVE ? 'ngừng hoạt động' : 'bảo trì';
    }
}
