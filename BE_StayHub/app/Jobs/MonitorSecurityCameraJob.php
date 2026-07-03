<?php

namespace App\Jobs;

use App\Models\SecurityCamera;
use App\Services\FireSafety\FireSafetyCameraAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MonitorSecurityCameraJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 150;

    public function __construct(
        public int $cameraId,
        public string $monitoringToken,
    ) {
        $this->onQueue('fire-safety');
    }

    public function handle(FireSafetyCameraAnalyzer $analyzer): void
    {
        $camera = SecurityCamera::query()->with('building.manager')->find($this->cameraId);

        if (! $this->canContinue($camera)) {
            return;
        }

        $intervalSeconds = max(5, (int) $camera->frame_interval_seconds);
        $lock = Cache::lock('fire-safety:camera:' . $camera->id, max(30, $intervalSeconds + $this->timeout));

        if (! $lock->get()) {
            return;
        }

        try {
            $result = $analyzer->analyze($camera);
            $freshCamera = SecurityCamera::query()->find($camera->id);

            if (! $this->canContinue($freshCamera)) {
                return;
            }

            if (! ($result['success'] ?? false)) {
                $this->markError($freshCamera, (string) ($result['message'] ?? 'Không thể phân tích camera.'), 30);
                return;
            }

            $alert = $result['alert'] ?? null;
            $analysis = $result['analysis'] ?? [];
            $risk = $analysis['risk_level'] ?? 'safe';
            $hasVisualSignal = (bool) ($analysis['detected_fire'] ?? false)
                || (bool) ($analysis['detected_smoke'] ?? false)
                || (bool) ($analysis['detected_smoking'] ?? false);
            $message = $alert
                ? "Đã gửi cảnh báo {$risk}"
                : ($hasVisualSignal ? "Phát hiện nguy cơ {$risk}, đang trong cooldown cảnh báo" : "An toàn ({$risk})");

            $freshCamera->forceFill([
                'last_scanned_at' => now(),
                'next_scan_at' => now()->addSeconds($intervalSeconds),
                'last_scan_status' => ($alert || $hasVisualSignal) ? SecurityCamera::SCAN_STATUS_ALERT : SecurityCamera::SCAN_STATUS_SAFE,
                'last_scan_message' => Str::limit($message, 500, ''),
                'monitoring_error_count' => 0,
            ])->save();

            self::dispatch($freshCamera->id, (string) $freshCamera->monitoring_token)->delay(now()->addSeconds($intervalSeconds));
        } catch (\Throwable $e) {
            Log::warning('AI camera monitoring job lỗi', [
                'security_camera_id' => $camera->id,
                'message' => $e->getMessage(),
            ]);

            $freshCamera = SecurityCamera::query()->find($camera->id);

            if ($this->canContinue($freshCamera)) {
                $this->markError($freshCamera, 'Không quét được camera: ' . $e->getMessage(), 30);
            }
        } finally {
            optional($lock)->release();
        }
    }

    private function canContinue(?SecurityCamera $camera): bool
    {
        return $camera !== null
            && (int) $camera->status === SecurityCamera::STATUS_ACTIVE
            && (bool) $camera->is_ai_enabled
            && filled($camera->monitoring_token)
            && hash_equals((string) $camera->monitoring_token, $this->monitoringToken);
    }

    private function markError(SecurityCamera $camera, string $message, int $delaySeconds): void
    {
        $camera->forceFill([
            'last_scanned_at' => now(),
            'next_scan_at' => now()->addSeconds($delaySeconds),
            'last_scan_status' => SecurityCamera::SCAN_STATUS_ERROR,
            'last_scan_message' => Str::limit($message, 500, ''),
            'monitoring_error_count' => (int) $camera->monitoring_error_count + 1,
        ])->save();

        self::dispatch($camera->id, (string) $camera->monitoring_token)->delay(now()->addSeconds($delaySeconds));
    }
}
