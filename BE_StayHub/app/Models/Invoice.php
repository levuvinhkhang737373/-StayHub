<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;


    public const STATUS_DRAFT = 1;
    public const STATUS_UNPAID = 2;
    public const STATUS_PARTIALLY_PAID = 3;
    public const STATUS_PAID = 4;
    public const STATUS_OVERDUE = 5;
    public const STATUS_CANCELLED = 6;

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_UNPAID => 'Chưa thanh toán',
        self::STATUS_PARTIALLY_PAID => 'Thanh toán 1 phần',
        self::STATUS_PAID => 'Đã thanh toán',
        self::STATUS_OVERDUE => 'Quá hạn',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected $fillable = ['invoice_code', 'contract_id', 'room_id', 'billing_month', 'billing_year', 'period_start', 'period_end', 'previous_debt_amount', 'total_amount', 'paid_amount', 'remaining_amount', 'due_date', 'status', 'issued_at', 'created_by'];

    protected function casts(): array
    {
        return ['billing_month' => 'integer', 'billing_year' => 'integer', 'period_start' => 'date', 'period_end' => 'date', 'previous_debt_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'due_date' => 'date', 'status' => 'integer', 'issued_at' => 'datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
