<?php

namespace Tests\Feature\Chat;

use App\Events\ChatMessageSent;
use App\Events\NotificationSent;
use App\Models\Admin;
use App\Models\Building;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Notification;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $manager;
    private Admin $otherManager;
    private Tenant $tenant;
    private Building $building;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->createAdmin('manager_chat', 'manager_chat@stayhub.local', '0900000001');
        $this->otherManager = $this->createAdmin('other_manager_chat', 'other_manager_chat@stayhub.local', '0900000002');

        $region = Region::query()->create([
            'name' => 'Chat Region',
            'code' => 'CHAT_REGION',
            'created_by' => $this->manager->id,
        ]);

        $this->building = Building::query()->create([
            'name' => 'Chat Building',
            'slug' => 'chat-building',
            'address' => '123 Chat Street',
            'region_id' => $region->id,
            'manager_admin_id' => $this->manager->id,
            'created_by' => $this->manager->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $roomType = RoomType::query()->create([
            'name' => 'Chat Standard',
            'slug' => 'chat-standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->manager->id,
        ]);

        $this->room = Room::query()->create([
            'building_id' => $this->building->id,
            'room_type_id' => $roomType->id,
            'room_number' => 'C101',
            'slug' => 'c101',
            'floor' => 1,
            'base_price' => '3500000.00',
            'max_occupants' => 3,
            'current_occupants' => 1,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->manager->id,
        ]);

        $this->tenant = Tenant::query()->create([
            'created_by' => $this->manager->id,
            'building_id' => $this->building->id,
            'full_name' => 'Tenant Chat',
            'gender' => Tenant::GENDER_MALE,
            'date_of_birth' => '2000-01-01',
            'phone' => '0910000001',
            'email' => 'tenant_chat@stayhub.local',
            'username' => 'tenant_chat',
            'password' => bcrypt('password'),
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789001',
            'status' => Tenant::STATUS_RENTING,
        ]);

        $contract = Contract::query()->create([
            'contract_code' => 'HD-CHAT-001',
            'room_id' => $this->room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'room_price' => '3500000.00',
            'deposit_amount' => '3500000.00',
            'status' => Contract::STATUS_ACTIVE,
            'payment_status' => Contract::PAYMENT_STATUS_SUCCESS,
            'created_by' => $this->manager->id,
        ]);

        ContractTenant::query()->create([
            'contract_id' => $contract->id,
            'tenant_id' => $this->tenant->id,
            'join_date' => '2026-06-01',
            'is_staying' => true,
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_tenant_sends_message_to_own_building_manager(): void
    {
        Event::fake([ChatMessageSent::class, NotificationSent::class]);

        $response = $this->actingAs($this->tenant, 'tenant')
            ->postJson('/api/v1/tenant/chat/messages', ['body' => 'Em cần hỗ trợ phòng C101']);

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('result.conversation.manager_admin_id', $this->manager->id)
            ->assertJsonPath('result.conversation.room_number', 'C101')
            ->assertJsonPath('result.message.sender_type', 'tenant');

        $this->assertDatabaseHas('chat_conversations', [
            'tenant_id' => $this->tenant->id,
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'manager_admin_id' => $this->manager->id,
            'admin_unread_count' => 1,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'sender_type' => 'tenant',
            'sender_id' => $this->tenant->id,
            'sender_role' => ChatMessage::SENDER_TENANT,
            'body' => 'Em cần hỗ trợ phòng C101',
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Tin nhắn mới từ khách thuê',
            'notification_type' => Notification::NOTIFICATION_TYPE_CHAT,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'tenant_id' => $this->tenant->id,
            'target_admin_id' => $this->manager->id,
            'created_by' => null,
            'status' => Notification::STATUS_SENT,
        ]);
        Event::assertDispatched(ChatMessageSent::class);
        Event::assertDispatched(NotificationSent::class);
    }

    public function test_chat_notifications_are_only_visible_to_target_admin(): void
    {
        Event::fake([ChatMessageSent::class, NotificationSent::class]);

        $superAdmin = $this->createAdmin('super_chat', 'super_chat@stayhub.local', '0900000003', Admin::ROLE_SUPER_ADMIN);

        $this->actingAs($this->tenant, 'tenant')
            ->postJson('/api/v1/tenant/chat/messages', ['body' => 'Tin nhắn riêng cho quản lý'])
            ->assertCreated();

        $this->actingAs($this->manager, 'admin')
            ->getJson('/api/v1/admin/notifications?per_page=50')
            ->assertOk()
            ->assertJsonPath('result.data.0.title', 'Tin nhắn mới từ khách thuê')
            ->assertJsonPath('result.data.0.target_admin_id', $this->manager->id);

        $this->actingAs($this->otherManager, 'admin')
            ->getJson('/api/v1/admin/notifications?per_page=50')
            ->assertOk()
            ->assertJsonCount(0, 'result.data');

        $this->actingAs($superAdmin, 'admin')
            ->getJson('/api/v1/admin/notifications?per_page=50')
            ->assertOk()
            ->assertJsonCount(0, 'result.data');
    }

    public function test_super_admin_cannot_open_tenant_manager_conversation(): void
    {
        $superAdmin = $this->createAdmin('super_private_chat', 'super_private_chat@stayhub.local', '0900000004', Admin::ROLE_SUPER_ADMIN);
        $conversation = ChatConversation::query()->create([
            'tenant_id' => $this->tenant->id,
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'manager_admin_id' => $this->manager->id,
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);

        $this->actingAs($superAdmin, 'admin')
            ->getJson('/api/v1/admin/chat/conversations')
            ->assertForbidden();

        $this->actingAs($superAdmin, 'admin')
            ->getJson('/api/v1/admin/chat/conversations/' . $conversation->id . '/messages')
            ->assertForbidden();
    }

    public function test_only_assigned_building_manager_can_open_conversation(): void
    {
        $conversation = ChatConversation::query()->create([
            'tenant_id' => $this->tenant->id,
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'manager_admin_id' => $this->manager->id,
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->otherManager, 'admin')
            ->getJson('/api/v1/admin/chat/conversations/' . $conversation->id . '/messages')
            ->assertForbidden();

        $this->actingAs($this->manager, 'admin')
            ->getJson('/api/v1/admin/chat/conversations/' . $conversation->id . '/messages')
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_manager_reply_increments_tenant_unread_count(): void
    {
        Event::fake([ChatMessageSent::class, NotificationSent::class]);

        $conversation = ChatConversation::query()->create([
            'tenant_id' => $this->tenant->id,
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'manager_admin_id' => $this->manager->id,
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->manager, 'admin')
            ->postJson('/api/v1/admin/chat/conversations/' . $conversation->id . '/messages', ['body' => 'Quản lý đã nhận thông tin']);

        $response->assertCreated()
            ->assertJsonPath('result.message.sender_type', 'admin')
            ->assertJsonPath('result.conversation.tenant_unread_count', 1);

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
            'tenant_unread_count' => 1,
            'admin_unread_count' => 0,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Tin nhắn mới từ quản lý',
            'notification_type' => Notification::NOTIFICATION_TYPE_CHAT,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'tenant_id' => $this->tenant->id,
            'target_admin_id' => null,
            'created_by' => $this->manager->id,
            'status' => Notification::STATUS_SENT,
        ]);
        Event::assertDispatched(ChatMessageSent::class);
        Event::assertDispatched(NotificationSent::class);
    }


    public function test_chat_message_event_broadcasts_immediately_with_public_attachment_url(): void
    {
        config(['app.url' => 'http://localhost:8080']);
        $conversation = ChatConversation::query()->create([
            'tenant_id' => $this->tenant->id,
            'building_id' => $this->building->id,
            'room_id' => $this->room->id,
            'manager_admin_id' => $this->manager->id,
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);
        $message = ChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'sender_id' => $this->manager->id,
            'sender_role' => ChatMessage::SENDER_ADMIN,
            'body' => '',
            'attachments' => ['/upload/chats/realtime-image.jpg'],
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        $event = new ChatMessageSent($message);
        $payload = $event->broadcastWith();

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame(
            'http://localhost:8080/upload/chats/realtime-image.jpg',
            $payload['message']['attachments'][0]
        );
    }

    private function createAdmin(string $username, string $email, string $phone, int $role = Admin::ROLE_BUILDING_MANAGER): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'full_name' => ucfirst(str_replace('_', ' ', $username)),
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Chat Test Address',
        ]);
    }
}
