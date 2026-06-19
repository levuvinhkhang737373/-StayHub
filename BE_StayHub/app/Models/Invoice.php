<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Invoice extends Model
{
    use HasFactory, Searchable;

    public const SEARCH_INDEX = 'invoices';

    public function searchableAs(): string
    {
        return self::SEARCH_INDEX;
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing([
            'contract',
            'room.building',
            'contract.contractTenants.tenant',
        ]);

        $tenantNames = [];
        $tenantPhones = [];
        if ($this->contract && $this->contract->contractTenants) {
            foreach ($this->contract->contractTenants as $contractTenant) {
                if ($contractTenant->tenant) {
                    $tenantNames[] = $contractTenant->tenant->full_name;
                    $tenantPhones[] = $contractTenant->tenant->phone;
                }
            }
        }

        return [
            'id' => $this->id,
            'invoice_code' => $this->invoice_code,
            'contract_id' => $this->contract_id === null ? null : (int) $this->contract_id,
            'room_id' => $this->room_id === null ? null : (int) $this->room_id,
            'building_id' => ($this->room && $this->room->building_id) ? (int) $this->room->building_id : null,
            'billing_month' => $this->billing_month === null ? null : (int) $this->billing_month,
            'billing_year' => $this->billing_year === null ? null : (int) $this->billing_year,
            'status' => $this->status === null ? null : (int) $this->status,
            'created_by' => $this->created_by === null ? null : (int) $this->created_by,
            'contract_code' => $this->contract ? $this->contract->contract_code : null,
            'room_number' => $this->room ? $this->room->room_number : null,
            'building_name' => ($this->room && $this->room->building) ? $this->room->building->name : null,
            'tenant_names' => $tenantNames,
            'tenant_phones' => $tenantPhones,
            'created_at' => optional($this->created_at)->timestamp,
            'updated_at' => optional($this->updated_at)->timestamp,
        ];
    }

    public const STATUS_UNPAID = 2;

    public const STATUS_PARTIALLY_PAID = 3;

    public const STATUS_PAID = 4;

    public const STATUS_OVERDUE = 5;

    public const STATUS_CANCELLED = 6;

    public const STATUS_LABELS = [
        self::STATUS_UNPAID => 'Chưa thanh toán',
        self::STATUS_PARTIALLY_PAID => 'Thanh toán 1 phần',
        self::STATUS_PAID => 'Đã thanh toán',
        self::STATUS_OVERDUE => 'Quá hạn',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected $fillable = ['invoice_code', 'contract_id', 'room_id', 'billing_month', 'billing_year', 'period_start', 'period_end', 'previous_debt_amount', 'total_amount', 'paid_amount', 'remaining_amount', 'due_date', 'status', 'issued_at', 'created_by'];

    protected function casts(): array
    {
        return ['billing_month' => 'integer', 'billing_year' => 'integer', 'period_start' => 'date', 'period_end' => 'date', 'previous_debt_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'due_date' => 'date', 'status' => 'integer', 'issued_at' => 'datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
