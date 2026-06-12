<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTenant extends Model
{
    use HasFactory;



    public const STAYING = true;
    public const NOT_STAYING = false;

    public const STAYING_LABELS = [
        self::STAYING => 'Còn đang ở',
        self::NOT_STAYING => 'Đã rời đi',
    ];

    protected $fillable = ['contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying', 'created_by'];

    protected function casts(): array
    {
        return ['join_date' => 'date', 'leave_date' => 'date', 'billing_start_date' => 'date', 'billing_end_date' => 'date', 'is_staying' => 'boolean'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
