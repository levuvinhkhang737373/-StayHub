<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomService extends Model
{
    use HasFactory;

    protected $fillable = ['room_id', 'service_id', 'is_active', 'ended_at'];

    protected function casts(): array
    {
        return [
            'room_id' => 'integer',
            'service_id' => 'integer',
            'is_active' => 'boolean',
            'ended_at' => 'date',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUsableForPeriod(Builder $query, mixed $periodStart, mixed $periodEnd): Builder
    {
        $start = $periodStart instanceof \Carbon\CarbonInterface ? $periodStart->toDateString() : (string) $periodStart;
        $end = $periodEnd instanceof \Carbon\CarbonInterface ? $periodEnd->toDateString() : (string) $periodEnd;

        return $query->where(function (Builder $scope) use ($start, $end): void {
            $scope->where('is_active', true)
                ->orWhere(function (Builder $endedScope) use ($start, $end): void {
                    $endedScope->where('is_active', false)
                        ->whereDate('ended_at', '>=', $start)
                        ->whereDate('ended_at', '<=', $end);
                });
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RoomServicePrice::class);
    }
}
