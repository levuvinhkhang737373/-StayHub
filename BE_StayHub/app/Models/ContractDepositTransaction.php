<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDepositTransaction extends Model
{
    use HasFactory;

    public const TRANSACTION_TYPE_COLLECT = 1;
    public const TRANSACTION_TYPE_REFUND = 2;
    public const TRANSACTION_TYPE_TRANSFER = 3;
    public const TRANSACTION_TYPE_DEDUCT = 4;

    public const TRANSACTION_TYPE_LABELS = [
        self::TRANSACTION_TYPE_COLLECT => 'Thu cọc',
        self::TRANSACTION_TYPE_REFUND => 'Hoàn cọc',
        self::TRANSACTION_TYPE_TRANSFER => 'Chuyển cọc',
        self::TRANSACTION_TYPE_DEDUCT => 'Khấu trừ cọc',
    ];

    public const PAYMENT_METHOD_CASH = 1;
    public const PAYMENT_METHOD_BANK_TRANSFER = 2;

    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_METHOD_CASH => 'Tiền mặt',
        self::PAYMENT_METHOD_BANK_TRANSFER => 'Chuyển khoản',
    ];

    protected $fillable = ['contract_id', 'transaction_type', 'amount', 'transaction_date', 'payment_method', 'transaction_reference', 'note', 'created_by'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'transaction_type' => 'integer',
            'amount' => 'decimal:2',
            'payment_method' => 'integer',
            'transaction_date' => 'date',
            'transaction_reference' => 'string'
        ];
    }

    protected static function booted()
    {
        static::saved(function ($transaction) {
            $contract = $transaction->contract;
            if ($contract) {
                $contract->updatePaymentStatus();
                event(new \App\Events\ContractDepositPaid($contract));
            }
        });

        static::deleted(function ($transaction) {
            $contract = $transaction->contract;
            if ($contract) {
                $contract->updatePaymentStatus();
                event(new \App\Events\ContractDepositPaid($contract));
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
