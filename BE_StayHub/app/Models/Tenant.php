<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Tenant extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


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

    protected $fillable = ['building_id', 'full_name', 'gender', 'date_of_birth', 'phone', 'email', 'username', 'password', 'permanent_address', 'current_address', 'avatar_url', 'status', 'identity_type', 'identity_number', 'front_image_url', 'back_image_url'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'building_id' => 'integer',
            'gender' => 'integer', 'date_of_birth' => 'date', 'status' => 'integer',
            'identity_type' => 'integer', 'password' => 'hashed',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
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
