<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomServicePrice extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 1;
    public const STATUS_EXPIRED = 2;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Còn hiệu lực',
        self::STATUS_EXPIRED => 'Hết hiệu lực',
    ];

    protected $fillable = ['room_service_id', 'price', 'effective_from', 'effective_to', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'room_service_id' => 'integer',
            'price' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function roomService(): BelongsTo
    {
        return $this->belongsTo(RoomService::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
