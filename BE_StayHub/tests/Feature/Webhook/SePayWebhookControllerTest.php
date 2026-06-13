<?php

namespace Tests\Feature\Webhook;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SePayWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'username' => 'superadmin_test',
            'full_name' => 'Super Admin Test',
            'email' => 'superadmin_test@stayhub.local',
            'phone' => '0901234567',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        $region = Region::create([
            'name' => 'Region Test',
            'code' => 'REG_TEST',
            'created_by' => $this->superAdmin->id,
        ]);

        $building = Building::create([
            'name' => 'Building A',
            'slug' => 'building-a',
            'address' => '123 Test St',
            'region_id' => $region->id,
            'manager_admin_id' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $roomType = RoomType::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        $room = Room::create([
            'building_id' => $building->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'slug' => '101',
            'floor' => 1,
            'base_price' => '3500000.00',
            'max_occupants' => 5,
            'current_occupants' => 0,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create a contract waiting for deposit
        $this->contract = Contract::create([
            'contract_code' => 'HD-TEST-WEBHOOK',
            'room_id' => $room->id,
            'start_date' => '2026-06-12',
            'end_date' => '2026-12-12',
            'billing_cycle_day' => 5,
            'room_price' => 3500000,
            'deposit_amount' => 3500000,
            'status' => Contract::STATUS_ACTIVE,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_sepay_webhook_processes_payment_successfully(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        $payload = [
            'id' => 99999,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-06-12 08:30:00',
            'accountNumber' => '99928876789',
            'amount' => 3500000,
            'transferType' => 'in',
            'content' => 'COC HD-TEST-WEBHOOK',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/sepay-webhook', $payload, [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Xử lý thanh toán thành công.'
            ]);

        // Verify transaction is created
        $this->assertDatabaseHas('contract_deposit_transactions', [
            'contract_id' => $this->contract->id,
            'amount' => 3500000.00,
            'transaction_reference' => 'FT12345678',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
        ]);

        // Verify contract status is updated to Paid/Success
        $this->contract->refresh();
        $this->assertEquals(Contract::PAYMENT_STATUS_SUCCESS, $this->contract->payment_status);
    }

    public function test_sepay_webhook_fails_with_invalid_token(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        $payload = [
            'id' => 99999,
            'gateway' => 'MBBank',
            'amount' => 3500000,
            'transferType' => 'in',
            'content' => 'COC HD-TEST-WEBHOOK',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/sepay-webhook', $payload, [
            'Authorization' => 'Apikey wrong-token'
        ]);

        $response->assertStatus(401);
    }

    public function test_sepay_webhook_ignores_outgoing_transfers(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        $payload = [
            'id' => 99999,
            'gateway' => 'MBBank',
            'amount' => 3500000,
            'transferType' => 'out',
            'content' => 'COC HD-TEST-WEBHOOK',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/sepay-webhook', $payload, [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Chỉ xử lý giao dịch nhận tiền.'
            ]);
    }

    public function test_sepay_webhook_ignores_duplicate_reference(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        // Create transaction with this reference first
        ContractDepositTransaction::create([
            'contract_id' => $this->contract->id,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            'amount' => 3500000,
            'transaction_date' => '2026-06-12',
            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            'transaction_reference' => 'FT12345678',
            'created_by' => null,
        ]);

        $payload = [
            'id' => 99999,
            'gateway' => 'MBBank',
            'amount' => 3500000,
            'transferType' => 'in',
            'content' => 'COC HD-TEST-WEBHOOK',
            'code' => 'FT12345678',
        ];

        $response = $this->postJson('/api/sepay-webhook', $payload, [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Giao dịch đã được xử lý.'
            ]);
    }

    public function test_sepay_webhook_bypasses_test_delivery(): void
    {
        Config::set('services.sepay.webhook_token', 'test-token-123');

        $payload = [
            'id' => 0,
            'gateway' => 'SePay',
            'transactionDate' => '2026-06-12 08:31:48',
            'accountNumber' => '0000000000',
            'transferType' => 'in',
            'transferAmount' => 10000,
            'code' => 'SEPAYTEST',
            'content' => 'SEPAY TEST WEBHOOK',
        ];

        $response = $this->postJson('/api/sepay-webhook', $payload, [
            'Authorization' => 'Apikey test-token-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Nhận webhook thử nghiệm thành công.'
            ]);
    }
}
