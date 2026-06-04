<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;

class Tenant extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Searchable;

    public const SEARCH_INDEX = 'tenants';

    public const GENDER_MALE = 1;
    public const GENDER_FEMALE = 2;

    public const GENDER_LABELS = [
        self::GENDER_MALE => 'Nam',
        self::GENDER_FEMALE => 'Nữ',
    ];

    public const STATUS_RENTING = 1;
    public const STATUS_STOPPED_RENTING = 2;

    public const STATUS_LABELS = [
        self::STATUS_RENTING => 'Đang thuê',
        self::STATUS_STOPPED_RENTING => 'Ngừng thuê',
    ];

    public const IDENTITY_TYPE_CCCD = 1;
    public const IDENTITY_TYPE_CMND = 2;
    public const IDENTITY_TYPE_PASSPORT = 3;

    public const IDENTITY_TYPE_LABELS = [
        self::IDENTITY_TYPE_CCCD => 'CCCD',
        self::IDENTITY_TYPE_CMND => 'CMND',
        self::IDENTITY_TYPE_PASSPORT => 'Hộ chiếu',
    ];

    protected $fillable = ['created_by', 'room_id', 'full_name', 'gender', 'date_of_birth', 'phone', 'email', 'username', 'password', 'permanent_address', 'current_address', 'avatar_url', 'status', 'identity_type', 'identity_number', 'front_image_url', 'back_image_url'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'gender' => 'integer',
            'date_of_birth' => 'date',
            'status' => 'integer',
            'identity_type' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function searchableAs(): string
    {
        return self::SEARCH_INDEX;
    }

    /**
     * Dữ liệu đồng bộ sang Meilisearch, không đưa mật khẩu/token/ảnh giấy tờ nhạy cảm vào index.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'phone' => $this->phone,
            'email' => $this->email,
            'gender' => $this->gender === null ? null : (int) $this->gender,
            'status' => $this->status === null ? null : (int) $this->status,
            'identity_type' => $this->identity_type === null ? null : (int) $this->identity_type,
            'identity_number' => $this->identity_number,
            'permanent_address' => $this->permanent_address,
            'current_address' => $this->current_address,
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'created_at' => optional($this->created_at)->timestamp,
            'updated_at' => optional($this->updated_at)->timestamp,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function representedContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'representative_tenant_id');
    }

    public function contractTenants(): HasMany
    {
        return $this->hasMany(ContractTenant::class);
    }

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_tenants')->withPivot(['join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_representative', 'is_staying', 'created_by'])->withTimestamps();
    }

    public function roomMovements(): HasMany
    {
        return $this->hasMany(RoomMovement::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function maintenanceFeedbacks(): HasMany
    {
        return $this->hasMany(MaintenanceFeedback::class);
    }

    public function notificationReads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function readNotifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'notification_reads')->withPivot('read_at');
    }
}
