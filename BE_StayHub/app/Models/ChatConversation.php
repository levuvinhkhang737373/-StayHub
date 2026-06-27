<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 1;
    public const STATUS_CLOSED = 2;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Đang hoạt động',
        self::STATUS_CLOSED => 'Đã đóng',
    ];

    protected $fillable = [
        'building_id',
        'room_id',
        'tenant_id',
        'manager_admin_id',
        'last_message_id',
        'last_message_at',
        'tenant_unread_count',
        'admin_unread_count',
        'tenant_last_read_at',
        'admin_last_read_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'tenant_last_read_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
            'tenant_unread_count' => 'integer',
            'admin_unread_count' => 'integer',
            'status' => 'integer',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'manager_admin_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }
}
