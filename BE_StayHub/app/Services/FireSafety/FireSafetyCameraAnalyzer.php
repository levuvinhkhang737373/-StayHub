<?php

namespace App\Services\FireSafety;

use App\Events\NotificationSent;
use App\Helpers\ImageHelper;
use App\Models\Admin;
use App\Models\FireSafetyAlert;
use App\Models\Notification;
use App\Models\SecurityCamera;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FireSafetyCameraAnalyzer
{
    public function analyze(SecurityCamera $camera, ?Admin $triggeredBy = null): array
    {
        $analysis = $this->analyzeCameraStream($camera);

        if (! ($analysis['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $analysis['message'] ?? 'Không thể phân tích camera',
                'analysis' => $this->publicAnalysisPayload($analysis),
                'alert' => null,
                'discord' => null,
            ];
        }

        $alert = null;
        $discord = null;

        if ($this->shouldCreateAlert($analysis, $camera)) {
            $alert = $this->createAlertFromAnalysis($camera, $analysis, $triggeredBy);
            $this->notifyFireAlert($alert, $triggeredBy);
            $discord = $this->sendDiscordAlert($alert);
            $alert = $this->attachDiscordResult($alert, $discord);
        }

        return [
            'success' => true,
            'message' => $alert
                ? 'AI phát hiện nguy cơ và đã gửi cảnh báo'
                : 'AI đã phân tích camera, chưa phát hiện nguy cơ vượt ngưỡng',
            'analysis' => $this->publicAnalysisPayload($analysis),
            'alert' => $alert,
            'discord' => $discord,
        ];
    }

    public function testStream(SecurityCamera $camera): array
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

    private function createAlertFromAnalysis(SecurityCamera $camera, array $analysis, ?Admin $triggeredBy): FireSafetyAlert
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
                'triggered_by_admin_id' => $triggeredBy?->id,
                'triggered_by' => $triggeredBy ? 'admin' : 'monitoring_job',
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

    private function notifyFireAlert(FireSafetyAlert $alert, ?Admin $triggeredBy): void
    {
        $building = $alert->building;
        $adminIds = Admin::query()
            ->where('status', Admin::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($building): void {
                $query->where('role', Admin::ROLE_SUPER_ADMIN);

                if ($building?->manager_admin_id) {
                    $query->orWhere('id', $building->manager_admin_id);
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

        $adminIds->each(function (int $adminId) use ($alert, $triggeredBy, $title, $content): void {
            $notification = Notification::query()->create([
                'title' => $title,
                'content' => $content,
                'notification_type' => Notification::NOTIFICATION_TYPE_WARNING,
                'target_type' => Notification::TARGET_TYPE_ADMIN,
                'action_url' => '/admin/fire-safety?panel=alerts&alert_id=' . $alert->id,
                'building_id' => $alert->building_id,
                'target_admin_id' => $adminId,
                'status' => Notification::STATUS_SENT,
                'published_at' => now(),
                'created_by' => $triggeredBy?->id,
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
}
