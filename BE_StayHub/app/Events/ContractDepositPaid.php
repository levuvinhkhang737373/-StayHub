<?php

namespace App\Events;

use App\Models\Contract;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractDepositPaid implements ShouldBroadcast
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
            'room_number' => $contract->room?->room_number,
            'deposit_amount' => $contract->deposit_amount,
            'is_deposit_paid' => (bool) $contract->is_deposit_paid,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-maintenance'),
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
        return 'ContractDepositPaid';
    }
}
