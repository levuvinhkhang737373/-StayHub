<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;


    public const ITEM_TYPE_ROOM = 1;
    public const ITEM_TYPE_ELECTRIC = 2;
    public const ITEM_TYPE_WATER = 3;
    public const ITEM_TYPE_INTERNET = 4;
    public const ITEM_TYPE_TRASH = 5;
    public const ITEM_TYPE_PARKING = 6;
    public const ITEM_TYPE_SURCHARGE = 7;
    public const ITEM_TYPE_DISCOUNT = 8;
    public const ITEM_TYPE_OLD_DEBT = 9;
    public const ITEM_TYPE_ADJUST_INCREASE = 10;
    public const ITEM_TYPE_ADJUST_DECREASE = 11;

    public const ITEM_TYPE_LABELS = [
        self::ITEM_TYPE_ROOM => 'Tiền phòng',
        self::ITEM_TYPE_ELECTRIC => 'Tiền điện',
        self::ITEM_TYPE_WATER => 'Tiền nước',
        self::ITEM_TYPE_INTERNET => 'Internet',
        self::ITEM_TYPE_TRASH => 'Rác',
        self::ITEM_TYPE_PARKING => 'Gửi xe',
        self::ITEM_TYPE_SURCHARGE => 'Phụ thu',
        self::ITEM_TYPE_DISCOUNT => 'Giảm trừ',
        self::ITEM_TYPE_OLD_DEBT => 'Nợ cũ',
        self::ITEM_TYPE_ADJUST_INCREASE => 'Điều chỉnh tăng',
        self::ITEM_TYPE_ADJUST_DECREASE => 'Điều chỉnh giảm',
    ];

    protected $fillable = ['invoice_id', 'service_id', 'meter_reading_id', 'item_type', 'description', 'quantity', 'unit_price', 'amount'];

    protected function casts(): array
    {
        return ['item_type' => 'integer',
            'quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
    }
}
