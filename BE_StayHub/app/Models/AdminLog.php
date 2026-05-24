<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    use HasFactory;

    protected $fillable = ['admin_id', 'action', 'entity_type', 'entity_id', 'old_data', 'new_data', 'ip_address', 'user_agent'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['old_data' => 'array', 'new_data' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
