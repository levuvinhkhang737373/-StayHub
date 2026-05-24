<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingImage extends Model
{
    use HasFactory;

    public const STATUS_VISIBLE = 1;

    public const STATUS_HIDDEN = 2;

    public const STATUS_LABELS = [
        self::STATUS_VISIBLE => 'Hiển thị',
        self::STATUS_HIDDEN => 'Không hiển thị',
    ];

    public const NOT_PRIMARY = false;

    public const PRIMARY = true;

    public const PRIMARY_LABELS = [
        self::NOT_PRIMARY => 'Không',
        self::PRIMARY => 'Có',
    ];

    protected $fillable = ['building_id', 'image_path', 'is_primary', 'sort_order', 'status', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'status' => 'integer',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }
}
