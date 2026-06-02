<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceFeedback extends Model
{
    use HasFactory;

    /** Bắt buộc khai báo đúng bảng vì Laravel không tự pluralize được từ "feedback". */
    protected $table = 'maintenance_feedbacks';

    protected $fillable = ['maintenance_request_id', 'tenant_id', 'rating', 'images', 'comment'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'images' => 'array',
        ];
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
