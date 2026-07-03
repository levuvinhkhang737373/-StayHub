<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SecurityCamera\AnalyzeRequest;
use App\Http\Requests\Admin\SecurityCamera\BulkMonitoringRequest;
use App\Http\Requests\Admin\SecurityCamera\IndexRequest;
use App\Http\Requests\Admin\SecurityCamera\MonitoringRequest;
use App\Http\Requests\Admin\SecurityCamera\StoreRequest;
use App\Http\Requests\Admin\SecurityCamera\UpdateRequest;
use App\Http\Resources\Admin\FireSafetyAlertResource;
use App\Http\Resources\Admin\SecurityCameraResource;
use App\Jobs\MonitorSecurityCameraJob;
use App\Models\Admin;
use App\Models\SecurityCamera;
use App\Services\FireSafety\FireSafetyCameraAnalyzer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecurityCameraController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh sách camera', 403, null, 403);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem camera của tòa nhà này', 403, null, 403);
            }

            $cameras = $this->cameraQuery($admin, $validated)
                ->with(['building.manager', 'creator', 'latestAlert'])
                ->withCount('alerts')
                ->orderByDesc('created_at')
                ->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách camera an ninh', 200, [
                'data' => SecurityCameraResource::collection($cameras->items())->resolve(),
                'pagination' => [
                    'current_page' => $cameras->currentPage(),
                    'per_page' => $cameras->perPage(),
                    'total' => $cameras->total(),
                    'last_page' => $cameras->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được tạo camera', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $payload = $this->payload($validated, $admin);
                $wantsMonitoring = (bool) ($payload['is_ai_enabled'] ?? false);
                $payload['is_ai_enabled'] = false;

                if ($wantsMonitoring && (int) ($payload['status'] ?? SecurityCamera::STATUS_ACTIVE) !== SecurityCamera::STATUS_ACTIVE) {
                    return ApiResponse::responseJson(false, 'Camera tạm tắt không thể bật giám sát 24/24', 422, null, 422);
                }

                $camera = SecurityCamera::query()->create($payload);

                if ($wantsMonitoring) {
                    $this->startMonitoring($camera);
                }

                AdminActivityLogger::write($admin, 'Tạo máy quay an ninh', SecurityCamera::class, $camera->id, null, $camera->fresh()->toArray(), $request);

                $camera->load(['building.manager', 'creator'])->loadCount('alerts');

                return ApiResponse::responseJson(true, 'Tạo camera thành công', 201, new SecurityCameraResource($camera), 201);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $securityCamera): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            $camera = SecurityCamera::query()
                ->with(['building.manager', 'creator', 'updater', 'latestAlert'])
                ->withCount('alerts')
                ->find($securityCamera);

            if (! $admin || ! $camera) {
                return ApiResponse::responseJson(false, 'Không tìm thấy camera', 404, null, 404);
            }

            if (! AdminScope::ensureBuildingAccess($admin, (int) $camera->building_id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem camera này', 403, null, 403);
            }

            return ApiResponse::responseJson(true, 'Chi tiết camera', 200, new SecurityCameraResource($camera), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $securityCamera): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được cập nhật camera', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $securityCamera, $admin, $request): JsonResponse {
                $camera = SecurityCamera::query()->lockForUpdate()->find($securityCamera);

                if (! $camera) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy camera', 404, null, 404);
                }

                $oldData = $camera->toArray();
                $wantsMonitoring = array_key_exists('is_ai_enabled', $validated) ? (bool) $validated['is_ai_enabled'] : null;
                $targetStatus = (int) ($validated['status'] ?? $camera->status);

                if ($wantsMonitoring === true && $targetStatus !== SecurityCamera::STATUS_ACTIVE) {
                    return ApiResponse::responseJson(false, 'Camera tạm tắt không thể bật giám sát 24/24', 422, null, 422);
                }

                $camera->fill($this->payload($validated, $admin, true))->save();

                if ((int) $camera->status !== SecurityCamera::STATUS_ACTIVE) {
                    $this->stopMonitoring($camera);
                } elseif ($wantsMonitoring === true) {
                    $this->startMonitoring($camera);
                } elseif ($wantsMonitoring === false) {
                    $this->stopMonitoring($camera);
                }

                AdminActivityLogger::write($admin, 'Cập nhật máy quay an ninh', SecurityCamera::class, $camera->id, $oldData, $camera->fresh()->toArray(), $request);

                $camera->load(['building.manager', 'creator', 'updater'])->loadCount('alerts');

                return ApiResponse::responseJson(true, 'Cập nhật camera thành công', 200, new SecurityCameraResource($camera), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function destroy(Request $request, int $securityCamera): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được xóa camera', 403, null, 403);
            }

            $response = DB::transaction(function () use ($securityCamera, $admin, $request): JsonResponse {
                $camera = SecurityCamera::query()->withCount('alerts')->lockForUpdate()->find($securityCamera);

                if (! $camera) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy camera', 404, null, 404);
                }

                if ((int) $camera->alerts_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể xóa camera đã có lịch sử cảnh báo, vui lòng tạm tắt camera', 422, null, 422);
                }

                $oldData = $camera->toArray();
                $camera->delete();

                AdminActivityLogger::write($admin, 'Xóa máy quay an ninh', SecurityCamera::class, $securityCamera, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa camera thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function analyze(AnalyzeRequest $request, int $securityCamera): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            $camera = SecurityCamera::query()->with('building.manager')->find($securityCamera);

            if (! $admin || ! $camera) {
                return ApiResponse::responseJson(false, 'Không tìm thấy camera', 404, null, 404);
            }

            if (! AdminScope::ensureBuildingAccess($admin, (int) $camera->building_id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền phân tích camera này', 403, null, 403);
            }

            if ((int) $camera->status !== SecurityCamera::STATUS_ACTIVE) {
                return ApiResponse::responseJson(false, 'Camera đang tạm tắt, không thể quét AI', 422, null, 422);
            }

            $result = app(FireSafetyCameraAnalyzer::class)->analyze($camera, $admin);

            if (! ($result['success'] ?? false)) {
                return ApiResponse::responseJson(false, $result['message'] ?? 'Không thể phân tích camera', 422, $result['analysis'] ?? $result, 422);
            }

            $camera->load(['building.manager', 'creator', 'latestAlert'])->loadCount('alerts');

            return ApiResponse::responseJson(true, $result['message'], 200, [
                'camera' => new SecurityCameraResource($camera),
                'analysis' => $result['analysis'],
                'alert' => $result['alert'] ? new FireSafetyAlertResource($result['alert']) : null,
                'discord' => $result['discord'],
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function testStream(AnalyzeRequest $request, int $securityCamera): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            $camera = SecurityCamera::query()->with('building.manager')->find($securityCamera);

            if (! $admin || ! $camera) {
                return ApiResponse::responseJson(false, 'Không tìm thấy camera', 404, null, 404);
            }

            if (! AdminScope::ensureBuildingAccess($admin, (int) $camera->building_id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền test camera này', 403, null, 403);
            }

            $stream = app(FireSafetyCameraAnalyzer::class)->testStream($camera);

            if (! ($stream['success'] ?? false)) {
                return ApiResponse::responseJson(false, $stream['message'] ?? 'Không thể lấy frame từ camera', 422, $stream, 422);
            }

            $camera->load(['building.manager', 'creator', 'latestAlert'])->loadCount('alerts');

            return ApiResponse::responseJson(true, 'Camera đã trả frame thành công', 200, [
                'camera' => new SecurityCameraResource($camera),
                'stream' => $stream,
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function monitoring(MonitoringRequest $request, int $securityCamera): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được bật/tắt giám sát 24/24', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $securityCamera, $admin, $request): JsonResponse {
                $camera = SecurityCamera::query()->lockForUpdate()->find($securityCamera);

                if (! $camera) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy camera', 404, null, 404);
                }

                if ((bool) $validated['enabled'] && (int) $camera->status !== SecurityCamera::STATUS_ACTIVE) {
                    return ApiResponse::responseJson(false, 'Camera tạm tắt không thể bật giám sát 24/24', 422, null, 422);
                }

                $oldData = $camera->toArray();
                (bool) $validated['enabled'] ? $this->startMonitoring($camera) : $this->stopMonitoring($camera);

                AdminActivityLogger::write($admin, 'Cập nhật giám sát AI camera', SecurityCamera::class, $camera->id, $oldData, $camera->fresh()->toArray(), $request);

                $camera->load(['building.manager', 'creator', 'latestAlert'])->loadCount('alerts');

                return ApiResponse::responseJson(true, (bool) $validated['enabled'] ? 'Đã bật giám sát AI 24/24' : 'Đã tắt giám sát AI 24/24', 200, new SecurityCameraResource($camera), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function bulkMonitoring(BulkMonitoringRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được bật/tắt giám sát 24/24', 403, null, 403);
            }

            $enabled = (bool) $validated['enabled'];
            $updatedCount = 0;
            $skippedCount = 0;
            $filters = collect($validated)->only(['building_id', 'keyword'])->toArray();

            $this->cameraQuery($admin, $validated)
                ->orderBy('id')
                ->chunkById(100, function ($cameras) use ($enabled, &$updatedCount, &$skippedCount): void {
                    foreach ($cameras as $camera) {
                        if ($enabled && (int) $camera->status !== SecurityCamera::STATUS_ACTIVE) {
                            $skippedCount++;
                            continue;
                        }

                        $enabled ? $this->startMonitoring($camera) : $this->stopMonitoring($camera);
                        $updatedCount++;
                    }
                });

            AdminActivityLogger::write($admin, 'Cập nhật giám sát AI camera hàng loạt', SecurityCamera::class, null, [
                'requested_enabled' => $enabled,
                'filters' => $filters,
            ], [
                'enabled' => $enabled,
                'filters' => $filters,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
            ], $request);

            return ApiResponse::responseJson(true, $enabled ? 'Đã bật giám sát AI 24/24 theo bộ lọc' : 'Đã tắt giám sát AI 24/24 theo bộ lọc', 200, [
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function startMonitoring(SecurityCamera $camera): void
    {
        $token = (string) Str::uuid();
        $intervalSeconds = max(5, (int) $camera->frame_interval_seconds);

        $camera->forceFill([
            'is_ai_enabled' => true,
            'monitoring_token' => $token,
            'monitoring_started_at' => now(),
            'monitoring_stopped_at' => null,
            'next_scan_at' => now()->addSeconds($intervalSeconds),
            'last_scan_status' => null,
            'last_scan_message' => 'Đang chờ lượt quét AI 24/24 đầu tiên.',
            'monitoring_error_count' => 0,
        ])->save();

        MonitorSecurityCameraJob::dispatch($camera->id, $token)->afterCommit();
    }

    private function stopMonitoring(SecurityCamera $camera): void
    {
        $camera->forceFill([
            'is_ai_enabled' => false,
            'monitoring_token' => null,
            'monitoring_stopped_at' => now(),
            'next_scan_at' => null,
            'last_scan_message' => 'Đã tắt giám sát AI 24/24.',
        ])->save();
    }

    private function cameraQuery(Admin $admin, array $validated = []): Builder
    {
        $query = SecurityCamera::query();
        AdminScope::applyBuildingScope($query, $admin);

        return $query
            ->when(isset($validated['building_id']), fn (Builder $q): Builder => $q->where('building_id', (int) $validated['building_id']))
            ->when(isset($validated['source_type']), fn (Builder $q): Builder => $q->where('source_type', (int) $validated['source_type']))
            ->when(isset($validated['status']), fn (Builder $q): Builder => $q->where('status', (int) $validated['status']))
            ->when(array_key_exists('is_ai_enabled', $validated), fn (Builder $q): Builder => $q->where('is_ai_enabled', (bool) $validated['is_ai_enabled']))
            ->when(filled($validated['keyword'] ?? null), function (Builder $q) use ($validated): Builder {
                $keyword = trim((string) $validated['keyword']);

                return $q->where(function (Builder $subQuery) use ($keyword): void {
                    $subQuery->where('name', 'like', "%{$keyword}%")
                        ->orWhere('location', 'like', "%{$keyword}%")
                        ->orWhereHas('building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('name', 'like', "%{$keyword}%"));
                });
            });
    }

    private function payload(array $validated, Admin $admin, bool $isUpdate = false): array
    {
        $payload = collect($validated)
            ->only([
                'building_id',
                'name',
                'location',
                'source_type',
                'stream_url',
                'username',
                'password',
                'is_ai_enabled',
                'frame_interval_seconds',
                'frames_per_batch',
                'alert_cooldown_seconds',
                'status',
            ])
            ->toArray();

        if (array_key_exists('password', $payload) && blank($payload['password'])) {
            unset($payload['password']);
        }

        if (! $isUpdate) {
            $payload['created_by'] = $admin->id;
            $payload['is_ai_enabled'] = $payload['is_ai_enabled'] ?? false;
            $payload['frame_interval_seconds'] = $payload['frame_interval_seconds'] ?? (int) config('services.fire_safety.analysis_window_seconds', 2);
            $payload['frames_per_batch'] = $payload['frames_per_batch'] ?? (int) config('services.fire_safety.frame_count', 3);
            $payload['alert_cooldown_seconds'] = $payload['alert_cooldown_seconds'] ?? (int) config('services.fire_safety.alert_cooldown_seconds', 60);
            $payload['status'] = $payload['status'] ?? SecurityCamera::STATUS_ACTIVE;
        }

        $payload['updated_by'] = $admin->id;

        return $payload;
    }
}
