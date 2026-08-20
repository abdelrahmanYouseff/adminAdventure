<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestSystemMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $sentAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'اختبار البريد — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-system',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
