<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangeOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $code)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز تأكيد تغيير كلمة المرور - Taskora',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password-change-otp',
            with: ['code' => $this->code],
        );
    }
}
