<?php

namespace App\Http\Controllers\Admin;

use App\Events\NotificationSent;
use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SecurityCamera\AnalyzeRequest;
use App\Http\Requests\Admin\SecurityCamera\IndexRequest;
use App\Http\Requests\Admin\SecurityCamera\StoreRequest;
use App\Http\Requests\Admin\SecurityCamera\UpdateRequest;
use App\Http\Resources\Admin\FireSafetyAlertResource;
use App\Http\Resources\Admin\SecurityCameraResource;
use App\Models\Admin;
use App\Models\FireSafetyAlert;
use App\Models\Notification;
use App\Models\SecurityCamera;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
                $camera = SecurityCamera::query()->create($this->payload($validated, $admin));

                AdminActivityLogger::write($admin, 'create_security_camera', SecurityCamera::class, $camera->id, null, $camera->toArray(), $request);

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
                $camera->fill($this->payload($validated, $admin, true))->save();

                AdminActivityLogger::write($admin, 'update_security_camera', SecurityCamera::class, $camera->id, $oldData, $camera->toArray(), $request);

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

                AdminActivityLogger::write($admin, 'delete_security_camera', SecurityCamera::class, $securityCamera, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa camera thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function analyze(AnalyzeRequest $request, int $securityCamera): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');
            $camera = SecurityCamera::query()->with('building.manager')->find($securityCamera);

            if (! $admin || ! $camera) {
                return ApiResponse::responseJson(false, 'Không tìm thấy camera', 404, null, 404);
            }

            if (! AdminScope::ensureBuildingAccess($admin, (int) $camera->building_id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền phân tích camera này', 403, null, 403);
            }

            if (! $camera->is_ai_enabled || $camera->status !== SecurityCamera::STATUS_ACTIVE) {
                return ApiResponse::responseJson(false, 'Camera đang tắt hoặc chưa bật AI', 422, null, 422);
            }

            $analysis = $this->analyzeCameraStream($camera);

            if (! ($analysis['success'] ?? false)) {
                return ApiResponse::responseJson(false, $analysis['message'] ?? 'Không thể phân tích camera', 422, $analysis, 422);
            }

            $alert = null;

            if ($this->shouldCreateAlert($analysis, $camera)) {
                $alert = $this->createAlertFromAnalysis($camera, $analysis, $request);
                $this->notifyFireAlert($alert, $request);
                $discord = $this->sendDiscordAlert($alert);
                $alert = $this->attachDiscordResult($alert, $discord);
            }

            $camera->load(['building.manager', 'creator', 'latestAlert'])->loadCount('alerts');

            $message = $alert
                ? 'AI phát hiện nguy cơ và đã gửi cảnh báo'
                : 'AI đã phân tích camera, chưa phát hiện nguy cơ vượt ngưỡng';

            return ApiResponse::responseJson(true, $message, 200, [
                'camera' => new SecurityCameraResource($camera),
                'analysis' => $this->publicAnalysisPayload($analysis),
                'alert' => $alert ? new FireSafetyAlertResource($alert) : null,
                'discord' => $discord ?? null,
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

            $stream = $this->testCameraStream($camera);

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

    private function analyzeCameraStream(SecurityCamera $camera): array
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('services.fire_safety.ai_timeout', 90))
                ->post(config('services.ai_service.url') . '/api/v1/fire-safety/analyze-stream', $this->cameraAiPayload($camera));
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Không kết nối được AI service: ' . $e->getMessage(),
            ];
        }

        if (! $response->successful()) {
            $message = $response->json('detail') ?? 'AI service không thể phân tích camera';

            return [
                'success' => false,
                'message' => is_string($message) ? $message : 'AI service không thể phân tích camera',
                'status_code' => $response->status(),
            ];
        }

        $analysis = $response->json();

        if (! is_array($analysis) || ! isset($analysis['risk_level_code'])) {
            return [
                'success' => false,
                'message' => 'AI service trả về dữ liệu phân tích không hợp lệ',
            ];
        }

        $analysis['success'] = true;
        $analysis['confidence'] = max(0, min(1, (float) ($analysis['confidence'] ?? 0)));

        return $analysis;
    }

    private function testCameraStream(SecurityCamera $camera): array
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post(config('services.ai_service.url') . '/api/v1/fire-safety/test-stream', $this->cameraAiPayload($camera, 1, 1));
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Không kết nối được AI service: ' . $e->getMessage(),
            ];
        }

        if (! $response->successful()) {
            $message = $response->json('detail') ?? 'Không thể lấy frame từ camera';

            return [
                'success' => false,
                'message' => is_string($message) ? $message : 'Không thể lấy frame từ camera',
                'status_code' => $response->status(),
            ];
        }

        $stream = $response->json();

        if (! is_array($stream) || ! ($stream['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'AI service trả về dữ liệu test camera không hợp lệ',
            ];
        }

        return $stream;
    }

    private function cameraAiPayload(SecurityCamera $camera, ?int $frameCount = null, ?int $windowSeconds = null): array
    {
        return [
            'camera_id' => $camera->id,
            'building_id' => $camera->building_id,
            'camera_name' => $camera->name,
            'location' => $camera->location,
            'source_type' => $camera->source_type,
            'stream_url' => $camera->stream_url,
            'username' => $camera->username,
            'password' => $camera->password,
            'frame_count' => max(1, min(6, $frameCount ?? (int) $camera->frames_per_batch)),
            'window_seconds' => max(1, min(60, $windowSeconds ?? (int) $camera->frame_interval_seconds)),
        ];
    }

    private function shouldCreateAlert(array $analysis, SecurityCamera $camera): bool
    {
        if (! $this->hasAlertVisualSignal($analysis)) {
            return false;
        }

        $cooldownSeconds = max(10, (int) $camera->alert_cooldown_seconds);

        return ! FireSafetyAlert::query()
            ->where('security_camera_id', $camera->id)
            ->where('status', FireSafetyAlert::STATUS_OPEN)
            ->where('created_at', '>=', now()->subSeconds($cooldownSeconds))
            ->exists();
    }

    private function publicAnalysisPayload(array $analysis): array
    {
        return collect($analysis)->except(['snapshot_base64', 'raw_provider_payload'])->toArray();
    }

    private function createAlertFromAnalysis(SecurityCamera $camera, array $analysis, Request $request): FireSafetyAlert
    {
        $riskLevel = max(FireSafetyAlert::RISK_WARNING, (int) ($analysis['risk_level_code'] ?? FireSafetyAlert::RISK_WARNING));

        $alert = FireSafetyAlert::query()->create([
            'security_camera_id' => $camera->id,
            'building_id' => $camera->building_id,
            'source_label' => trim($camera->name . ($camera->location ? ' - ' . $camera->location : '')),
            'risk_level' => $riskLevel,
            'detected_fire' => (bool) ($analysis['detected_fire'] ?? false),
            'detected_smoke' => (bool) ($analysis['detected_smoke'] ?? false),
            'detected_smoking' => (bool) ($analysis['detected_smoking'] ?? false),
            'confidence' => (float) ($analysis['confidence'] ?? 0),
            'snapshot_path' => $this->storeSnapshot($camera, $analysis),
            'ai_summary' => $analysis['summary'] ?? 'AI phát hiện nguy cơ từ camera.',
            'raw_ai_payload' => array_merge(collect($analysis)->except(['snapshot_base64', 'raw_provider_payload'])->toArray(), [
                'triggered_by_admin_id' => $request->user('admin')?->id,
            ]),
            'status' => FireSafetyAlert::STATUS_OPEN,
        ]);

        return $alert->load(['securityCamera', 'building', 'acknowledger', 'resolver']);
    }

    private function storeSnapshot(SecurityCamera $camera, array $analysis): ?string
    {
        if (! $this->hasAlertVisualSignal($analysis)) {
            return null;
        }

        $base64Image = $analysis['snapshot_base64'] ?? null;

        if (blank($base64Image)) {
            return null;
        }

        $image = preg_replace('/^data:image\/\w+;base64,/', '', (string) $base64Image);
        $binary = base64_decode((string) $image, true);

        if ($binary === false) {
            return null;
        }

        $folder = 'upload/fire-safety-alerts/' . now()->format('Y/m/d');
        $directory = public_path($folder);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return null;
        }

        $path = $folder . '/camera-' . $camera->id . '-' . Str::uuid() . '.jpg';
        $absolutePath = public_path($path);

        if (file_put_contents($absolutePath, $binary) === false) {
            return null;
        }

        ImageHelper::compress($absolutePath, 88, 1280);

        return ImageHelper::normalizePath($path);
    }

    private function hasAlertVisualSignal(array $analysis): bool
    {
        return (bool) ($analysis['detected_fire'] ?? false)
            || (bool) ($analysis['detected_smoke'] ?? false)
            || (bool) ($analysis['detected_smoking'] ?? false);
    }

    private function notifyFireAlert(FireSafetyAlert $alert, Request $request): void
    {
        $creatorId = $request->user('admin')?->id;
        $building = $alert->building;
        $adminIds = Admin::query()
            ->where('status', Admin::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($building): void {
                $query->where('role', Admin::ROLE_SUPER_ADMIN);

                if ($building?->manager_admin_id) {
                    $query->orWhereKey($building->manager_admin_id);
                }
            })
            ->pluck('id')
            ->unique()
            ->values();

        $title = 'Báo động đỏ AI Camera';
        $content = sprintf(
            'Camera %s tại %s phát hiện nguy cơ: %s. Độ tin cậy %.0f%%.',
            $alert->securityCamera?->name ?? 'không rõ',
            $building?->name ?? 'không rõ tòa nhà',
            FireSafetyAlert::RISK_LABELS[$alert->risk_level] ?? 'cảnh báo',
            ((float) $alert->confidence) * 100
        );

        $adminIds->each(function (int $adminId) use ($alert, $creatorId, $title, $content): void {
            $notification = Notification::query()->create([
                'title' => $title,
                'content' => $content,
                'notification_type' => Notification::NOTIFICATION_TYPE_WARNING,
                'target_type' => Notification::TARGET_TYPE_ADMIN,
                'building_id' => $alert->building_id,
                'target_admin_id' => $adminId,
                'status' => Notification::STATUS_SENT,
                'published_at' => now(),
                'created_by' => $creatorId,
            ]);

            broadcast(new NotificationSent($notification));
        });
    }

    private function sendDiscordAlert(FireSafetyAlert $alert): array
    {
        $webhookUrl = config('services.fire_safety.discord_webhook_url');

        if (blank($webhookUrl)) {
            Log::warning('Thiếu Discord webhook AI camera', [
                'fire_safety_alert_id' => $alert->id,
            ]);

            return [
                'sent' => false,
                'status' => null,
                'message' => 'Thiếu FIRE_DISCORD_WEBHOOK_URL',
                'has_snapshot' => filled($alert->snapshot_path),
            ];
        }

        $content = implode("\n", [
            '🚨 **BÁO ĐỘNG ĐỎ AI CAMERA - STAYHUB**',
            '**Tòa nhà:** ' . ($alert->building?->name ?? 'Không rõ'),
            '**Camera:** ' . ($alert->securityCamera?->name ?? 'Không rõ'),
            '**Vị trí:** ' . ($alert->securityCamera?->location ?? 'Không rõ'),
            '**Mức nguy cơ:** ' . (FireSafetyAlert::RISK_LABELS[$alert->risk_level] ?? 'Cảnh báo'),
            '**Độ tin cậy:** ' . round(((float) $alert->confidence) * 100) . '%',
            '**AI mô tả:** ' . ($alert->ai_summary ?: 'Không có mô tả'),
            '**Thời gian:** ' . optional($alert->created_at)->format('d/m/Y H:i:s'),
        ]);

        try {
            $request = Http::timeout(15)->asMultipart();

            $snapshotPath = ImageHelper::toAbsolutePath($alert->snapshot_path);

            if ($snapshotPath && is_file($snapshotPath)) {
                $request = $request->attach(
                    'file',
                    file_get_contents($snapshotPath),
                    'fire-alert-' . $alert->id . '.jpg'
                );
            }

            $response = $request->post((string) $webhookUrl, ['content' => $content]);

            if (! $response->successful()) {
                Log::warning('Discord webhook AI camera trả lỗi', [
                    'fire_safety_alert_id' => $alert->id,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 300),
                ]);

                return [
                    'sent' => false,
                    'status' => $response->status(),
                    'message' => Str::limit($response->body(), 300),
                    'has_snapshot' => filled($alert->snapshot_path),
                ];
            }

            Log::info('Đã gửi cảnh báo Discord AI camera', [
                'fire_safety_alert_id' => $alert->id,
                'status' => $response->status(),
                'has_snapshot' => filled($alert->snapshot_path),
            ]);

            return [
                'sent' => true,
                'status' => $response->status(),
                'message' => 'Discord đã nhận cảnh báo',
                'has_snapshot' => filled($alert->snapshot_path),
            ];
        } catch (\Exception $e) {
            Log::warning('Không gửi được cảnh báo Discord AI camera', [
                'fire_safety_alert_id' => $alert->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'status' => null,
                'message' => $e->getMessage(),
                'has_snapshot' => filled($alert->snapshot_path),
            ];
        }
    }

    private function attachDiscordResult(FireSafetyAlert $alert, array $discord): FireSafetyAlert
    {
        $payload = $alert->raw_ai_payload ?? [];
        $payload['discord'] = $discord;
        $alert->forceFill(['raw_ai_payload' => $payload])->save();

        return $alert->fresh(['securityCamera', 'building', 'acknowledger', 'resolver']);
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
            $payload['is_ai_enabled'] = $payload['is_ai_enabled'] ?? true;
            $payload['frame_interval_seconds'] = $payload['frame_interval_seconds'] ?? (int) config('services.fire_safety.analysis_window_seconds', 2);
            $payload['frames_per_batch'] = $payload['frames_per_batch'] ?? (int) config('services.fire_safety.frame_count', 3);
            $payload['alert_cooldown_seconds'] = $payload['alert_cooldown_seconds'] ?? (int) config('services.fire_safety.alert_cooldown_seconds', 60);
            $payload['status'] = $payload['status'] ?? SecurityCamera::STATUS_ACTIVE;
        }

        $payload['updated_by'] = $admin->id;

        return $payload;
    }

}
