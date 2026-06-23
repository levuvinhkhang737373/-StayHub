<?php

namespace App\Console\Commands;

use App\Events\NotificationSent;
use App\Helpers\DecimalMoney;
use App\Mail\InvoiceDebtReminderMail;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceReminderLog;
use App\Models\Notification;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInvoiceDebtReminders extends Command
{
    protected $signature = 'invoices:send-debt-reminders {--date= : Ngày chạy định dạng YYYY-MM-DD} {--dry-run : Chỉ kiểm tra, không gửi thông báo/email}';

    protected $description = 'Nhắc tenant thanh toán hóa đơn tháng hiện tại còn dư nợ qua notification realtime và email.';

    public function handle(): int
    {
        $runDate = $this->resolveRunDate();
        $billingDate = $runDate->copy()->subMonthNoOverflow();
        $reminderDate = $runDate->toDateString();
        $dryRun = (bool) $this->option('dry-run');

        $invoices = $this->reminderInvoices($billingDate)->get();
        $summary = [
            'processed' => 0,
            'skipped' => 0,
            'tenants' => 0,
            'emails' => 0,
            'errors' => 0,
            'room_ids' => [],
        ];

        $billingPeriod = str_pad((string) $billingDate->month, 2, '0', STR_PAD_LEFT).'/'.$billingDate->year;
        $this->info('Bắt đầu nhắc nợ ngày '.$reminderDate.' cho tháng '.$billingPeriod.'.');
        $this->info('Tìm thấy '.$invoices->count().' hóa đơn còn dư nợ.'.($dryRun ? ' Đang chạy dry-run.' : ''));

        foreach ($invoices as $invoice) {
            $activeTenants = $this->activeTenants($invoice);

            if ($activeTenants->isEmpty()) {
                $summary['skipped']++;
                $this->warn("Bỏ qua hóa đơn {$invoice->invoice_code}: không có tenant đang ở.");
                continue;
            }

            if ($this->alreadyReminded($invoice, $reminderDate)) {
                $summary['skipped']++;
                $this->line("Bỏ qua hóa đơn {$invoice->invoice_code}: đã gửi nhắc trong ngày {$reminderDate}.");
                continue;
            }

            if ($dryRun) {
                $mailCount = $activeTenants->filter(fn (Tenant $tenant): bool => filled($tenant->email))->count();
                $summary['processed']++;
                $summary['tenants'] += $activeTenants->count();
                $summary['emails'] += $mailCount;
                $summary['room_ids'][$invoice->room_id] = true;
                $this->line("[DRY-RUN] {$invoice->invoice_code}: {$activeTenants->count()} tenant, {$mailCount} email.");
                continue;
            }

            try {
                $result = $this->sendReminder($invoice, $activeTenants, $reminderDate);

                if ($result['skipped']) {
                    $summary['skipped']++;
                    $this->line("Bỏ qua hóa đơn {$invoice->invoice_code}: đã gửi bởi tiến trình khác.");
                    continue;
                }

                $summary['processed']++;
                $summary['tenants'] += $result['tenant_count'];
                $summary['emails'] += $result['mail_queued_count'];
                $summary['room_ids'][$invoice->room_id] = true;
                $this->info("Đã nhắc hóa đơn {$invoice->invoice_code}: {$result['tenant_count']} tenant, {$result['mail_queued_count']} email.");
            } catch (Throwable $e) {
                $summary['errors']++;
                $this->error("Lỗi nhắc hóa đơn {$invoice->invoice_code}: {$e->getMessage()}");
            }
        }

        if (! $dryRun) {
            $this->sendAdminSummary($summary, $billingDate);
        }

        $this->info('Hoàn tất nhắc nợ. Đã gửi: '.$summary['processed'].', bỏ qua: '.$summary['skipped'].', lỗi: '.$summary['errors'].'.');

        return self::SUCCESS;
    }

    private function resolveRunDate(): Carbon
    {
        $date = $this->option('date');

        if (filled($date)) {
            return Carbon::createFromFormat('Y-m-d', (string) $date, 'Asia/Ho_Chi_Minh')->startOfDay();
        }

        return now('Asia/Ho_Chi_Minh')->startOfDay();
    }

    private function reminderInvoices(Carbon $billingDate): Builder
    {
        return Invoice::query()
            ->with([
                'room.building',
                'contract.contractTenants.tenant',
            ])
            ->where('billing_month', (int) $billingDate->month)
            ->where('billing_year', (int) $billingDate->year)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->where('remaining_amount', '>', 0)
            ->whereHas('contract', fn (Builder $query): Builder => $query->where('status', Contract::STATUS_ACTIVE))
            ->whereHas('contract.contractTenants', function (Builder $query): void {
                $query->where('is_staying', true)
                    ->whereNull('leave_date');
            })
            ->orderBy('room_id')
            ->orderBy('id');
    }

    private function activeTenants(Invoice $invoice): Collection
    {
        $contractTenants = $invoice->contract?->contractTenants ?? collect();

        return $contractTenants
            ->filter(fn ($contractTenant): bool => (bool) $contractTenant->is_staying && $contractTenant->leave_date === null && $contractTenant->tenant instanceof Tenant)
            ->pluck('tenant')
            ->unique('id')
            ->values();
    }

    private function alreadyReminded(Invoice $invoice, string $reminderDate): bool
    {
        return InvoiceReminderLog::query()
            ->where('invoice_id', $invoice->id)
            ->whereDate('reminder_date', $reminderDate)
            ->exists();
    }

    private function sendReminder(Invoice $invoice, Collection $activeTenants, string $reminderDate): array
    {
        $transactionResult = DB::transaction(function () use ($invoice, $activeTenants, $reminderDate): array {
            if ($this->alreadyReminded($invoice, $reminderDate)) {
                return ['skipped' => true];
            }

            $invoice->loadMissing(['room.building', 'contract']);
            $amountText = number_format(DecimalMoney::toIntegerAmount($invoice->remaining_amount), 0, ',', '.').' VND';
            $billingPeriod = str_pad((string) $invoice->billing_month, 2, '0', STR_PAD_LEFT).'/'.$invoice->billing_year;

            $notification = Notification::query()->create([
                'title' => 'Nhắc thanh toán tiền phòng',
                'content' => 'Phòng '.($invoice->room?->room_number ?? 'chưa rõ')." còn {$amountText} cần thanh toán cho hóa đơn {$invoice->invoice_code} tháng {$billingPeriod}.",
                'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
                'target_type' => Notification::TARGET_TYPE_ROOM,
                'building_id' => $invoice->room?->building_id,
                'room_id' => $invoice->room_id,
                'tenant_id' => null,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => null,
            ]);

            $log = InvoiceReminderLog::query()->create([
                'invoice_id' => $invoice->id,
                'contract_id' => $invoice->contract_id,
                'room_id' => $invoice->room_id,
                'notification_id' => $notification->id,
                'reminder_date' => $reminderDate,
                'tenant_count' => $activeTenants->count(),
                'mail_queued_count' => 0,
                'status' => InvoiceReminderLog::STATUS_SENT,
            ]);

            return [
                'skipped' => false,
                'notification' => $notification,
                'log' => $log,
            ];
        });

        if ($transactionResult['skipped']) {
            return ['skipped' => true];
        }

        /** @var Notification $notification */
        $notification = $transactionResult['notification'];
        /** @var InvoiceReminderLog $log */
        $log = $transactionResult['log'];

        event(new NotificationSent($notification));

        $mailQueuedCount = 0;
        $mailErrors = [];

        foreach ($activeTenants as $tenant) {
            if (blank($tenant->email)) {
                continue;
            }

            try {
                Mail::to($tenant->email)->queue(new InvoiceDebtReminderMail($tenant, $invoice));
                $mailQueuedCount++;
            } catch (Throwable $e) {
                $mailErrors[] = $tenant->id.': '.$e->getMessage();
            }
        }

        $log->forceFill([
            'mail_queued_count' => $mailQueuedCount,
            'status' => empty($mailErrors) ? InvoiceReminderLog::STATUS_SENT : InvoiceReminderLog::STATUS_FAILED,
            'error_message' => empty($mailErrors) ? null : implode("\n", $mailErrors),
        ])->save();

        return [
            'skipped' => false,
            'tenant_count' => $activeTenants->count(),
            'mail_queued_count' => $mailQueuedCount,
        ];
    }

    private function sendAdminSummary(array $summary, Carbon $billingDate): void
    {
        $roomCount = count($summary['room_ids']);
        $billingPeriod = str_pad((string) $billingDate->month, 2, '0', STR_PAD_LEFT).'/'.$billingDate->year;

        $notification = Notification::query()->create([
            'title' => 'Tổng kết nhắc nợ tiền phòng',
            'content' => "Đã nhắc {$summary['processed']} hóa đơn tháng {$billingPeriod} cho {$roomCount} phòng, {$summary['tenants']} tenant, queue {$summary['emails']} email. Bỏ qua {$summary['skipped']} hóa đơn, lỗi {$summary['errors']}.",
            'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
            'target_type' => Notification::TARGET_TYPE_ADMIN,
            'building_id' => null,
            'room_id' => null,
            'tenant_id' => null,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => null,
        ]);

        event(new NotificationSent($notification));
    }
}
