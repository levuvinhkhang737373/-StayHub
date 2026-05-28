<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

class Building extends Model
{
    use HasFactory, HasUniqueSlug, Searchable;

    public const SEARCH_INDEX = 'buildings';

    public function searchableAs(): string
    {
        return self::SEARCH_INDEX;
    }

    public const GENDER_POLICY_MIXED = 1;
    public const GENDER_POLICY_MALE = 2;
    public const GENDER_POLICY_FEMALE = 3;

    public const GENDER_POLICY_LABELS = [
        self::GENDER_POLICY_MIXED => 'Hỗn hợp',
        self::GENDER_POLICY_MALE => 'Nam',
        self::GENDER_POLICY_FEMALE => 'Nữ',
    ];

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;
    public const STATUS_MAINTENANCE = 3;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Hoạt động',
        self::STATUS_INACTIVE => 'Ngừng hoạt động',
        self::STATUS_MAINTENANCE => 'Đang bảo trì',
    ];

    protected $fillable = ['region_id', 'manager_admin_id', 'name', 'slug', 'address', 'total_floors', 'gender_policy', 'description', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'total_floors' => 'integer',
            'gender_policy' => 'integer',
            'status' => 'integer',
        ];
    }

    protected function slugSourceIsDirty(): bool
    {
        return $this->isDirty('name') || $this->isDirty('region_id');
    }

    protected function slugSourceValue(): string
    {
        $regionName = $this->relationLoaded('region')
            ? $this->region?->name
            : Region::query()->whereKey($this->region_id)->value('name');
        return trim(($regionName ? $regionName.' ' : '').$this->name);
    }

    /**
     * Dữ liệu đồng bộ sang Meilisearch để tìm kiếm tòa nhà tốc độ cao.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'region_id' => $this->region_id,
            'manager_admin_id' => $this->manager_admin_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'total_floors' => $this->total_floors === null ? null : (int) $this->total_floors,
            'gender_policy' => (int) $this->gender_policy,
            'description' => $this->description,
            'status' => (int) $this->status,
            'created_by' => $this->created_by,
            'created_at' => optional($this->created_at)->timestamp,
            'updated_at' => optional($this->updated_at)->timestamp,
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'manager_admin_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(BuildingImage::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(BuildingImage::class)->where('is_primary', true)->orderBy('sort_order')->orderBy('id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function assetTemplates(): HasMany
    {
        return $this->hasMany(AssetTemplate::class, 'building_id');
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class, 'building_id');
    }

    public function servicePrices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }
}
