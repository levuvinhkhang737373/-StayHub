<?php

namespace Tests\Feature\Admin;

use App\Jobs\MonitorSecurityCameraJob;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use App\Models\SecurityCamera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityCameraMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $manager;
    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'username' => 'superadmin_camera',
            'full_name' => 'Super Admin Camera',
            'email' => 'superadmin_camera@stayhub.local',
            'phone' => '0900000001',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
        ]);

        $this->manager = Admin::create([
            'username' => 'manager_camera',
            'full_name' => 'Manager Camera',
            'email' => 'manager_camera@stayhub.local',
            'phone' => '0900000002',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_BUILDING_MANAGER,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_FEMALE,
        ]);

        $region = Region::create([
            'name' => 'Region Camera',
            'code' => 'REG_CAMERA',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->building = Building::create([
            'name' => 'Building Camera',
            'slug' => 'building-camera',
            'address' => '123 Camera St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->manager->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);
    }

    public function test_super_admin_can_enable_monitoring_and_dispatch_job(): void
    {
        Queue::fake();
        $camera = $this->camera(['is_ai_enabled' => false]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/api/v1/admin/security-cameras/{$camera->id}/monitoring", ['enabled' => true]);

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('result.is_ai_enabled', true);

        $this->assertDatabaseHas('security_cameras', [
            'id' => $camera->id,
            'is_ai_enabled' => true,
            'status' => SecurityCamera::STATUS_ACTIVE,
        ]);

        $camera->refresh();
        $this->assertNotNull($camera->monitoring_token);
        $this->assertNotNull($camera->monitoring_started_at);
        $this->assertNull($camera->monitoring_stopped_at);

        Queue::assertPushed(MonitorSecurityCameraJob::class);
    }

    public function test_inactive_camera_cannot_enable_monitoring(): void
    {
        Queue::fake();
        $camera = $this->camera(['status' => SecurityCamera::STATUS_INACTIVE]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/api/v1/admin/security-cameras/{$camera->id}/monitoring", ['enabled' => true]);

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_non_super_admin_cannot_toggle_monitoring(): void
    {
        Queue::fake();
        $camera = $this->camera();

        $response = $this->actingAs($this->manager, 'admin')
            ->patchJson("/api/v1/admin/security-cameras/{$camera->id}/monitoring", ['enabled' => true]);

        $response->assertStatus(403);
        Queue::assertNothingPushed();
    }

    public function test_bulk_monitoring_skips_inactive_cameras(): void
    {
        Queue::fake();
        $activeCamera = $this->camera(['name' => 'Active Camera']);
        $inactiveCamera = $this->camera(['name' => 'Inactive Camera', 'status' => SecurityCamera::STATUS_INACTIVE]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson('/api/v1/admin/security-cameras/monitoring/bulk', [
                'enabled' => true,
                'building_id' => $this->building->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('result.updated_count', 1);
        $response->assertJsonPath('result.skipped_count', 1);

        $this->assertTrue($activeCamera->fresh()->is_ai_enabled);
        $this->assertFalse($inactiveCamera->fresh()->is_ai_enabled);
        Queue::assertPushed(MonitorSecurityCameraJob::class, 1);
    }

    public function test_super_admin_can_disable_monitoring_and_clear_runtime_state(): void
    {
        Queue::fake();
        $camera = $this->camera([
            'is_ai_enabled' => true,
            'monitoring_token' => 'token-running',
            'monitoring_started_at' => now(),
            'next_scan_at' => now()->addMinute(),
            'last_scan_message' => 'Đang chạy.',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/api/v1/admin/security-cameras/{$camera->id}/monitoring", ['enabled' => false]);

        $response->assertOk();
        $response->assertJsonPath('result.is_ai_enabled', false);

        $camera->refresh();
        $this->assertFalse($camera->is_ai_enabled);
        $this->assertNull($camera->monitoring_token);
        $this->assertNull($camera->next_scan_at);
        $this->assertNotNull($camera->monitoring_stopped_at);
        Queue::assertNothingPushed();
    }

    public function test_manager_can_manual_scan_active_camera_when_monitoring_is_off(): void
    {
        config(['services.ai_service.url' => 'http://ai-service.test']);
        Http::fake([
            'ai-service.test/api/v1/fire-safety/analyze-stream' => Http::response([
                'risk_level_code' => 3,
                'risk_level' => 'danger',
                'detected_fire' => true,
                'detected_smoke' => false,
                'detected_smoking' => false,
                'confidence' => 0.91,
                'summary' => 'Có lửa ở sảnh.',
            ]),
        ]);
        Queue::fake([MonitorSecurityCameraJob::class]);
        $camera = $this->camera(['is_ai_enabled' => false]);

        $response = $this->actingAs($this->manager, 'admin')
            ->postJson("/api/v1/admin/security-cameras/{$camera->id}/analyze");

        $response->assertOk();
        $response->assertJsonPath('result.alert.detected_fire', true);
        $this->assertDatabaseHas('fire_safety_alerts', [
            'security_camera_id' => $camera->id,
            'building_id' => $this->building->id,
            'detected_fire' => true,
        ]);
        $this->assertFalse($camera->fresh()->is_ai_enabled);
        Queue::assertNotPushed(MonitorSecurityCameraJob::class);
    }

    private function camera(array $overrides = []): SecurityCamera
    {
        return SecurityCamera::create(array_merge([
            'building_id' => $this->building->id,
            'name' => 'Camera Test',
            'location' => 'Lobby',
            'source_type' => SecurityCamera::SOURCE_TYPE_MJPEG,
            'stream_url' => 'http://127.0.0.1:8080/video',
            'is_ai_enabled' => false,
            'frame_interval_seconds' => 5,
            'frames_per_batch' => 3,
            'alert_cooldown_seconds' => 60,
            'status' => SecurityCamera::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ], $overrides));
    }
}
