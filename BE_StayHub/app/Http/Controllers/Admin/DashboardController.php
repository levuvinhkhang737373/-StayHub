<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function utilityPriceHistory(Request $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            $buildingId = $request->query('building_id');
            if ($buildingId) {
                $buildingId = (int) $buildingId;
                if (! AdminScope::ensureBuildingAccess($admin, $buildingId)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
                }
            } else {
                if (AdminScope::isSuperAdmin($admin)) {
                    $building = Building::query()->orderBy('id')->first();
                    $buildingId = $building ? $building->id : null;
                } else {
                    $managedIds = AdminScope::managedBuildingIds($admin);
                    $buildingId = !empty($managedIds) ? $managedIds[0] : null;
                }
            }

            if (! $buildingId) {
                return ApiResponse::responseJson(true, 'Lịch sử đơn giá điện nước', 200, [], 200);
            }

            $electricService = Service::whereIn('slug', ['electric', 'dien-sinh-hoat', 'dien'])->first();
            $waterService = Service::whereIn('slug', ['water', 'nuoc-sinh-hoat', 'nuoc'])->first();

            if (! $electricService || ! $waterService) {
                return ApiResponse::responseJson(false, 'Không tìm thấy cấu hình dịch vụ điện hoặc nước', 422, null, 422);
            }

            $monthsCount = $request->query('months', 6);
            if ($monthsCount < 2 || $monthsCount > 24) {
                $monthsCount = 6;
            }

            $data = [];
            $currentDate = Carbon::now()->startOfMonth();

            for ($i = $monthsCount - 1; $i >= 0; $i--) {
                $targetMonthDate = $currentDate->copy()->subMonths($i);
                $targetMonthStr = $targetMonthDate->format('m/Y');
                $targetDateSql = $targetMonthDate->toDateString();

                // Find active electric price for that month
                $electricPrice = ServicePrice::where('building_id', $buildingId)
                    ->where('service_id', $electricService->id)
                    ->whereDate('effective_from', '<=', $targetMonthDate)
                    ->where(function ($query) use ($targetMonthDate) {
                        $query->whereDate('effective_to', '>=', $targetMonthDate)
                            ->orWhereNull('effective_to');
                    })
                    ->orderBy('effective_from', 'desc')
                    ->orderBy('id', 'desc')
                    ->value('price');

                if ($electricPrice === null) {
                    $electricPrice = ServicePrice::where('building_id', $buildingId)
                        ->where('service_id', $electricService->id)
                        ->whereDate('effective_from', '<=', $targetMonthDate)
                        ->orderBy('effective_from', 'desc')
                        ->orderBy('id', 'desc')
                        ->value('price');
                }

                // Find active water price for that month
                $waterPrice = ServicePrice::where('building_id', $buildingId)
                    ->where('service_id', $waterService->id)
                    ->whereDate('effective_from', '<=', $targetMonthDate)
                    ->where(function ($query) use ($targetMonthDate) {
                        $query->whereDate('effective_to', '>=', $targetMonthDate)
                            ->orWhereNull('effective_to');
                    })
                    ->orderBy('effective_from', 'desc')
                    ->orderBy('id', 'desc')
                    ->value('price');

                if ($waterPrice === null) {
                    $waterPrice = ServicePrice::where('building_id', $buildingId)
                        ->where('service_id', $waterService->id)
                        ->whereDate('effective_from', '<=', $targetMonthDate)
                        ->orderBy('effective_from', 'desc')
                        ->orderBy('id', 'desc')
                        ->value('price');
                }

                $data[] = [
                    'month' => $targetMonthStr,
                    'electric_price' => $electricPrice !== null ? (float) $electricPrice : 4000.0,
                    'water_price' => $waterPrice !== null ? (float) $waterPrice : 18000.0,
                ];
            }

            return ApiResponse::responseJson(true, 'Lịch sử đơn giá điện nước', 200, $data, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }
}
