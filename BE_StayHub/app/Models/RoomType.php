<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory, HasUniqueSlug;


    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Hoạt động',
        self::STATUS_INACTIVE => 'Ngừng hoạt động',
    ];

    protected $fillable = ['name', 'slug', 'building_id', 'description', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['building_id' => 'integer', 'status' => 'integer'];
    }

    protected function slugSourceIsDirty(): bool
    {
        return $this->isDirty('name') || $this->isDirty('building_id');
    }

    protected function slugExists(string $slug): bool
    {
        return static::query()
            ->where('slug', $slug)
            ->where('building_id', $this->building_id)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
