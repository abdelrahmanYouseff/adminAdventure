<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsTransactionalEnvelope;
use App\Support\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstallationCompletedMail extends Mailable
{
    use BuildsTransactionalEnvelope;
    use Queueable, SerializesModels;

    /**
     * @param  list<array{product_name: string, photo_path: string, photo_url: string|null}>  $photos
     */
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $customerName,
        public readonly string $workerName,
        public readonly string $workOrderUrl,
        public readonly array $photos,
    ) {}

    public function envelope(): Envelope
    {
        return $this->transactionalEnvelope(
            'تم التركيب — '.$this->customerName.' ('.$this->orderNumber.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.installation-completed',
            text: 'emails.text.installation-completed',
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
            if (! is_string($path) || $path === '' || ! MediaStorage::exists($path)) {
                continue;
            }

            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $safeName = 'installation-'.($index + 1).'.'.$extension;

            $attachments[] = Attachment::fromStorageDisk(MediaStorage::DISK, $path)
                ->as($safeName)
                ->withMime(MediaStorage::mimeType($path) ?: 'image/jpeg');
        }

        return $attachments;
    }
}
