<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    use HasFactory;


    public const STATUS_ACTIVE = 1;
    public const STATUS_EXPIRED = 2;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Còn hiệu lực',
        self::STATUS_EXPIRED => 'Hết hiệu lực',
    ];

    protected $fillable = ['service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
