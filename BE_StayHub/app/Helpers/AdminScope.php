<?php

namespace App\Helpers;

use App\Models\Admin;
use App\Models\Building;
use App\Models\MaintenanceRequest;
use Illuminate\Database\Eloquent\Builder;

class AdminScope
{
    public static function isSuperAdmin(Admin $admin): bool
    {
        return $admin->role === Admin::ROLE_SUPER_ADMIN;
    }

    public static function isBuildingManager(Admin $admin): bool
    {
        return $admin->role === Admin::ROLE_BUILDING_MANAGER;
    }

    public static function isTechnician(Admin $admin): bool
    {
        return false;
    }

    /**
     * Lấy danh sách ID tòa nhà admin đang quản lý.
     */
    public static function managedBuildingIds(Admin $admin): array
    {
        if (! self::isBuildingManager($admin)) {
            return [];
        }

        return $admin->managedBuildings()
            ->select('buildings.id')
            ->pluck('buildings.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Áp scope theo tòa nhà cho query có cột building_id.
     * Super admin xem toàn bộ, quản lý tòa nhà chỉ xem tòa nhà mình quản lý, role khác không thấy dữ liệu.
     */
    public static function applyBuildingScope(Builder $query, Admin $admin, string $column = 'building_id'): Builder
    {
        if (self::isSuperAdmin($admin)) {
            return $query;
        }

        if (self::isBuildingManager($admin)) {
            return $query->whereIn($column, Building::query()
                ->select('id')
                ->where('manager_admin_id', $admin->id));
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Quyền xem khách thuê theo tòa nhà.
     */
    public static function applyTenantScope(Builder $query, Admin $admin): Builder
    {
        if (self::isSuperAdmin($admin)) {
            return $query;
        }

        if (self::isBuildingManager($admin)) {
            return $query->where(function (Builder $tenantQuery) use ($admin): void {
                $tenantQuery
                    ->whereIn('building_id', Building::query()
                        ->select('id')
                        ->where('manager_admin_id', $admin->id))
                    ->orWhereHas('contractTenants.contract.room.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Kiểm tra quyền truy cập một tòa nhà bằng exists().
     */
    public static function ensureBuildingAccess(Admin $admin, int $buildingId): bool
    {
        if (self::isSuperAdmin($admin)) {
            return true;
        }

        if (! self::isBuildingManager($admin)) {
            return false;
        }

        return Building::query()
            ->whereKey($buildingId)
            ->where('manager_admin_id', $admin->id)
            ->exists();
    }

    /**
     * Áp quyền xem phiếu bảo trì theo vai trò admin.
     */
    public static function applyMaintenanceRequestScope(Builder $query, Admin $admin): Builder
    {
        if (self::isSuperAdmin($admin)) {
            return $query;
        }

        if (self::isBuildingManager($admin)) {
            return $query->whereHas('room.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
        }

        if (self::isTechnician($admin)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Kiểm tra quyền cập nhật trạng thái phiếu bảo trì.
     */
    public static function canUpdateMaintenanceRequestStatus(Admin $admin, MaintenanceRequest $maintenanceRequest): bool
    {
        if (self::isSuperAdmin($admin)) {
            return true;
        }

        if (self::isBuildingManager($admin)) {
            return Building::query()
                ->whereKey($maintenanceRequest->room?->building_id)
                ->where('manager_admin_id', $admin->id)
                ->exists();
        }

        return false;
    }
}
