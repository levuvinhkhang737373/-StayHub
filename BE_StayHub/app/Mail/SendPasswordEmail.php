<?php

namespace App\Mail;

use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendPasswordEmail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public Admin $admin;

    public string $password;

    public string $loginUrl;

    public function __construct(Admin $admin, string $password)
    {
        $this->admin = $admin;
        $this->password = $password;
        $this->loginUrl = 'https://stayhub.id.vn/admin/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thông tin đăng nhập quản trị StayHub',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.mailPasswordFirst',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
