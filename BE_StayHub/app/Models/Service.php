<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, HasUniqueSlug;


    public const CHARGE_METHOD_BY_METER = 1;
    public const CHARGE_METHOD_BY_PERSON = 2;
    public const CHARGE_METHOD_BY_ROOM = 3;
    public const CHARGE_METHOD_BY_VEHICLE = 4;
    public const CHARGE_METHOD_FIXED = 5;

    public const CHARGE_METHOD_LABELS = [
        self::CHARGE_METHOD_BY_METER => 'Theo chỉ số',
        self::CHARGE_METHOD_BY_PERSON => 'Theo người',
        self::CHARGE_METHOD_BY_ROOM => 'Theo phòng',
        self::CHARGE_METHOD_BY_VEHICLE => 'Theo xe',
        self::CHARGE_METHOD_FIXED => 'Cố định',
    ];

    public const REQUIRED_NO = false;
    public const REQUIRED_YES = true;

    public const REQUIRED_LABELS = [
        self::REQUIRED_NO => 'Không bắt buộc',
        self::REQUIRED_YES => 'Bắt buộc',
    ];

    public const ACTIVE = true;
    public const INACTIVE = false;

    public const ACTIVE_LABELS = [
        self::ACTIVE => 'Hoạt động',
        self::INACTIVE => 'Ngừng hoạt động',
    ];

    public const UTILITY_SLUGS = ['electric', 'water', 'electricity', 'dien', 'nuoc', 'dien-sinh-hoat', 'nuoc-sinh-hoat'];

    protected $fillable = ['name', 'slug', 'charge_method', 'unit_name', 'is_required', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['charge_method' => 'integer', 'is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function meterDevices(): HasMany
    {
        return $this->hasMany(MeterDevice::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isMeteredUtility(): bool
    {
        return (int) $this->charge_method === self::CHARGE_METHOD_BY_METER
            || in_array((string) $this->slug, self::UTILITY_SLUGS, true);
    }
}
