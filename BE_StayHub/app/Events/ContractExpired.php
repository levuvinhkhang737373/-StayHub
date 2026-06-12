<?php

namespace App\Events;

use App\Models\Contract;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractExpired implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $contract;

    public function __construct(Contract $contract)
    {
        $contract->loadMissing(['room.building']);

        $this->contract = [
            'id' => $contract->id,
            'contract_code' => $contract->contract_code,
            'building_id' => $contract->room?->building_id,
            'building_name' => $contract->room?->building?->name,
            'room_number' => $contract->room?->room_number,
            'end_date' => $contract->end_date?->toDateString(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-building.' . ($this->contract['building_id'] ?? 0)),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'contract' => $this->contract,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ContractExpired';
    }
}
