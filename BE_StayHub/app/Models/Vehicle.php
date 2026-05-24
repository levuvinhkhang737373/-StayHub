<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;


    public const VEHICLE_TYPE_MOTORBIKE = 1;
    public const VEHICLE_TYPE_BICYCLE = 2;
    public const VEHICLE_TYPE_CAR = 3;
    public const VEHICLE_TYPE_ELECTRIC = 4;

    public const VEHICLE_TYPE_LABELS = [
        self::VEHICLE_TYPE_MOTORBIKE => 'Xe máy',
        self::VEHICLE_TYPE_BICYCLE => 'Xe đạp',
        self::VEHICLE_TYPE_CAR => 'Ô tô',
        self::VEHICLE_TYPE_ELECTRIC => 'Xe điện',
    ];

    public const ACTIVE = true;
    public const INACTIVE = false;

    public const ACTIVE_LABELS = [
        self::ACTIVE => 'Còn sử dụng',
        self::INACTIVE => 'Hết sử dụng',
    ];

    protected $fillable = ['tenant_id', 'vehicle_type', 'license_plate', 'brand', 'color', 'is_active'];

    protected function casts(): array
    {
        return ['vehicle_type' => 'integer', 'is_active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contractVehicles(): HasMany
    {
        return $this->hasMany(ContractVehicle::class);
    }

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_vehicles')->withPivot(['started_at', 'ended_at', 'billing_start_date', 'billing_end_date', 'monthly_fee', 'charge_policy', 'is_active'])->withTimestamps();
    }
}
