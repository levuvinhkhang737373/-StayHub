<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChatMessage extends Model
{
    use HasFactory;

    public const SENDER_TENANT = 1;
    public const SENDER_ADMIN = 2;

    public const SENDER_LABELS = [
        self::SENDER_TENANT => 'Khách thuê',
        self::SENDER_ADMIN => 'Quản lý',
    ];

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'sender_role',
        'body',
        'queued_at',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sender_role' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }
}
