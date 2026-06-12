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

class SendTenantPasswordEmail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public Tenant $tenant;

    public string $password;

    public string $loginUrl;

    public function __construct(Tenant $tenant, string $password)
    {
        $frontendUrl = config('app.frontend_url') ?: config('app.url');

        $this->tenant = $tenant;
        $this->password = $password;
        $this->loginUrl = rtrim((string) $frontendUrl, '/').'/tenant/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thông tin đăng nhập khách thuê StayHub',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.mailTenantPasswordFirst',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
