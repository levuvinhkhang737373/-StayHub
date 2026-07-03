<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SecurityCamera extends Model
{
    use HasFactory;

    public const SCAN_STATUS_SAFE = 'safe';
    public const SCAN_STATUS_ALERT = 'alert';
    public const SCAN_STATUS_ERROR = 'error';

    public const SOURCE_TYPE_SNAPSHOT = 1;
    public const SOURCE_TYPE_MJPEG = 2;
    public const SOURCE_TYPE_RTSP = 3;

    public const SOURCE_TYPE_LABELS = [
        self::SOURCE_TYPE_SNAPSHOT => 'Ảnh chụp định kỳ',
        self::SOURCE_TYPE_MJPEG => 'Luồng MJPEG/HTTP',
        self::SOURCE_TYPE_RTSP => 'Máy quay RTSP',
    ];

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Đang hoạt động',
        self::STATUS_INACTIVE => 'Tạm tắt',
    ];

    protected $fillable = [
        'building_id',
        'name',
        'location',
        'source_type',
        'stream_url',
        'username',
        'password',
        'is_ai_enabled',
        'frame_interval_seconds',
        'frames_per_batch',
        'alert_cooldown_seconds',
        'status',
        'monitoring_token',
        'monitoring_started_at',
        'monitoring_stopped_at',
        'last_scanned_at',
        'next_scan_at',
        'last_scan_status',
        'last_scan_message',
        'monitoring_error_count',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'building_id' => 'integer',
            'source_type' => 'integer',
            'password' => 'encrypted',
            'is_ai_enabled' => 'boolean',
            'frame_interval_seconds' => 'integer',
            'frames_per_batch' => 'integer',
            'alert_cooldown_seconds' => 'integer',
            'status' => 'integer',
            'monitoring_started_at' => 'datetime',
            'monitoring_stopped_at' => 'datetime',
            'last_scanned_at' => 'datetime',
            'next_scan_at' => 'datetime',
            'monitoring_error_count' => 'integer',
        ];
    }

    public function isMonitoringActive(): bool
    {
        return (bool) $this->is_ai_enabled && (int) $this->status === self::STATUS_ACTIVE;
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(FireSafetyAlert::class);
    }

    public function latestAlert(): HasOne
    {
        return $this->hasOne(FireSafetyAlert::class)->latestOfMany();
    }
}
