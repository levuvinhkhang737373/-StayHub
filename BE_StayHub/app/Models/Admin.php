<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


    public const ROLE_BUILDING_MANAGER = 1;
    public const ROLE_SUPER_ADMIN = 2;

    public const ROLE_LABELS = [
        self::ROLE_BUILDING_MANAGER => 'Quản lý tòa nhà',
        self::ROLE_SUPER_ADMIN => 'Quản trị tổng',
    ];

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Hoạt động',
        self::STATUS_INACTIVE => 'Ngừng hoạt động',
    ];

    public const GENDER_MALE = 1;
    public const GENDER_FEMALE = 2;

    public const GENDER_LABELS = [
        self::GENDER_MALE => 'Nam',
        self::GENDER_FEMALE => 'Nữ',
    ];

    protected $fillable = ['username', 'full_name', 'email', 'phone', 'password', 'role', 'avatar_url', 'image_path_faceid', 'created_faceid_at', 'updated_faceid_at', 'status', 'gender', 'date_of_birth', 'address'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'integer',
            'role' => 'integer',
            'gender' => 'integer',
            'created_faceid_at' => 'datetime',
            'updated_faceid_at' => 'datetime',
            'date_of_birth' => 'date',
        ];
    }

    public static function isSupportedRole(mixed $role): bool
    {
        return array_key_exists((int) $role, self::ROLE_LABELS);
    }

    public function createdRegions(): HasMany
    {
        return $this->hasMany(Region::class, 'created_by');
    }

    public function createdTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'created_by');
    }

    public function managedBuildings(): HasMany
    {
        return $this->hasMany(Building::class, 'manager_admin_id');
    }

    public function createdBuildings(): HasMany
    {
        return $this->hasMany(Building::class, 'created_by');
    }

    public function uploadedBuildingImages(): HasMany
    {
        return $this->hasMany(BuildingImage::class, 'uploaded_by');
    }

    public function createdRoomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class, 'created_by');
    }

    public function createdRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'created_by');
    }

    public function uploadedRoomImages(): HasMany
    {
        return $this->hasMany(RoomImage::class, 'uploaded_by');
    }

    public function createdAssetTemplates(): HasMany
    {
        return $this->hasMany(AssetTemplate::class, 'created_by');
    }

    public function createdServices(): HasMany
    {
        return $this->hasMany(Service::class, 'created_by');
    }

    public function createdContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'created_by');
    }

    public function createdContractTenants(): HasMany
    {
        return $this->hasMany(ContractTenant::class, 'created_by');
    }

    public function createdRoomMovements(): HasMany
    {
        return $this->hasMany(RoomMovement::class, 'created_by');
    }

    public function depositTransactions(): HasMany
    {
        return $this->hasMany(ContractDepositTransaction::class, 'created_by');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function collectedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'collected_by');
    }

    public function assignedMaintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'assigned_to');
    }

    public function maintenanceRequestLogs(): HasMany
    {
        return $this->hasMany(MaintenanceRequestLog::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'created_by');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'created_by');
    }

    public function createdExpenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AdminLog::class);
    }
}
