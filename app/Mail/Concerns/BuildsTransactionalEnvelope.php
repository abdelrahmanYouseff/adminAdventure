<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

trait BuildsTransactionalEnvelope
{
    protected function transactionalEnvelope(string $subject): Envelope
    {
        $replyToAddress = config('mail.reply_to.address');
        $replyToName = (string) config('mail.reply_to.name', '');

        $replyTo = [];
        if (is_string($replyToAddress) && $replyToAddress !== '' && filter_var($replyToAddress, FILTER_VALIDATE_EMAIL)) {
            $replyTo = [new Address($replyToAddress, $replyToName !== '' ? $replyToName : null)];
        }

        return new Envelope(
            subject: $subject,
            replyTo: $replyTo,
        );
    }
}
