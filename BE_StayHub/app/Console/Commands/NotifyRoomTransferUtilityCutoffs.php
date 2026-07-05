<?php

namespace App\Console\Commands;

use App\Events\NotificationSent;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\Room;
use App\Models\RoomMovement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class NotifyRoomTransferUtilityCutoffs extends Command
{
    protected $signature = 'room-transfers:notify-utility-cutoffs {--date=}';

    protected $description = 'Nhắc admin chốt điện nước và lập hóa đơn cuối cho hợp đồng cũ trước ngày chuyển phòng.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now('Asia/Ho_Chi_Minh')->startOfDay();

        $notifications = $this->createNotificationsForDate($date);

        $notifications->each(fn (Notification $notification): mixed => event(new NotificationSent($notification)));

        $this->info('Đã gửi '.$notifications->count().' thông báo chốt điện nước chuyển phòng.');

        return self::SUCCESS;
    }

    public function createNotificationsForDate(Carbon $date): Collection
    {
        $movementDate = $date->copy()->addDay();

        return RoomMovement::query()
            ->with(['tenant:id,full_name', 'sourceContract.contractTenants', 'fromRoom'])
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->whereDate('movement_date', $movementDate->toDateString())
            ->whereNotNull('transfer_code')
            ->get()
            ->groupBy('transfer_code')
            ->map(fn (Collection $movements): ?Notification => $this->createNotification($movements, $date, $movementDate))
            ->filter()
            ->values();
    }

    private function createNotification(Collection $movements, Carbon $cutoffDate, Carbon $movementDate): ?Notification
    {
        $movement = $movements->first();
        $sourceContract = $movement?->sourceContract;
        $fromRoom = $movement?->fromRoom;

        if (! $sourceContract || ! $fromRoom) {
            return null;
        }

        $actionUrl = $this->actionUrl($movement, $sourceContract, $fromRoom, $movementDate, $cutoffDate);

        return Notification::query()->firstOrCreate(
            [
                'title' => 'Cần chốt điện nước chuyển phòng',
                'action_url' => $actionUrl,
            ],
            [
                'content' => "Phòng {$fromRoom->room_number} có lịch chuyển phòng {$movement->transfer_code} ngày {$movementDate->format('d/m/Y')}. Vui lòng nhập chỉ số điện/nước đến ngày {$cutoffDate->format('d/m/Y')} và lập hóa đơn cuối cho hợp đồng cũ {$sourceContract->contract_code}.",
                'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                'target_type' => Notification::TARGET_TYPE_ADMIN,
                'building_id' => $fromRoom->building_id,
                'room_id' => $fromRoom->id,
                'tenant_id' => null,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $movement->created_by,
            ]
        );
    }

    private function actionUrl(RoomMovement $movement, Contract $sourceContract, Room $fromRoom, Carbon $movementDate, Carbon $cutoffDate): string
    {
        return '/admin/meter-readings?'.http_build_query([
            'building_id' => $fromRoom->building_id,
            'billing_month' => $cutoffDate->month,
            'billing_year' => $cutoffDate->year,
            'room_id' => $fromRoom->id,
            'contract_id' => $sourceContract->id,
            'cutoff_date' => $cutoffDate->toDateString(),
            'transfer_code' => $movement->transfer_code,
        ]);
    }
}
