<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsTransactionalEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewWorkOrderIssuedMail extends Mailable
{
    use BuildsTransactionalEnvelope;
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $products
     */
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $customerName,
        public readonly ?string $customerPhone,
        public readonly array $products,
        public readonly string $assignWorkersUrl,
    ) {}

    public function envelope(): Envelope
    {
        return $this->transactionalEnvelope(
            'أمر عمل جديد — '.$this->customerName.' ('.$this->orderNumber.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-work-order-issued',
            text: 'emails.text.new-work-order-issued',
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
