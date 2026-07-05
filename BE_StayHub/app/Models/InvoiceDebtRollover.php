<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDebtRollover extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 1;
    public const STATUS_SETTLED = 2;
    public const STATUS_CANCELLED = 3;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Đang chuyển nợ',
        self::STATUS_SETTLED => 'Đã tất toán',
        self::STATUS_CANCELLED => 'Đã hủy chuyển nợ',
    ];

    protected $fillable = ['source_invoice_id', 'target_invoice_id', 'amount', 'settled_amount', 'status'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'status' => 'integer',
        ];
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    public function targetInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'target_invoice_id');
    }
}
