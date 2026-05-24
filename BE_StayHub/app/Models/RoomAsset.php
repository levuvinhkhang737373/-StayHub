<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAsset extends Model
{
    use HasFactory;

    protected $fillable = ['room_id', 'asset_template_id', 'quantity', 'price', 'note'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'price' => 'decimal:2'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assetTemplate(): BelongsTo
    {
        return $this->belongsTo(AssetTemplate::class, 'asset_template_id');
    }
}
