<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    public const PUBLIC = true;
    public const PRIVATE = false;

    public const PUBLIC_LABELS = [
        self::PUBLIC => 'Được xem',
        self::PRIVATE => 'Không được xem',
    ];

    protected $fillable = ['building_id', 'setting_label', 'setting_value', 'description', 'is_public', 'created_by'];

    protected function casts(): array
    {
        return [
            'building_id' => 'integer',
            'is_public' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
