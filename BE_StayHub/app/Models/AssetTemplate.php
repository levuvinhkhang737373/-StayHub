<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetTemplate extends Model
{
    use HasFactory, HasUniqueSlug;


    public const UNIT_PIECE = 1;
    public const UNIT_SET = 2;
    public const UNIT_UNIT = 3;

    public const UNIT_LABELS = [
        self::UNIT_PIECE => 'cái',
        self::UNIT_SET => 'bộ',
        self::UNIT_UNIT => 'chiếc',
    ];

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Hoạt động',
        self::STATUS_INACTIVE => 'Ngừng hoạt động',
    ];

    protected $fillable = ['name', 'slug', 'default_unit_name', 'description', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['default_unit_name' => 'integer', 'status' => 'integer'];
    }


    public function roomAssets(): HasMany
    {
        return $this->hasMany(RoomAsset::class, 'asset_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
