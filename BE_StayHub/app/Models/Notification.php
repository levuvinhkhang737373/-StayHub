<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasFactory;


    public const NOTIFICATION_TYPE_MAINTENANCE = 1;
    public const NOTIFICATION_TYPE_INVOICE = 2;
    public const NOTIFICATION_TYPE_SYSTEM = 3;
    public const NOTIFICATION_TYPE_WARNING = 4;
    public const NOTIFICATION_TYPE_OTHER = 5;
    public const NOTIFICATION_TYPE_CHAT = 6;

    public const NOTIFICATION_TYPE_LABELS = [
        self::NOTIFICATION_TYPE_MAINTENANCE => 'Sửa chữa',
        self::NOTIFICATION_TYPE_INVOICE => 'Hóa đơn',
        self::NOTIFICATION_TYPE_SYSTEM => 'Hệ thống',
        self::NOTIFICATION_TYPE_WARNING => 'Cảnh báo',
        self::NOTIFICATION_TYPE_OTHER => 'Khác',
        self::NOTIFICATION_TYPE_CHAT => 'Tin nhắn',
    ];

    public const TARGET_TYPE_ALL = 1;
    public const TARGET_TYPE_BUILDING = 2;
    public const TARGET_TYPE_ROOM = 3;
    public const TARGET_TYPE_TENANT = 4;
    public const TARGET_TYPE_ADMIN = 5;

    public const TARGET_TYPE_LABELS = [
        self::TARGET_TYPE_ALL => 'Tất cả',
        self::TARGET_TYPE_BUILDING => 'Theo tòa nhà',
        self::TARGET_TYPE_ROOM => 'Theo phòng',
        self::TARGET_TYPE_TENANT => 'Theo khách thuê',
        self::TARGET_TYPE_ADMIN => 'Ban quản lý',
    ];

    public const STATUS_DRAFT = 1;
    public const STATUS_SENT = 2;
    public const STATUS_CANCELLED = 3;

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_SENT => 'Đã gửi',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected $fillable = ['title', 'content', 'notification_type', 'target_type', 'building_id', 'room_id', 'tenant_id', 'target_admin_id', 'published_at', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['notification_type' => 'integer', 'target_type' => 'integer', 'published_at' => 'datetime', 'status' => 'integer'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function targetAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'target_admin_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function readByTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'notification_reads')->withPivot('read_at');
    }
}
