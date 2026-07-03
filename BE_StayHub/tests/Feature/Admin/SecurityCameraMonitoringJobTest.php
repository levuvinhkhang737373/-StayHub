<?php

namespace Tests\Feature\Admin;

use App\Jobs\MonitorSecurityCameraJob;
use App\Models\Admin;
use App\Models\Building;
use App\Models\FireSafetyAlert;
use App\Models\Region;
use App\Models\SecurityCamera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityCameraMonitoringJobTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai_service.url' => 'http://ai-service.test']);

        $this->superAdmin = Admin::create([
            'username' => 'superadmin_camera_job',
            'full_name' => 'Super Admin Camera Job',
            'email' => 'superadmin_camera_job@stayhub.local',
            'phone' => '0900000011',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
        ]);

        $region = Region::create([
            'name' => 'Region Camera Job',
            'code' => 'REG_CAMERA_JOB',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->building = Building::create([
            'name' => 'Building Camera Job',
            'slug' => 'building-camera-job',
            'address' => '123 Camera Job St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);
    }

    public function test_job_marks_safe_scan_and_reschedules(): void
    {
        Http::fake([
            'ai-service.test/api/v1/fire-safety/analyze-stream' => Http::response([
                'risk_level_code' => 1,
                'risk_level' => 'safe',
                'detected_fire' => false,
                'detected_smoke' => false,
                'detected_smoking' => false,
                'confidence' => 0.1,
                'summary' => 'An toàn',
            ]),
        ]);
        Queue::fake();
        $camera = $this->monitoringCamera();

        app(MonitorSecurityCameraJob::class, [
            'cameraId' => $camera->id,
            'monitoringToken' => 'token-safe',
        ])->handle(app(\App\Services\FireSafety\FireSafetyCameraAnalyzer::class));

        $camera->refresh();
        $this->assertSame(SecurityCamera::SCAN_STATUS_SAFE, $camera->last_scan_status);
        $this->assertSame(0, $camera->monitoring_error_count);
        $this->assertNotNull($camera->last_scanned_at);
        $this->assertNotNull($camera->next_scan_at);
        Queue::assertPushed(MonitorSecurityCameraJob::class);
    }

    public function test_stale_job_does_not_scan_or_reschedule(): void
    {
        Http::fake();
        Queue::fake();
        $camera = $this->monitoringCamera(['monitoring_token' => 'fresh-token']);

        app(MonitorSecurityCameraJob::class, [
            'cameraId' => $camera->id,
            'monitoringToken' => 'stale-token',
        ])->handle(app(\App\Services\FireSafety\FireSafetyCameraAnalyzer::class));

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_job_marks_error_scan_and_retries(): void
    {
        Http::fake([
            'ai-service.test/api/v1/fire-safety/analyze-stream' => Http::response(['detail' => 'AI service quá tải'], 503),
        ]);
        Queue::fake();
        $camera = $this->monitoringCamera();

        app(MonitorSecurityCameraJob::class, [
            'cameraId' => $camera->id,
            'monitoringToken' => 'token-safe',
        ])->handle(app(\App\Services\FireSafety\FireSafetyCameraAnalyzer::class));

        $camera->refresh();
        $this->assertSame(SecurityCamera::SCAN_STATUS_ERROR, $camera->last_scan_status);
        $this->assertSame(1, $camera->monitoring_error_count);
        $this->assertNotNull($camera->next_scan_at);
        Queue::assertPushed(MonitorSecurityCameraJob::class);
    }

    public function test_job_creates_alert_and_reschedules_when_ai_detects_risk(): void
    {
        Http::fake([
            'ai-service.test/api/v1/fire-safety/analyze-stream' => Http::response([
                'risk_level_code' => FireSafetyAlert::RISK_DANGER,
                'risk_level' => 'danger',
                'detected_fire' => true,
                'detected_smoke' => true,
                'detected_smoking' => false,
                'confidence' => 0.93,
                'summary' => 'Phát hiện lửa và khói.',
            ]),
        ]);
        Queue::fake();
        $camera = $this->monitoringCamera();

        app(MonitorSecurityCameraJob::class, [
            'cameraId' => $camera->id,
            'monitoringToken' => 'token-safe',
        ])->handle(app(\App\Services\FireSafety\FireSafetyCameraAnalyzer::class));

        $camera->refresh();
        $this->assertSame(SecurityCamera::SCAN_STATUS_ALERT, $camera->last_scan_status);
        $this->assertSame(0, $camera->monitoring_error_count);
        $this->assertDatabaseHas('fire_safety_alerts', [
            'security_camera_id' => $camera->id,
            'building_id' => $this->building->id,
            'detected_fire' => true,
            'detected_smoke' => true,
            'status' => FireSafetyAlert::STATUS_OPEN,
        ]);
        Queue::assertPushed(MonitorSecurityCameraJob::class);
    }

    private function monitoringCamera(array $overrides = []): SecurityCamera
    {
        return SecurityCamera::create(array_merge([
            'building_id' => $this->building->id,
            'name' => 'Camera Job Test',
            'location' => 'Lobby',
            'source_type' => SecurityCamera::SOURCE_TYPE_MJPEG,
            'stream_url' => 'http://127.0.0.1:8080/video',
            'is_ai_enabled' => true,
            'monitoring_token' => 'token-safe',
            'monitoring_started_at' => now(),
            'next_scan_at' => now(),
            'frame_interval_seconds' => 5,
            'frames_per_batch' => 3,
            'alert_cooldown_seconds' => 60,
            'status' => SecurityCamera::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ], $overrides));
    }
}
