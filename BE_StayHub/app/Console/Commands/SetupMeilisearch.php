<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Region;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SetupMeilisearch extends Command
{
    protected $signature = 'search:setup {--flush : Xóa document cũ trước khi import lại}';

    protected $description = 'Đồng bộ cấu hình và dữ liệu cho các index Meilisearch của StayHub';

    public function handle(): int
    {
        if (config('scout.driver') !== 'meilisearch') {
            $this->error('SCOUT_DRIVER phải là meilisearch để chạy lệnh này.');

            return self::FAILURE;
        }

        $settingsSynced = false;
        $this->components->task('Đồng bộ cấu hình index', function () use (&$settingsSynced): bool {
            $settingsSynced = $this->callSilently('scout:sync-index-settings') === self::SUCCESS;

            return $settingsSynced;
        });

        if (! $settingsSynced) {
            $this->error('Không thể đồng bộ cấu hình Meilisearch. Dữ liệu hiện tại được giữ nguyên.');

            return self::FAILURE;
        }

        if ($this->option('flush') && ! $this->flushIndexes()) {
            return self::FAILURE;
        }

        foreach ($this->searchableModels() as $modelClass) {
            $exitCode = $this->call('scout:import', ['model' => $modelClass]);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Không thể import index cho {$modelClass}.");

                return self::FAILURE;
            }
        }

        $this->info('Đã đồng bộ đầy đủ cấu hình và dữ liệu Meilisearch.');

        return self::SUCCESS;
    }

    private function flushIndexes(): bool
    {
        foreach ($this->searchableModels() as $modelClass) {
            if ($this->call('scout:flush', ['model' => $modelClass]) !== self::SUCCESS) {
                $this->error("Không thể xóa index cũ của {$modelClass}.");

                return false;
            }
        }

        return true;
    }

    private function searchableModels(): array
    {
        return [Region::class, Building::class, Tenant::class, Invoice::class];
    }
}
