<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterReading extends Model
{
    use HasFactory;


    public const STATUS_DRAFT = 1;
    public const STATUS_CONFIRMED = 2;
    public const STATUS_INVOICED = 3;

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_CONFIRMED => 'Đã xác nhận',
        self::STATUS_INVOICED => 'Đã lập hóa đơn',
    ];

    protected $fillable = ['meter_device_id', 'billing_month', 'billing_year', 'previous_reading', 'current_reading', 'consumption', 'reading_date', 'status', 'image_path', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['billing_month' => 'integer', 'billing_year' => 'integer', 'previous_reading' => 'decimal:2', 'current_reading' => 'decimal:2', 'consumption' => 'decimal:2', 'reading_date' => 'date', 'status' => 'integer'];
    }

    public function meterDevice(): BelongsTo
    {
        return $this->belongsTo(MeterDevice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
