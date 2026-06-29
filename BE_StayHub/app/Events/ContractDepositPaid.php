<?php

namespace App\Events;

use App\Models\Contract;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractDepositPaid implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $contract;
    protected $tenantIds = [];

    public function __construct(Contract $contract)
    {
        $contract->loadMissing(['room.building', 'tenants']);
        $this->tenantIds = $contract->tenants->pluck('id')->toArray();

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
        $channels = [
            new PrivateChannel('admin-maintenance'),
        ];

        foreach ($this->tenantIds as $tenantId) {
            $channels[] = new PrivateChannel('tenant.' . $tenantId);
        }

        return $channels;
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
