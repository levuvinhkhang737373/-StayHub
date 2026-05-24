<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;


    public const PAYMENT_METHOD_CASH = 1;
    public const PAYMENT_METHOD_BANK_TRANSFER = 2;

    public const STATUS_RECORDED = 1;
    public const STATUS_CANCELLED = 2;

    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_METHOD_CASH => 'Tiền mặt',
        self::PAYMENT_METHOD_BANK_TRANSFER => 'Chuyển khoản',
    ];

    public const STATUS_LABELS = [
        self::STATUS_RECORDED => 'Đã ghi nhận',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected $fillable = ['expense_code', 'building_id', 'room_id', 'expense_category_id', 'title', 'amount', 'expense_date', 'receipt_images', 'payment_method', 'note', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'receipt_images' => 'array',
            'payment_method' => 'integer',
            'status' => 'integer',
            'expense_date' => 'date',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
