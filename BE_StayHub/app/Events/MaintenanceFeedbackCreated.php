<?php

namespace App\Events;

use App\Models\MaintenanceFeedback;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaintenanceFeedbackCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $feedback;

    public function __construct(MaintenanceFeedback $feedback)
    {
        $this->feedback = $feedback;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-maintenance'),
        ];
    }

    public function broadcastWith(): array
    {
        $this->feedback->load(['tenant', 'maintenanceRequest.room.building']);

        return [
            'feedback' => [
                'id' => $this->feedback->id,
                'maintenance_request_id' => $this->feedback->maintenance_request_id,
                'request_code' => $this->feedback->maintenanceRequest?->request_code,
                'room_number' => $this->feedback->maintenanceRequest?->room?->room_number,
                'building_id' => $this->feedback->maintenanceRequest?->room?->building_id,
                'tenant_name' => $this->feedback->tenant?->full_name,
                'rating' => $this->feedback->rating,
                'comment' => $this->feedback->comment,
            ]
        ];
    }

    public function broadcastAs(): string
    {
        return 'MaintenanceFeedbackCreated';
    }
}
