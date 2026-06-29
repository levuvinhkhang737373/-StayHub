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

    public const STATUS_PENDING = 1;
    public const STATUS_EXECUTED = 2;
    public const STATUS_BLOCKED = 3;
    public const STATUS_CANCELLED = 4;

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Chờ xử lý',
        self::STATUS_EXECUTED => 'Đã chuyển',
        self::STATUS_BLOCKED => 'Đang bị chặn',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    public const SETTLEMENT_PAYMENT_STATUS_PENDING = 1;
    public const SETTLEMENT_PAYMENT_STATUS_PAID = 2;
    public const SETTLEMENT_PAYMENT_STATUS_PARTIAL = 3;

    public const SETTLEMENT_PAYMENT_STATUS_LABELS = [
        self::SETTLEMENT_PAYMENT_STATUS_PENDING => 'Chờ thanh toán',
        self::SETTLEMENT_PAYMENT_STATUS_PAID => 'Đã thanh toán',
        self::SETTLEMENT_PAYMENT_STATUS_PARTIAL => 'Thanh toán một phần',
    ];

    public const MOVEMENT_TYPE_LABELS = [
        self::MOVEMENT_TYPE_CHECKOUT => 'Trả phòng',
        self::MOVEMENT_TYPE_TRANSFER => 'Chuyển phòng',
    ];

    protected $fillable = [ 'tenant_id', 'contract_id', 'source_contract_id', 'destination_contract_id', 'from_room_id', 'to_room_id', 'movement_type', 'status', 'movement_date', 'old_room_final_amount', 'transfer_fee', 'deposit_transfer_amount', 'deposit_refund_amount', 'deduction_amount', 'manual_refund_amount', 'deposit_due_amount', 'extra_charge_amount', 'settlement_due_amount', 'settlement_paid_amount', 'settlement_payment_status', 'settlement_payment_references', 'final_electric_reading', 'final_water_reading', 'note', 'scheduled_payload', 'executed_at', 'failure_reason', 'created_by'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'source_contract_id' => 'integer',
            'destination_contract_id' => 'integer',
            'movement_type' => 'integer',
            'status' => 'integer',
            'movement_date' => 'datetime',
            'old_room_final_amount' => 'decimal:2',
            'transfer_fee' => 'decimal:2',
            'deposit_transfer_amount' => 'decimal:2',
            'deposit_refund_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'manual_refund_amount' => 'decimal:2',
            'deposit_due_amount' => 'decimal:2',
            'extra_charge_amount' => 'decimal:2',
            'settlement_due_amount' => 'decimal:2',
            'settlement_paid_amount' => 'decimal:2',
            'settlement_payment_status' => 'integer',
            'settlement_payment_references' => 'array',
            'final_electric_reading' => 'decimal:2',
            'final_water_reading' => 'decimal:2',
            'scheduled_payload' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function sourceContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'source_contract_id');
    }

    public function destinationContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'destination_contract_id');
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
