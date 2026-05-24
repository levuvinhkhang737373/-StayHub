<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractVehicle extends Model
{
    use HasFactory;


    public const CHARGE_POLICY_MONTHLY = 1;
    public const CHARGE_POLICY_DAILY = 2;
    public const CHARGE_POLICY_FREE = 3;

    public const CHARGE_POLICY_LABELS = [
        self::CHARGE_POLICY_MONTHLY => 'Tính theo tháng',
        self::CHARGE_POLICY_DAILY => 'Tính theo ngày',
        self::CHARGE_POLICY_FREE => 'Miễn phí',
    ];

    public const ACTIVE = true;
    public const INACTIVE = false;

    public const ACTIVE_LABELS = [
        self::ACTIVE => 'Còn tính phí',
        self::INACTIVE => 'Hết tính phí',
    ];

    protected $fillable = ['contract_id', 'vehicle_id', 'started_at', 'ended_at', 'billing_start_date', 'billing_end_date', 'monthly_fee', 'charge_policy', 'is_active'];

    protected function casts(): array
    {
        return ['started_at' => 'date', 'ended_at' => 'date', 'billing_start_date' => 'date', 'billing_end_date' => 'date', 'monthly_fee' => 'decimal:2', 'charge_policy' => 'integer', 'is_active' => 'boolean'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
