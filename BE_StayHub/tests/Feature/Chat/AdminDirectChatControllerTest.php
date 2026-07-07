<?php

namespace Tests\Feature\Chat;

use App\Events\ChatConversationRead;
use App\Events\ChatMessageSent;
use App\Events\NotificationSent;
use App\Models\Admin;
use App\Models\Building;
use App\Models\ChatConversation;
use App\Models\Notification;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminDirectChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $otherSuperAdmin;
    private Admin $manager;
    private Admin $otherManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->createAdmin('super_direct', 'super_direct@stayhub.local', '0911000001', Admin::ROLE_SUPER_ADMIN);
        $this->otherSuperAdmin = $this->createAdmin('other_super_direct', 'other_super_direct@stayhub.local', '0911000002', Admin::ROLE_SUPER_ADMIN);
        $this->manager = $this->createAdmin('manager_direct', 'manager_direct@stayhub.local', '0911000003', Admin::ROLE_BUILDING_MANAGER);
        $this->otherManager = $this->createAdmin('other_manager_direct', 'other_manager_direct@stayhub.local', '0911000004', Admin::ROLE_BUILDING_MANAGER);

        $region = Region::query()->create([
            'name' => 'Direct Region',
            'code' => 'DIRECT_REGION',
            'created_by' => $this->superAdmin->id,
        ]);

        Building::query()->create([
            'name' => 'Manager Direct Building',
            'slug' => 'manager-direct-building',
            'address' => '1 Direct Street',
            'region_id' => $region->id,
            'manager_admin_id' => $this->manager->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path('upload/chats/*direct-chat*')) ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_super_admin_sees_active_building_managers_in_direct_chat(): void
    {
        $inactiveManager = $this->createAdmin('inactive_manager_direct', 'inactive_manager_direct@stayhub.local', '0911000005', Admin::ROLE_BUILDING_MANAGER, Admin::STATUS_INACTIVE);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/chat/direct-conversations?per_page=50');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonFragment(['manager_admin_id' => $this->manager->id])
            ->assertJsonMissing(['manager_admin_id' => $this->otherManager->id])
            ->assertJsonMissing(['manager_admin_id' => $inactiveManager->id]);

        $this->assertDatabaseHas('chat_conversations', [
            'conversation_type' => ChatConversation::TYPE_SUPER_ADMIN_MANAGER,
            'super_admin_id' => $this->superAdmin->id,
            'manager_admin_id' => $this->manager->id,
        ]);
        $this->assertDatabaseMissing('chat_conversations', [
            'conversation_type' => ChatConversation::TYPE_SUPER_ADMIN_MANAGER,
            'super_admin_id' => $this->superAdmin->id,
            'manager_admin_id' => $this->otherManager->id,
        ]);
    }

    public function test_building_manager_sees_only_active_super_admins_in_direct_chat(): void
    {
        $inactiveSuperAdmin = $this->createAdmin('inactive_super_direct', 'inactive_super_direct@stayhub.local', '0911000006', Admin::ROLE_SUPER_ADMIN, Admin::STATUS_INACTIVE);

        $response = $this->actingAs($this->manager, 'admin')
            ->getJson('/api/v1/admin/chat/direct-conversations?per_page=50');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonFragment(['super_admin_id' => $this->superAdmin->id])
            ->assertJsonFragment(['super_admin_id' => $this->otherSuperAdmin->id])
            ->assertJsonMissing(['super_admin_id' => $inactiveSuperAdmin->id])
            ->assertJsonMissing(['manager_admin_id' => $this->otherManager->id]);

        foreach ($response->json('result.data') as $conversation) {
            $this->assertSame($this->manager->id, $conversation['manager_admin_id']);
        }
    }

    public function test_direct_chat_message_is_private_to_exact_participants(): void
    {
        Event::fake([ChatMessageSent::class, NotificationSent::class]);

        $conversationId = $this->directConversationId($this->superAdmin, $this->manager);

        $sendResponse = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/chat/direct-conversations/' . $conversationId . '/messages', ['body' => 'Trao đổi riêng với quản lý']);

        $sendResponse->assertCreated()
            ->assertJsonPath('result.message.sender_id', $this->superAdmin->id)
            ->assertJsonPath('result.conversation.tenant_unread_count', 1)
            ->assertJsonPath('result.conversation.admin_unread_count', 0);

        $this->actingAs($this->manager, 'admin')
            ->getJson('/api/v1/admin/chat/direct-conversations/' . $conversationId . '/messages')
            ->assertOk()
            ->assertJsonPath('result.data.0.body', 'Trao đổi riêng với quản lý');

        $this->actingAs($this->otherManager, 'admin')
            ->getJson('/api/v1/admin/chat/direct-conversations/' . $conversationId . '/messages')
            ->assertForbidden();

        $this->actingAs($this->otherSuperAdmin, 'admin')
            ->getJson('/api/v1/admin/chat/direct-conversations/' . $conversationId . '/messages')
            ->assertForbidden();

        $this->actingAs($this->manager, 'admin')
            ->getJson('/api/v1/admin/notifications?per_page=50')
            ->assertOk()
            ->assertJsonPath('result.data.0.notification_type', Notification::NOTIFICATION_TYPE_CHAT)
            ->assertJsonPath('result.data.0.target_admin_id', $this->manager->id)
            ->assertJsonPath('result.data.0.action_url', '/admin/chat?tab=direct&direct_conversation_id=' . $conversationId);

        $this->actingAs($this->otherManager, 'admin')
            ->getJson('/api/v1/admin/notifications?per_page=50')
            ->assertOk()
            ->assertJsonCount(0, 'result.data');
    }

    public function test_manager_reply_notifies_only_original_super_admin_and_mark_read_resets_counts(): void
    {
        Event::fake([ChatConversationRead::class, ChatMessageSent::class, NotificationSent::class]);

        $conversationId = $this->directConversationId($this->superAdmin, $this->manager);

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/chat/direct-conversations/' . $conversationId . '/messages', ['body' => 'Bạn kiểm tra giúp tôi']);

        $replyResponse = $this->actingAs($this->manager, 'admin')
            ->postJson('/api/v1/admin/chat/direct-conversations/' . $conversationId . '/messages', ['body' => 'Tôi đã nhận được']);

        $replyResponse->assertCreated()
            ->assertJsonPath('result.conversation.admin_unread_count', 1)
            ->assertJsonPath('result.conversation.tenant_unread_count', 0);

        $this->actingAs($this->superAdmin, 'admin')
            ->getJson('/api/v1/admin/notifications?per_page=50')
            ->assertOk()
            ->assertJsonPath('result.data.0.target_admin_id', $this->superAdmin->id);

        $this->actingAs($this->otherSuperAdmin, 'admin')
            ->getJson('/api/v1/admin/notifications?per_page=50')
            ->assertOk()
            ->assertJsonCount(0, 'result.data');

        $this->actingAs($this->superAdmin, 'admin')
            ->patchJson('/api/v1/admin/chat/direct-conversations/' . $conversationId . '/read')
            ->assertOk()
            ->assertJsonPath('result.admin_unread_count', 0);

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversationId,
            'admin_unread_count' => 0,
            'tenant_unread_count' => 0,
        ]);
    }

    public function test_direct_chat_uploads_images_like_admin_chat(): void
    {
        Event::fake([ChatMessageSent::class, NotificationSent::class]);

        $conversationId = $this->directConversationId($this->superAdmin, $this->manager);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->post('/api/v1/admin/chat/direct-conversations/' . $conversationId . '/messages', [
                'body' => '',
                'images' => [UploadedFile::fake()->image('direct-chat.jpg', 640, 480)],
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'result.message.attachments');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $this->superAdmin->id,
        ]);
    }

    private function directConversationId(Admin $superAdmin, Admin $manager): int
    {
        $response = $this->actingAs($superAdmin, 'admin')
            ->getJson('/api/v1/admin/chat/direct-conversations?per_page=50');

        $response->assertOk();

        $conversation = collect($response->json('result.data'))->firstWhere('manager_admin_id', $manager->id);
        $this->assertNotNull($conversation);

        return (int) $conversation['id'];
    }

    private function createAdmin(string $username, string $email, string $phone, int $role, int $status = Admin::STATUS_ACTIVE): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'full_name' => ucfirst(str_replace('_', ' ', $username)),
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => $status,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Direct Chat Test Address',
        ]);
    }
}
