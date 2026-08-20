<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DismantlingCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{product_name: string, photo_path: string, photo_url: string|null}>  $photos
     */
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $customerName,
        public readonly string $workerName,
        public readonly string $returnsUrl,
        public readonly array $photos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم الفك — '.$this->customerName.' ('.$this->orderNumber.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dismantling-completed',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->photos as $index => $photo) {
            $path = $photo['photo_path'] ?? null;
            if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $safeName = 'pickup-'.($index + 1).'.'.$extension;

            $attachments[] = Attachment::fromStorageDisk('public', $path)
                ->as($safeName)
                ->withMime(Storage::disk('public')->mimeType($path) ?: 'image/jpeg');
        }

        return $attachments;
    }
}
