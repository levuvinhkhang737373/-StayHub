<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomServicePrice extends Model
{
    use HasFactory;
    protected $fillable = ['room_service_id', 'contract_id', 'price', 'effective_from', 'effective_to', 'created_by'];

    protected function casts(): array
    {
        return [
            'room_service_id' => 'integer',
            'contract_id' => 'integer',
            'price' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
