<?php

namespace Tests\Feature\Search;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Region;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MeilisearchConfigurationTest extends TestCase
{
    public function test_every_searchable_model_has_complete_meilisearch_settings(): void
    {
        foreach ([Region::class, Building::class, Tenant::class, Invoice::class] as $modelClass) {
            $model = new $modelClass();
            $settings = config("scout.meilisearch.index-settings.{$modelClass}");

            $this->assertIsArray($settings, "Thiếu cấu hình Meilisearch cho {$modelClass}.");
            $this->assertNotEmpty($settings['searchableAttributes'] ?? [], "Thiếu searchableAttributes cho {$modelClass}.");
            $this->assertArrayHasKey('filterableAttributes', $settings, "Thiếu filterableAttributes cho {$modelClass}.");
            $this->assertArrayHasKey('sortableAttributes', $settings, "Thiếu sortableAttributes cho {$modelClass}.");

            $documentFields = array_keys($model->toSearchableArray());

            foreach (['searchableAttributes', 'filterableAttributes', 'sortableAttributes'] as $setting) {
                $missingFields = array_diff($settings[$setting], $documentFields);
                $this->assertSame([], array_values($missingFields), "{$setting} của {$modelClass} chứa field không được index.");
            }
        }
    }

    public function test_search_setup_command_is_registered(): void
    {
        $this->assertArrayHasKey('search:setup', Artisan::all());
    }
}
