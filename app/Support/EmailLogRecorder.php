<?php

namespace App\Support;

use App\Models\EmailLog;
use App\Models\Order;

class EmailLogRecorder
{
    /**
     * @param  list<string>  $recipients
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        string $type,
        string $status,
        ?Order $order = null,
        array $recipients = [],
        ?string $subject = null,
        ?string $errorMessage = null,
        array $meta = [],
    ): EmailLog {
        $normalizedRecipients = collect($recipients)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return EmailLog::query()->create([
            'order_id' => $order?->id,
            'type' => $type,
            'status' => $status,
            'subject' => $subject,
            'order_number' => $order?->order_number,
            'recipients' => $normalizedRecipients,
            'recipients_count' => count($normalizedRecipients),
            'error_message' => $errorMessage,
            'meta' => $meta,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
