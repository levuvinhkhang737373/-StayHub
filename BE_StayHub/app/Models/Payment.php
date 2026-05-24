<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    protected $fillable = ['payment_code', 'invoice_id', 'amount', 'payment_date', 'payment_method', 'transaction_reference', 'status', 'proof_image', 'note', 'collected_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2',
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
}
