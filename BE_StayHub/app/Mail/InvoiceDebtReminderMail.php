<?php

namespace App\Mail;

use App\Helpers\DecimalMoney;
use App\Helpers\VietQRHelper;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceDebtReminderMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public Tenant $tenant;

    public Invoice $invoice;

    public string $invoiceUrl;

    public ?string $paymentQrUrl;

    public function __construct(Tenant $tenant, Invoice $invoice)
    {
        $frontendUrl = config('app.frontend_url') ?: config('app.url');

        $this->tenant = $tenant;
        $this->invoice = $invoice;
        $this->invoiceUrl = rtrim((string) $frontendUrl, '/').'/tenant/invoices';
        $this->paymentQrUrl = $this->makePaymentQrUrl($invoice);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nhắc thanh toán tiền phòng '.$this->invoice->invoice_code,
        );
    }

    public function content(): Content
    {
        $this->invoice->loadMissing(['room.building', 'contract']);

        return new Content(
            view: 'email.invoiceDebtReminder',
            with: [
                'amountText' => number_format(DecimalMoney::toIntegerAmount($this->invoice->remaining_amount), 0, ',', '.').' VND',
                'billingPeriod' => str_pad((string) $this->invoice->billing_month, 2, '0', STR_PAD_LEFT).'/'.$this->invoice->billing_year,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function makePaymentQrUrl(Invoice $invoice): ?string
    {
        if (in_array((int) $invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
            return null;
        }

        if (! DecimalMoney::isPositive($invoice->remaining_amount)) {
            return null;
        }

        return VietQRHelper::generateLink(null, null, null, (string) $invoice->remaining_amount, $invoice->invoice_code);
    }
}
