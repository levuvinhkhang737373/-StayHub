<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReminderLog extends Model
{
    use HasFactory;

    public const STATUS_SENT = 1;
    public const STATUS_FAILED = 2;

    public const STATUS_LABELS = [
        self::STATUS_SENT => 'Đã gửi',
        self::STATUS_FAILED => 'Gửi lỗi',
    ];

    protected $fillable = [
        'invoice_id',
        'contract_id',
        'room_id',
        'notification_id',
        'reminder_date',
        'tenant_count',
        'mail_queued_count',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
            'tenant_count' => 'integer',
            'mail_queued_count' => 'integer',
            'status' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
