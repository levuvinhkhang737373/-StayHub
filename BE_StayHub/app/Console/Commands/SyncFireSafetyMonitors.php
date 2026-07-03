<?php

namespace App\Console\Commands;

use App\Jobs\MonitorSecurityCameraJob;
use App\Models\SecurityCamera;
use Illuminate\Console\Command;

class SyncFireSafetyMonitors extends Command
{
    protected $signature = 'fire-safety:sync-monitors';
    protected $description = 'Khôi phục các job giám sát AI camera 24/24 bị thiếu hoặc quá hạn.';

    public function handle(): int
    {
        $count = 0;

        SecurityCamera::query()
            ->where('is_ai_enabled', true)
            ->where('status', SecurityCamera::STATUS_ACTIVE)
            ->whereNotNull('monitoring_token')
            ->where(function ($query): void {
                $query->whereNull('next_scan_at')
                    ->orWhere('next_scan_at', '<=', now());
            })
            ->orderBy('id')
            ->chunkById(100, function ($cameras) use (&$count): void {
                foreach ($cameras as $camera) {
                    MonitorSecurityCameraJob::dispatch($camera->id, (string) $camera->monitoring_token);
                    $camera->forceFill(['next_scan_at' => now()->addSeconds(max(5, (int) $camera->frame_interval_seconds))])->save();
                    $count++;
                }
            });

        $this->info("Đã đồng bộ {$count} camera giám sát AI 24/24.");

        return self::SUCCESS;
    }
}
