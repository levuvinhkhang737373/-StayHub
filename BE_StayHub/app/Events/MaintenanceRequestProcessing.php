<?php

namespace App\Events;

use App\Http\Resources\Admin\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaintenanceRequestProcessing implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $maintenanceRequest;

    public function __construct(MaintenanceRequest $maintenanceRequest)
    {
        $this->maintenanceRequest = $maintenanceRequest;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-maintenance'),
            new PrivateChannel('tenant.' . $this->maintenanceRequest->tenant_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'request' => (new MaintenanceRequestResource($this->maintenanceRequest->load(['tenant', 'room.building', 'assignee'])))->resolve(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MaintenanceRequestProcessing';
    }
}
