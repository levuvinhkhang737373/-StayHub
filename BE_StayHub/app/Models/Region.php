<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Region extends Model
{
    use HasFactory, HasUniqueSlug, Searchable;

    public const SEARCH_INDEX = 'regions';

    public function searchableAs(): string
    {
        return self::SEARCH_INDEX;
    }


    public const ACTIVE = true;
    public const INACTIVE = false;

    public const ACTIVE_LABELS = [
        self::ACTIVE => 'Đang hoạt động',
        self::INACTIVE => 'Ngừng hoạt động',
    ];

    protected $fillable = ['parent_id', 'code', 'name', 'path', 'slug', 'description', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Region::class, 'parent_id');
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Dữ liệu đồng bộ sang Meilisearch để tra cứu khu vực tốc độ cao.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'path' => $this->path,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => optional($this->created_at)->timestamp,
            'updated_at' => optional($this->updated_at)->timestamp,
        ];
    }
}
