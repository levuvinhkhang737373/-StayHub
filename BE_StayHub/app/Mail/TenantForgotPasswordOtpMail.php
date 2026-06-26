<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantForgotPasswordOtpMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public Tenant $tenant;
    public string $otp;

    public function __construct(Tenant $tenant, string $otp)
    {
        $this->tenant = $tenant;
        $this->otp = $otp;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Mã xác minh đặt lại mật khẩu StayHub',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.mailTenantForgotPasswordOtp',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
