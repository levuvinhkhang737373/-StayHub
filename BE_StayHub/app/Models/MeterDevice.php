<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterDevice extends Model
{
    use HasFactory;


    public const METER_TYPE_ELECTRIC = 1;
    public const METER_TYPE_WATER = 2;

    public const METER_TYPE_LABELS = [
        self::METER_TYPE_ELECTRIC => 'Điện',
        self::METER_TYPE_WATER => 'Nước',
    ];

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;
    public const STATUS_REPLACED = 3;
    public const STATUS_BROKEN = 4;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Đang sử dụng',
        self::STATUS_INACTIVE => 'Ngừng sử dụng',
        self::STATUS_REPLACED => 'Đã thay thế',
        self::STATUS_BROKEN => 'Bị hỏng',
    ];

    protected $fillable = ['room_id', 'service_id', 'meter_type', 'initial_reading', 'installed_at', 'replaced_by_meter_id', 'final_reading', 'status', 'image_path', 'note'];

    protected function casts(): array
    {
        return ['meter_type' => 'integer',
            'initial_reading' => 'decimal:2',
            'status' => 'integer', 'installed_at' => 'date', 'final_reading' => 'decimal:2'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function replacementMeter(): BelongsTo
    {
        return $this->belongsTo(MeterDevice::class, 'replaced_by_meter_id');
    }

    public function replacedMeters(): HasMany
    {
        return $this->hasMany(MeterDevice::class, 'replaced_by_meter_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }
}
