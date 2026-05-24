<?php

namespace App\Helpers;

use App\Models\Admin;
use App\Models\AdminLog;
use Illuminate\Http\Request;

class AdminActivityLogger
{
    public static function write(
        Admin $admin,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?Request $request = null,
    ): AdminLog {
        return AdminLog::query()->create([
            'admin_id'    => $admin->id,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_data'    => $oldData,
            'new_data'    => $newData,
            'ip_address'  => $request?->ip(),
            'user_agent'  => $request?->userAgent(),
        ]);
    }
}
