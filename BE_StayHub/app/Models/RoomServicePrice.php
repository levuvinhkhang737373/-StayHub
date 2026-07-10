<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    public function scopeEffectiveFor(Builder $query, Carbon|string $targetDate): Builder
    {
        $date = $targetDate instanceof Carbon ? $targetDate->toDateString() : $targetDate;

        return $query->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $scope) use ($date): void {
                $scope->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    public function scopeForContractOrDefault(Builder $query, ?int $contractId): Builder
    {
        return $query->where(function (Builder $scope) use ($contractId): void {
            $scope->whereNull('contract_id')
                ->when($contractId !== null, fn (Builder $contractScope): Builder => $contractScope->orWhere('contract_id', $contractId));
        });
    }

    public function scopePriorityForContract(Builder $query, ?int $contractId): Builder
    {
        if ($contractId === null) {
            return $query->orderByDesc('effective_from')->orderByDesc('id');
        }

        return $query
            ->orderByRaw('contract_id = ? DESC', [$contractId])
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }
}
