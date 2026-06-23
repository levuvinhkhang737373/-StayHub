<?php

namespace Tests\Feature\Console;

use App\Events\NotificationSent;
use App\Mail\InvoiceDebtReminderMail;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendInvoiceDebtRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Admin::query()->create([
            'username' => 'superadmin_reminder',
            'full_name' => 'Super Admin Reminder',
            'email' => 'superadmin_reminder@stayhub.local',
            'phone' => '0901000000',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        $region = Region::query()->create([
            'name' => 'Reminder Region',
            'code' => 'REMINDER_REGION',
            'created_by' => $admin->id,
        ]);

        $building = Building::query()->create([
            'name' => 'Reminder Building',
            'slug' => 'reminder-building',
            'address' => '123 Reminder St',
            'region_id' => $region->id,
            'manager_admin_id' => $admin->id,
            'created_by' => $admin->id,
            'status' => Building::STATUS_ACTIVE,
        ]);

        $roomType = RoomType::query()->create([
            'name' => 'Reminder Standard',
            'slug' => 'reminder-standard',
            'status' => RoomType::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        $room = Room::query()->create([
            'building_id' => $building->id,
            'room_type_id' => $roomType->id,
            'room_number' => 'R101',
            'slug' => 'r101',
            'floor' => 1,
            'base_price' => '3500000.00',
            'max_occupants' => 4,
            'current_occupants' => 2,
            'status' => Room::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        $tenantWithEmail = $this->createTenant($admin, $building, 'tenant_reminder_1', 'tenant1@stayhub.local', '0910000001', '111111111111');
        $tenantWithoutEmail = $this->createTenant($admin, $building, 'tenant_reminder_2', null, '0910000002', '222222222222');

        $contract = Contract::query()->create([
            'contract_code' => 'HD-REMINDER-001',
            'room_id' => $room->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-01',
            'billing_cycle_day' => 5,
            'room_price' => '3500000.00',
            'deposit_amount' => '4000000.00',
            'status' => Contract::STATUS_ACTIVE,
            'payment_status' => Contract::PAYMENT_STATUS_SUCCESS,
            'created_by' => $admin->id,
        ]);

        foreach ([$tenantWithEmail, $tenantWithoutEmail] as $tenant) {
            ContractTenant::query()->create([
                'contract_id' => $contract->id,
                'tenant_id' => $tenant->id,
                'join_date' => '2026-06-01',
                'is_staying' => true,
                'created_by' => $admin->id,
            ]);
        }

        $this->invoice = Invoice::query()->create([
            'invoice_code' => 'INV-REMINDER-001',
            'contract_id' => $contract->id,
            'room_id' => $room->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'previous_debt_amount' => '0.00',
            'total_amount' => '3500000.00',
            'paid_amount' => '1000000.00',
            'remaining_amount' => '2500000.00',
            'due_date' => '2026-05-10',
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'issued_at' => '2026-05-01 08:00:00',
            'created_by' => $admin->id,
        ]);
    }

    public function test_command_sends_realtime_notification_and_queues_email_once(): void
    {
        Mail::fake();
        Event::fake([NotificationSent::class]);

        $this->artisan('invoices:send-debt-reminders', ['--date' => '2026-06-07'])->assertExitCode(0);
        $this->artisan('invoices:send-debt-reminders', ['--date' => '2026-06-07'])->assertExitCode(0);

        $this->assertDatabaseCount('invoice_reminder_logs', 1);
        $this->assertDatabaseHas('invoice_reminder_logs', [
            'invoice_id' => $this->invoice->id,
            'tenant_count' => 2,
            'mail_queued_count' => 1,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Nhắc thanh toán tiền phòng',
            'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
            'target_type' => Notification::TARGET_TYPE_ROOM,
            'room_id' => $this->invoice->room_id,
        ]);

        Mail::assertQueued(InvoiceDebtReminderMail::class, 1);
        Event::assertDispatched(NotificationSent::class, function (NotificationSent $event): bool {
            return (int) $event->notification->target_type === Notification::TARGET_TYPE_ROOM
                && (int) $event->notification->room_id === (int) $this->invoice->room_id;
        });
    }

    public function test_dry_run_does_not_write_or_send_anything(): void
    {
        Mail::fake();
        Event::fake([NotificationSent::class]);

        $this->artisan('invoices:send-debt-reminders', ['--date' => '2026-06-07', '--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseCount('invoice_reminder_logs', 0);
        $this->assertDatabaseMissing('notifications', [
            'title' => 'Nhắc thanh toán tiền phòng',
            'room_id' => $this->invoice->room_id,
        ]);
        Mail::assertNothingQueued();
        Event::assertNotDispatched(NotificationSent::class);
    }

    private function createTenant(Admin $admin, Building $building, string $username, ?string $email, string $phone, string $identityNumber): Tenant
    {
        return Tenant::query()->create([
            'username' => $username,
            'full_name' => 'Tenant '.$username,
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt('password'),
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => $identityNumber,
            'date_of_birth' => '2000-01-01',
            'created_by' => $admin->id,
            'building_id' => $building->id,
        ]);
    }
}
