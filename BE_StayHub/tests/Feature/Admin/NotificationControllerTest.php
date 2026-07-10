<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Notification;
use App\Models\Region;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_building_manager_does_not_see_global_or_other_super_admin_notifications(): void
    {
        $superAdmin = $this->createAdmin('notification_super', Admin::ROLE_SUPER_ADMIN);
        $manager = $this->createAdmin('notification_manager', Admin::ROLE_BUILDING_MANAGER);
        $otherManager = $this->createAdmin('notification_other_manager', Admin::ROLE_BUILDING_MANAGER);

        $region = Region::query()->create([
            'name' => 'Notification Region',
            'code' => 'notification-region',
            'created_by' => $superAdmin->id,
        ]);

        $managedBuilding = $this->createBuilding($region, $manager, 'Managed Notification Building');
        $otherBuilding = $this->createBuilding($region, $otherManager, 'Other Notification Building');

        $globalNotification = $this->createNotification($superAdmin, [
            'title' => 'Superadmin global notice',
            'target_type' => Notification::TARGET_TYPE_ALL,
            'building_id' => null,
        ]);

        $otherBuildingNotification = $this->createNotification($superAdmin, [
            'title' => 'Other building notice',
            'target_type' => Notification::TARGET_TYPE_BUILDING,
            'building_id' => $otherBuilding->id,
        ]);

        $managedBuildingNotification = $this->createNotification($superAdmin, [
            'title' => 'Managed building notice',
            'target_type' => Notification::TARGET_TYPE_BUILDING,
            'building_id' => $managedBuilding->id,
        ]);

        $ownNotification = $this->createNotification($manager, [
            'title' => 'Manager own notice',
            'target_type' => Notification::TARGET_TYPE_ALL,
            'building_id' => null,
        ]);

        $response = $this->actingAs($manager, 'admin')->getJson('/api/v1/admin/notifications?per_page=20');

        $response->assertOk();

        $visibleIds = collect($response->json('result.data'))->pluck('id')->all();

        $this->assertContains($managedBuildingNotification->id, $visibleIds);
        $this->assertContains($ownNotification->id, $visibleIds);
        $this->assertNotContains($globalNotification->id, $visibleIds);
        $this->assertNotContains($otherBuildingNotification->id, $visibleIds);
    }

    public function test_admin_target_notifications_broadcast_only_to_authorized_admin_channels(): void
    {
        $superAdmin = $this->createAdmin('notification_broadcast_super', Admin::ROLE_SUPER_ADMIN);
        $manager = $this->createAdmin('notification_broadcast_manager', Admin::ROLE_BUILDING_MANAGER);
        $region = Region::query()->create([
            'name' => 'Notification Broadcast Region',
            'code' => 'notification-broadcast-region',
            'created_by' => $superAdmin->id,
        ]);
        $building = $this->createBuilding($region, $manager, 'Broadcast Notification Building');

        $globalNotification = $this->createNotification($superAdmin, [
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'building_id' => null,
        ]);
        $buildingNotification = $this->createNotification($superAdmin, [
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'building_id' => $building->id,
        ]);

        $this->assertSame(['private-admin-super'], $this->broadcastChannelNames($globalNotification));
        $this->assertSame(['private-admin-building.'.$building->id], $this->broadcastChannelNames($buildingNotification));
    }

    private function createAdmin(string $username, int $role): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'full_name' => str($username)->replace('_', ' ')->title()->toString(),
            'email' => $username.'@stayhub.local',
            'phone' => '09'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Notification test address',
        ]);
    }

    private function createBuilding(Region $region, Admin $manager, string $name): Building
    {
        return Building::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'address' => 'Notification building address',
            'region_id' => $region->id,
            'manager_admin_id' => $manager->id,
            'created_by' => $manager->id,
            'status' => Building::STATUS_ACTIVE,
            'gender_policy' => Building::GENDER_POLICY_MIXED,
        ]);
    }

    private function createNotification(Admin $creator, array $attributes): Notification
    {
        return Notification::query()->create(array_merge([
            'title' => 'Notification test',
            'content' => 'Notification test content',
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_ALL,
            'building_id' => null,
            'room_id' => null,
            'tenant_id' => null,
            'target_admin_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $creator->id,
        ], $attributes));
    }

    private function broadcastChannelNames(Notification $notification): array
    {
        return collect((new \App\Events\NotificationSent($notification))->broadcastOn())
            ->map(fn (PrivateChannel $channel): string => $channel->name)
            ->values()
            ->all();
    }
}
