<?php

namespace App\Events;

use App\Http\Resources\Tenant\NotificationResource;
use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->notification->target_type === Notification::TARGET_TYPE_TENANT) {
            $channels[] = new PrivateChannel('tenant.' . $this->notification->tenant_id);
        } elseif ($this->notification->target_type === Notification::TARGET_TYPE_ROOM) {
            // Tìm tất cả khách thuê thuộc phòng đó
            $tenantIds = \App\Models\Tenant::query()
                ->where('room_id', $this->notification->room_id)
                ->pluck('id');
            foreach ($tenantIds as $id) {
                $channels[] = new PrivateChannel('tenant.' . $id);
            }
        } elseif ($this->notification->target_type === Notification::TARGET_TYPE_BUILDING) {
            // Tìm tất cả khách thuê thuộc tòa nhà đó
            $tenantIds = \App\Models\Tenant::query()
                ->whereHas('room', function ($q) {
                    $q->where('building_id', $this->notification->building_id);
                })
                ->pluck('id');
            foreach ($tenantIds as $id) {
                $channels[] = new PrivateChannel('tenant.' . $id);
            }
        } else {
            // TARGET_TYPE_ALL
            // Tìm tất cả khách thuê trong hệ thống
            $tenantIds = \App\Models\Tenant::query()->pluck('id');
            foreach ($tenantIds as $id) {
                $channels[] = new PrivateChannel('tenant.' . $id);
            }
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => (new NotificationResource($this->notification->load(['building', 'room', 'tenant', 'creator'])))->resolve(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationSent';
    }
}
