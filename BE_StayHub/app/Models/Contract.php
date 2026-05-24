<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;


    public const STATUS_DRAFT = 1;
    public const STATUS_ACTIVE = 2;
    public const STATUS_EXPIRED = 3;
    public const STATUS_LIQUIDATED = 4;
    public const STATUS_CANCELLED = 5;

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_ACTIVE => 'Đang hiệu lực',
        self::STATUS_EXPIRED => 'Hết hạn',
        self::STATUS_LIQUIDATED => 'Đã thanh lý',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected $fillable = ['contract_code', 'room_id', 'representative_tenant_id', 'start_date', 'end_date', 'actual_end_date', 'billing_cycle_day', 'room_price', 'deposit_amount', 'status', 'contract_files', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'actual_end_date' => 'date', 'billing_cycle_day' => 'integer', 'room_price' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'status' => 'integer', 'contract_files' => 'array'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function representativeTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'representative_tenant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function contractTenants(): HasMany
    {
        return $this->hasMany(ContractTenant::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'contract_tenants')->withPivot(['join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_representative', 'is_staying', 'created_by'])->withTimestamps();
    }

    public function roomMovements(): HasMany
    {
        return $this->hasMany(RoomMovement::class);
    }

    public function contractVehicles(): HasMany
    {
        return $this->hasMany(ContractVehicle::class);
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'contract_vehicles')->withPivot(['started_at', 'ended_at', 'billing_start_date', 'billing_end_date', 'monthly_fee', 'charge_policy', 'is_active'])->withTimestamps();
    }

    public function depositTransactions(): HasMany
    {
        return $this->hasMany(ContractDepositTransaction::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
