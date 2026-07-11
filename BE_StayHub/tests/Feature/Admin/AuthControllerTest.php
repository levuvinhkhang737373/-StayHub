<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_me_returns_all_buildings_for_super_admin_realtime_channels(): void
    {
        $superAdmin = $this->createAdmin('super_realtime', Admin::ROLE_SUPER_ADMIN);
        $manager = $this->createAdmin('manager_realtime', Admin::ROLE_BUILDING_MANAGER);
        $region = $this->createRegion($superAdmin);

        $firstBuilding = $this->createBuilding($region, $manager, 'Realtime Building A');
        $secondBuilding = $this->createBuilding($region, $manager, 'Realtime Building B');

        $response = $this->actingAs($superAdmin, 'admin')->getJson('/api/v1/admin/me');

        $response->assertOk();
        $buildingIds = collect($response->json('result.managed_buildings'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([
            $firstBuilding->id,
            $secondBuilding->id,
        ], $buildingIds);
    }

    public function test_admin_me_returns_only_managed_buildings_for_building_manager_realtime_channels(): void
    {
        $superAdmin = $this->createAdmin('super_scope', Admin::ROLE_SUPER_ADMIN);
        $manager = $this->createAdmin('manager_scope', Admin::ROLE_BUILDING_MANAGER);
        $otherManager = $this->createAdmin('other_manager_scope', Admin::ROLE_BUILDING_MANAGER);
        $region = $this->createRegion($superAdmin);

        $managedBuilding = $this->createBuilding($region, $manager, 'Managed Realtime Building');
        $this->createBuilding($region, $otherManager, 'Other Realtime Building');

        $response = $this->actingAs($manager, 'admin')->getJson('/api/v1/admin/me');

        $response->assertOk();
        $buildingIds = collect($response->json('result.managed_buildings'))->pluck('id')->all();

        $this->assertSame([$managedBuilding->id], $buildingIds);
    }

    public function test_broadcast_auth_allows_super_admin_to_subscribe_admin_building_channels(): void
    {
        $this->useRealBroadcasterForAuth();

        $superAdmin = $this->createAdmin('super_broadcast', Admin::ROLE_SUPER_ADMIN);
        $manager = $this->createAdmin('manager_broadcast', Admin::ROLE_BUILDING_MANAGER);
        $region = $this->createRegion($superAdmin);
        $building = $this->createBuilding($region, $manager, 'Broadcast Auth Building');

        $response = $this->actingAs($superAdmin, 'admin')->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-admin-building.'.$building->id,
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_broadcast_auth_rejects_manager_from_other_admin_building_channels(): void
    {
        $this->useRealBroadcasterForAuth();

        $superAdmin = $this->createAdmin('super_broadcast_reject', Admin::ROLE_SUPER_ADMIN);
        $manager = $this->createAdmin('manager_broadcast_reject', Admin::ROLE_BUILDING_MANAGER);
        $otherManager = $this->createAdmin('other_manager_broadcast_reject', Admin::ROLE_BUILDING_MANAGER);
        $region = $this->createRegion($superAdmin);
        $otherBuilding = $this->createBuilding($region, $otherManager, 'Other Broadcast Auth Building');

        $response = $this->actingAs($manager, 'admin')->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-admin-building.'.$otherBuilding->id,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_profile_can_update_email(): void
    {
        $admin = $this->createAdmin('profile_email_admin', Admin::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin, 'admin')->patchJson('/api/v1/admin/profile', [
            'full_name' => 'Profile Email Admin',
            'phone' => '0912345678',
            'email' => 'profile-email-updated@stayhub.local',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.admin.email', 'profile-email-updated@stayhub.local');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'email' => 'profile-email-updated@stayhub.local',
        ]);
    }

    public function test_admin_profile_rejects_duplicate_email(): void
    {
        $admin = $this->createAdmin('profile_email_owner', Admin::ROLE_SUPER_ADMIN);
        $otherAdmin = $this->createAdmin('profile_email_other', Admin::ROLE_BUILDING_MANAGER);

        $response = $this->actingAs($admin, 'admin')->patchJson('/api/v1/admin/profile', [
            'full_name' => 'Profile Email Owner',
            'phone' => '0912345678',
            'email' => $otherAdmin->email,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('result.email.0', 'Email này đã được sử dụng bởi tài khoản khác.');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'email' => 'profile_email_owner@stayhub.local',
        ]);
    }

    public function test_faceid_register_requires_at_least_two_images(): void
    {
        $admin = $this->createAdmin('face_register_min_images', Admin::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/face-id/register', [
            'images' => [UploadedFile::fake()->image('face-1.jpg', 640, 480)],
        ]);

        $response->assertUnprocessable();
    }

    public function test_face_login_rejects_more_than_one_image(): void
    {
        $response = $this->postJson('/api/v1/admin/face-login', [
            'images' => [
                UploadedFile::fake()->image('face-1.jpg', 640, 480),
                UploadedFile::fake()->image('face-2.jpg', 640, 480),
            ],
        ]);

        $response->assertUnprocessable();
    }

    public function test_face_login_uses_clear_vietnamese_message_when_face_is_not_matched(): void
    {
        Http::fake([
            config('services.ai_service.url').'/api/v1/extract*' => Http::response([
                'embedding' => array_fill(0, 128, 0.2),
            ], 200),
            config('services.qdrant.url').'*' => Http::response(['result' => []], 200),
        ]);

        $response = $this->postJson('/api/v1/admin/face-login', [
            'images' => [UploadedFile::fake()->image('face-1.jpg', 640, 480)],
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Không nhận diện được khuôn mặt. Vui lòng thử lại hoặc đăng nhập bằng mật khẩu.');
    }

    public function test_faceid_register_does_not_expose_storage_or_vector_errors(): void
    {
        Storage::fake('s3');
        Http::fake([
            config('services.ai_service.url').'/api/v1/extract*' => Http::response([
                'embedding' => array_fill(0, 128, 0.2),
            ], 200),
            config('services.qdrant.url').'*' => Http::response(['status' => ['error' => 'Qdrant unavailable']], 500),
        ]);

        $admin = $this->createAdmin('face_register_safe_error', Admin::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/face-id/register', [
            'images' => [
                UploadedFile::fake()->image('face-1.jpg', 640, 480),
                UploadedFile::fake()->image('face-2.jpg', 640, 480),
                UploadedFile::fake()->image('face-3.jpg', 640, 480),
            ],
        ]);

        $response
            ->assertStatus(500)
            ->assertJsonPath('message', 'Chưa thể đăng ký FaceID. Vui lòng thử lại.');
    }

    public function test_face_login_does_not_expose_vector_search_errors(): void
    {
        Http::fake([
            config('services.ai_service.url').'/api/v1/extract*' => Http::response([
                'embedding' => array_fill(0, 128, 0.2),
            ], 200),
            config('services.qdrant.url').'*' => Http::response(['status' => ['error' => 'Qdrant unavailable']], 500),
        ]);

        $response = $this->postJson('/api/v1/admin/face-login', [
            'images' => [UploadedFile::fake()->image('face-1.jpg', 640, 480)],
        ]);

        $response
            ->assertStatus(500)
            ->assertJsonPath('message', 'Không nhận diện được khuôn mặt. Vui lòng thử lại hoặc đăng nhập bằng mật khẩu.');
    }

    private function useRealBroadcasterForAuth(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-reverb-key',
            'broadcasting.connections.reverb.secret' => 'test-reverb-secret',
            'broadcasting.connections.reverb.app_id' => 'test-reverb-app',
        ]);

        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
    }

    private function createAdmin(string $username, int $role): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'full_name' => 'Realtime Test Admin',
            'email' => $username.'@stayhub.local',
            'phone' => '0900000000',
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Realtime test address',
        ]);
    }

    private function createRegion(Admin $admin): Region
    {
        return Region::query()->create([
            'name' => 'Realtime Region',
            'code' => 'RT-'.uniqid(),
            'created_by' => $admin->id,
        ]);
    }

    private function createBuilding(Region $region, Admin $manager, string $name): Building
    {
        return Building::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'address' => 'Realtime address',
            'region_id' => $region->id,
            'manager_admin_id' => $manager->id,
            'created_by' => $manager->id,
            'status' => Building::STATUS_ACTIVE,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
        ]);
    }
}
