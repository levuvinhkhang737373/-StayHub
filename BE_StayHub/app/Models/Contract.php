<?php

namespace App\Models;

use App\Events\NotificationSent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    public const STATUS_PENDING_SIGN = 0;
    public const STATUS_ACTIVE = 1;

    public const STATUS_EXPIRED = 2;

    public const STATUS_LIQUIDATED = 3;

    public const STATUS_CANCELLED = 4;

    public const STATUS_LABELS = [
        self::STATUS_PENDING_SIGN => 'Chờ ký',
        self::STATUS_ACTIVE => 'Đang hiệu lực',
        self::STATUS_EXPIRED => 'Hết hạn',
        self::STATUS_LIQUIDATED => 'Đã thanh lý',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    public const PAYMENT_STATUS_PENDING = 1;

    public const PAYMENT_STATUS_SUCCESS = 2;

    public const PAYMENT_STATUS_CANCELLED = 3;

    public const PAYMENT_STATUS_EXPIRED = 4;

    public const PAYMENT_STATUS_LABELS = [
        self::PAYMENT_STATUS_PENDING => 'Chờ thanh toán',
        self::PAYMENT_STATUS_SUCCESS => 'Đã thanh toán',
        self::PAYMENT_STATUS_CANCELLED => 'Đã hủy',
        self::PAYMENT_STATUS_EXPIRED => 'Hết hạn',
    ];

    protected $fillable = ['contract_code', 'room_id', 'start_date', 'end_date', 'actual_end_date', 'billing_cycle_day', 'room_price', 'deposit_amount', 'status', 'payment_status', 'contract_files', 'note', 'created_by', 'representative_tenant_id', 'parent_contract_id', 'renew_from_contract_id', 'tenant_signed_at', 'tenant_signature_url'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'actual_end_date' => 'date', 'billing_cycle_day' => 'integer', 'room_price' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'status' => 'integer', 'payment_status' => 'integer', 'contract_files' => 'array', 'representative_tenant_id' => 'integer', 'parent_contract_id' => 'integer', 'renew_from_contract_id' => 'integer', 'tenant_signed_at' => 'datetime'];
    }

    protected static function booted()
    {
        static::creating(function ($contract) {
            if ($contract->payment_status !== null) {
                return;
            }

            $required = (float) $contract->deposit_amount;
            if ($required <= 0) {
                $contract->payment_status = self::PAYMENT_STATUS_SUCCESS;
            } else {
                $contract->payment_status = self::PAYMENT_STATUS_PENDING;
            }
        });

        static::updated(function ($contract) {
            if ($contract->wasChanged('status') && (int) $contract->status === self::STATUS_EXPIRED) {
                $contract->loadMissing('room');

                // Lấy danh sách khách thuê đang ở của hợp đồng
                $activeTenants = $contract->contractTenants()
                    ->where('is_staying', true)
                    ->get();

                foreach ($activeTenants as $contractTenant) {
                    $tenantNotification = Notification::create([
                        'title' => 'Hợp đồng hết hạn',
                        'content' => "Hợp đồng {$contract->contract_code} của bạn tại phòng " . ($contract->room?->room_number ?? 'không rõ') . ' đã hết thời hạn.',
                        'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                        'target_type' => Notification::TARGET_TYPE_TENANT,
                        'building_id' => $contract->room?->building_id,
                        'room_id' => $contract->room_id,
                        'tenant_id' => $contractTenant->tenant_id,
                        'published_at' => now(),
                        'status' => Notification::STATUS_SENT,
                        'created_by' => null,
                    ]);

                    // Bắn realtime thông báo cho khách thuê qua Reverb
                    broadcast(new NotificationSent($tenantNotification));
                }
            }
        });
    }

    public function updatePaymentStatus(): void
    {
        $required = (float) $this->deposit_amount;
        if ($required <= 0) {
            $this->payment_status = self::PAYMENT_STATUS_SUCCESS;
        } else {
            $balance = (float) $this->deposit_balance;
            if ($balance >= $required) {
                $this->payment_status = self::PAYMENT_STATUS_SUCCESS;
            } else {
                if ($this->payment_status === self::PAYMENT_STATUS_SUCCESS) {
                    $this->payment_status = self::PAYMENT_STATUS_PENDING;
                }
            }
        }
        $this->save();
    }

    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'parent_contract_id');
    }

    public function renewFromContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'renew_from_contract_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function representativeTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'representative_tenant_id');
    }

    public function contractTenants(): HasMany
    {
        return $this->hasMany(ContractTenant::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'contract_tenants')->withPivot(['join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying', 'created_by'])->withTimestamps();
    }

    public function roomMovements(): HasMany
    {
        return $this->hasMany(RoomMovement::class);
    }

    public function sourceRoomMovements(): HasMany
    {
        return $this->hasMany(RoomMovement::class, 'source_contract_id');
    }

    public function destinationRoomMovements(): HasMany
    {
        return $this->hasMany(RoomMovement::class, 'destination_contract_id');
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

    public function getDepositBalanceAttribute(): string
    {
        $transactions = $this->relationLoaded('depositTransactions')
            ? $this->depositTransactions
            : $this->depositTransactions()->get();

        $balance = $transactions->reduce(function (float $balance, ContractDepositTransaction $transaction): float {
            $amount = (float) $transaction->amount;

            if (ContractDepositTransaction::increasesDepositBalance((int) $transaction->transaction_type)) {
                return $balance + $amount;
            }

            return $balance - $amount;
        }, 0.0);

        return number_format($balance, 2, '.', '');
    }

    public function getIsDepositPaidAttribute(): bool
    {
        $required = (float) $this->deposit_amount;
        if ($required <= 0) {
            return true;
        }

        return (float) $this->deposit_balance >= $required;
    }
}
