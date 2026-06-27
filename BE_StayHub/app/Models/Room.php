<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Room extends Model
{
    use HasFactory, HasUniqueSlug, Searchable;

    public const SEARCH_INDEX = 'rooms';

    public function searchableAs(): string
    {
        return self::SEARCH_INDEX;
    }

    /**
     * Dữ liệu đồng bộ sang Meilisearch để tìm kiếm phòng trọ tốc độ cao.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'room_type_id' => $this->room_type_id,
            'room_number' => $this->room_number,
            'slug' => $this->slug,
            'floor' => (int) $this->floor,
            'area_m2' => $this->area_m2 === null ? null : (float) $this->area_m2,
            'base_price' => $this->base_price === null ? null : (float) $this->base_price,
            'max_occupants' => (int) $this->max_occupants,
            'current_occupants' => (int) $this->current_occupants,
            'status' => (int) $this->status,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'created_at' => optional($this->created_at)->timestamp,
            'updated_at' => optional($this->updated_at)->timestamp,
        ];
    }


    public const STATUS_ACTIVE = 1;
    public const STATUS_MAINTENANCE = 2;
    public const STATUS_INACTIVE = 3;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Hoạt động',
        self::STATUS_MAINTENANCE => 'Đang bảo trì',
        self::STATUS_INACTIVE => 'Ngưng sử dụng',
    ];

    protected $fillable = ['building_id', 'room_type_id', 'room_number', 'slug', 'floor', 'area_m2', 'base_price', 'max_occupants', 'current_occupants', 'status', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['floor' => 'integer', 'area_m2' => 'decimal:2', 'base_price' => 'decimal:2', 'max_occupants' => 'integer', 'current_occupants' => 'integer', 'status' => 'integer'];
    }

    protected function slugSourceColumn(): string
    {
        return 'room_number';
    }

    public function scopeTrong(Builder $query): Builder
    {
        return $query->where('status', 1)
            ->where('current_occupants', 0);
    }

    public function scopeDangOGhep(Builder $query): Builder
    {
        return $query->where('status', 1)
            ->where('current_occupants', '>', 0)
            ->whereColumn('current_occupants', '<', 'max_occupants');
    }

    public function scopeDaDay(Builder $query): Builder
    {
        return $query->where('status', 1)
            ->whereColumn('current_occupants', '>=', 'max_occupants');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(RoomAsset::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(RoomMovement::class, 'from_room_id');
    }

    public function incomingMovements(): HasMany
    {
        return $this->hasMany(RoomMovement::class, 'to_room_id');
    }

    public function meterDevices(): HasMany
    {
        return $this->hasMany(MeterDevice::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }
}
