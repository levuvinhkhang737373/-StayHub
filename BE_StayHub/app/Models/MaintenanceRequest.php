<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceRequest extends Model
{
    use HasFactory;


    public const STATUS_CREATED = 1;
    public const STATUS_RECEIVED = 2;
    public const STATUS_PROCESSING = 3;
    public const STATUS_COMPLETED = 4;
    public const STATUS_CANCELLED = 5;

    public const STATUS_LABELS = [
        self::STATUS_CREATED => 'Mới tạo',
        self::STATUS_RECEIVED => 'Đã tiếp nhận',
        self::STATUS_PROCESSING => 'Đang xử lý',
        self::STATUS_COMPLETED => 'Đã hoàn thành',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected $fillable = ['request_code', 'tenant_id', 'room_id', 'title', 'description', 'status', 'images', 'assigned_to', 'received_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'images' => 'array',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(MaintenanceFeedback::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceRequestLog::class);
    }
}
