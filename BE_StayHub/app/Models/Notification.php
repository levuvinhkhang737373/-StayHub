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

    protected $fillable = ['title', 'content', 'action_url', 'notification_type', 'target_type', 'building_id', 'room_id', 'tenant_id', 'target_admin_id', 'published_at', 'status', 'created_by'];

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

    public function readByAdmins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'notification_reads')->withPivot('read_at');
    }

    public function actionUrl(): ?string
    {
        if (filled($this->action_url)) {
            return $this->action_url;
        }

        return match ((int) $this->notification_type) {
            self::NOTIFICATION_TYPE_MAINTENANCE => $this->maintenanceActionUrl(),
            self::NOTIFICATION_TYPE_INVOICE => $this->invoiceActionUrl(),
            self::NOTIFICATION_TYPE_CHAT => $this->chatActionUrl(),
            default => $this->systemActionUrl(),
        };
    }

    private function maintenanceActionUrl(): string
    {
        if (preg_match('/(SC-\d{6})/i', (string) $this->content, $matches)) {
            return '/admin/maintenance?request_code=' . urlencode($matches[1]);
        }

        return $this->relatedIdUrl('/admin/maintenance', $this->idFromContent('/y[eê]u c[ầa]u[^#A-Z0-9]*(?:#|m[ãa]\s*)?(\d+)/iu'));
    }

    private function invoiceActionUrl(): string
    {
        if (preg_match('/(INV-[A-Z0-9-]+)/i', (string) $this->content, $matches)) {
            return '/admin/invoices?invoice_code=' . urlencode($matches[1]);
        }

        return '/admin/invoices';
    }

    private function chatActionUrl(): string
    {
        return $this->tenant_id ? '/admin/chat?tenant_id=' . $this->tenant_id : '/admin/chat';
    }

    private function systemActionUrl(): ?string
    {
        if (preg_match('/(HD-[A-Z0-9-]+)/i', (string) $this->content, $matches)) {
            return '/admin/contracts?contract_code=' . urlencode($matches[1]);
        }

        if (preg_match('/(INV-[A-Z0-9-]+)/i', (string) $this->content, $matches)) {
            return '/admin/invoices?invoice_code=' . urlencode($matches[1]);
        }

        if (preg_match('/(SC-\d{6})/i', (string) $this->content, $matches)) {
            return '/admin/maintenance?request_code=' . urlencode($matches[1]);
        }

        if (preg_match('/(TRF-[A-Z0-9-]+)/i', (string) $this->content, $matches)) {
            return '/admin/room-movements?keyword=' . urlencode($matches[1]);
        }

        if (preg_match('/chuyển phòng/iu', (string) $this->title)) {
            return '/admin/room-movements';
        }

        if (preg_match('/hợp đồng/iu', (string) $this->title)) {
            return '/admin/contracts';
        }

        return null;
    }

    private function relatedIdUrl(string $path, ?int $id): string
    {
        return $id ? $path . '?id=' . $id : $path;
    }

    private function idFromContent(string $pattern): ?int
    {
        if (preg_match($pattern, (string) $this->content, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
