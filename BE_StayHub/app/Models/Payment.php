<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;


    public const PAYMENT_METHOD_CASH = 1;
    public const PAYMENT_METHOD_BANK_TRANSFER = 2;

    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_METHOD_CASH => 'Tiền mặt',
        self::PAYMENT_METHOD_BANK_TRANSFER => 'Chuyển khoản',
    ];

    public const STATUS_PENDING_CONFIRMATION = 1;
    public const STATUS_CONFIRMED = 2;
    public const STATUS_CANCELLED = 3;

    public const STATUS_LABELS = [
        self::STATUS_PENDING_CONFIRMATION => 'Chờ xác nhận',
        self::STATUS_CONFIRMED => 'Đã xác nhận',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected $fillable = ['payment_code', 'invoice_id', 'allocated_from_payment_id', 'invoice_debt_rollover_id', 'is_internal_allocation', 'amount', 'payment_date', 'payment_method', 'transaction_reference', 'status', 'proof_image', 'note', 'collected_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2',
            'allocated_from_payment_id' => 'integer',
            'invoice_debt_rollover_id' => 'integer',
            'is_internal_allocation' => 'boolean',
            'payment_method' => 'integer',
            'status' => 'integer', 'payment_date' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'collected_by');
    }

    public function scopeRealMoney(Builder $query): Builder
    {
        return $query->where(function (Builder $moneyQuery): void {
            $moneyQuery->where('is_internal_allocation', false)
                ->orWhereNull('is_internal_allocation');
        });
    }
}
