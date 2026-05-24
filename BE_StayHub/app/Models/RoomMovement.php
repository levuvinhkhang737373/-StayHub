<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomMovement extends Model
{
    use HasFactory;

    public const MOVEMENT_TYPE_CHECKOUT = 1;
    public const MOVEMENT_TYPE_TRANSFER = 2;

    public const MOVEMENT_TYPE_LABELS = [
        self::MOVEMENT_TYPE_CHECKOUT => 'Trả phòng',
        self::MOVEMENT_TYPE_TRANSFER => 'Chuyển phòng',
    ];

    protected $fillable = ['tenant_id', 'contract_id', 'from_room_id', 'to_room_id', 'movement_type', 'movement_date', 'old_room_final_amount', 'transfer_fee', 'deposit_transfer_amount', 'deposit_refund_amount', 'deduction_amount', 'final_electric_reading', 'final_water_reading', 'note', 'created_by'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['movement_type' => 'integer',
            'movement_date' => 'datetime', 'old_room_final_amount' => 'decimal:2', 'transfer_fee' => 'decimal:2', 'deposit_transfer_amount' => 'decimal:2', 'deposit_refund_amount' => 'decimal:2', 'deduction_amount' => 'decimal:2', 'final_electric_reading' => 'decimal:2', 'final_water_reading' => 'decimal:2'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
