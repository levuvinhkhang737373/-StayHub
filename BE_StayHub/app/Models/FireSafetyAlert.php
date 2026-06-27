<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FireSafetyAlert extends Model
{
    use HasFactory;

    public const RISK_SAFE = 1;
    public const RISK_WARNING = 2;
    public const RISK_DANGER = 3;
    public const RISK_CRITICAL = 4;

    public const RISK_LABELS = [
        self::RISK_SAFE => 'An toàn',
        self::RISK_WARNING => 'Cần chú ý',
        self::RISK_DANGER => 'Nguy hiểm',
        self::RISK_CRITICAL => 'Báo động đỏ',
    ];

    public const STATUS_OPEN = 1;
    public const STATUS_ACKNOWLEDGED = 2;
    public const STATUS_RESOLVED = 3;
    public const STATUS_FALSE_ALARM = 4;

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Đang báo động',
        self::STATUS_ACKNOWLEDGED => 'Đã xác nhận',
        self::STATUS_RESOLVED => 'Đã xử lý',
        self::STATUS_FALSE_ALARM => 'Báo giả',
    ];

    protected $fillable = [
        'security_camera_id',
        'building_id',
        'source_label',
        'risk_level',
        'detected_fire',
        'detected_smoke',
        'detected_smoking',
        'confidence',
        'snapshot_path',
        'ai_summary',
        'raw_ai_payload',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'security_camera_id' => 'integer',
            'building_id' => 'integer',
            'risk_level' => 'integer',
            'detected_fire' => 'boolean',
            'detected_smoke' => 'boolean',
            'detected_smoking' => 'boolean',
            'confidence' => 'decimal:4',
            'raw_ai_payload' => 'array',
            'status' => 'integer',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function securityCamera(): BelongsTo
    {
        return $this->belongsTo(SecurityCamera::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'acknowledged_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }
}
