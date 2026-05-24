<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRequestLog extends Model
{
    use HasFactory;

    protected $fillable = ['maintenance_request_id', 'old_status', 'new_status', 'note', 'created_by'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_status' => 'integer',
            'new_status' => 'integer',
        ];
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
