<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FireSafetyAlert\IndexRequest;
use App\Http\Resources\Admin\FireSafetyAlertResource;
use App\Models\FireSafetyAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FireSafetyAlertController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem cảnh báo AI camera', 403, null, 403);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem cảnh báo của tòa nhà này', 403, null, 403);
            }

            $alertQuery = FireSafetyAlert::query()->with(['securityCamera', 'building', 'acknowledger', 'resolver']);
            AdminScope::applyBuildingScope($alertQuery, $admin);

            $alerts = $alertQuery
                ->when(isset($validated['building_id']), fn (Builder $q): Builder => $q->where('building_id', (int) $validated['building_id']))
                ->when(isset($validated['security_camera_id']), fn (Builder $q): Builder => $q->where('security_camera_id', (int) $validated['security_camera_id']))
                ->when(isset($validated['risk_level']), fn (Builder $q): Builder => $q->where('risk_level', (int) $validated['risk_level']))
                ->when(isset($validated['status']), fn (Builder $q): Builder => $q->where('status', (int) $validated['status']))
                ->when(isset($validated['from']), fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $validated['from']))
                ->when(isset($validated['to']), fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $validated['to']))
                ->orderByDesc('created_at')
                ->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách cảnh báo AI camera', 200, [
                'data' => FireSafetyAlertResource::collection($alerts->items())->resolve(),
                'pagination' => [
                    'current_page' => $alerts->currentPage(),
                    'per_page' => $alerts->perPage(),
                    'total' => $alerts->total(),
                    'last_page' => $alerts->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $fireSafetyAlert): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            $alert = FireSafetyAlert::query()
                ->with(['securityCamera', 'building', 'acknowledger', 'resolver'])
                ->find($fireSafetyAlert);

            if (! $admin || ! $alert) {
                return ApiResponse::responseJson(false, 'Không tìm thấy cảnh báo AI camera', 404, null, 404);
            }

            if (! AdminScope::ensureBuildingAccess($admin, (int) $alert->building_id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem cảnh báo này', 403, null, 403);
            }

            return ApiResponse::responseJson(true, 'Chi tiết cảnh báo AI camera', 200, new FireSafetyAlertResource($alert), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function acknowledge(Request $request, int $fireSafetyAlert): JsonResponse
    {
        return $this->changeStatus($request, $fireSafetyAlert, FireSafetyAlert::STATUS_ACKNOWLEDGED, 'Đã xác nhận cảnh báo');
    }

    public function resolve(Request $request, int $fireSafetyAlert): JsonResponse
    {
        return $this->changeStatus($request, $fireSafetyAlert, FireSafetyAlert::STATUS_RESOLVED, 'Đã xử lý cảnh báo');
    }

    public function markFalseAlarm(Request $request, int $fireSafetyAlert): JsonResponse
    {
        return $this->changeStatus($request, $fireSafetyAlert, FireSafetyAlert::STATUS_FALSE_ALARM, 'Đã đánh dấu báo giả');
    }

    private function changeStatus(Request $request, int $alertId, int $status, string $message): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            $alert = FireSafetyAlert::query()->with(['securityCamera', 'building', 'acknowledger', 'resolver'])->find($alertId);

            if (! $admin || ! $alert) {
                return ApiResponse::responseJson(false, 'Không tìm thấy cảnh báo AI camera', 404, null, 404);
            }

            if (! AdminScope::ensureBuildingAccess($admin, (int) $alert->building_id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật cảnh báo này', 403, null, 403);
            }

            if ($status === FireSafetyAlert::STATUS_ACKNOWLEDGED) {
                $alert->forceFill([
                    'status' => $status,
                    'acknowledged_by' => $admin->id,
                    'acknowledged_at' => now(),
                ])->save();
            } else {
                $alert->forceFill([
                    'status' => $status,
                    'resolved_by' => $admin->id,
                    'resolved_at' => now(),
                ])->save();
            }

            $alert->load(['securityCamera', 'building', 'acknowledger', 'resolver']);

            return ApiResponse::responseJson(true, $message, 200, new FireSafetyAlertResource($alert), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }
}
