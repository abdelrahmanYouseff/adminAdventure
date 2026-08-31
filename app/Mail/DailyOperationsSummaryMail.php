<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsTransactionalEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyOperationsSummaryMail extends Mailable
{
    use BuildsTransactionalEnvelope;
    use Queueable, SerializesModels;

    /**
     * @param  list<array{order_number: string, customer_name: string}>  $orders
     */
    public function __construct(
        public readonly string $dateLabel,
        public readonly int $ordersCount,
        public readonly int $installationsCount,
        public readonly int $dismantlingCount,
        public readonly array $orders,
    ) {}

    public function envelope(): Envelope
    {
        return $this->transactionalEnvelope(
            'الملخص اليومي — '.$this->dateLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-operations-summary',
            text: 'emails.text.daily-operations-summary',
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
