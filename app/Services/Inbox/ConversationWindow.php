<?php

namespace App\Services\Inbox;

use App\Models\InboxConversation;
use Carbon\CarbonInterface;

class ConversationWindow
{
    public const HOURS = 24;

    /**
     * @return array{is_open: bool, expires_at: ?string, remaining_seconds: int}
     */
    public function for(InboxConversation $conversation, ?CarbonInterface $now = null): array
    {
        $now = $now ?? now();
        $inbound = $conversation->last_inbound_at;

        if (! $inbound) {
            return [
                'is_open' => false,
                'expires_at' => null,
                'remaining_seconds' => 0,
            ];
        }

        $expires = $inbound->copy()->addHours(self::HOURS);
        $remaining = (int) $now->diffInSeconds($expires, false);

        return [
            'is_open' => $remaining > 0,
            'expires_at' => $expires->toIso8601String(),
            'remaining_seconds' => max(0, $remaining),
        ];
    }

    public function isOpen(InboxConversation $conversation, ?CarbonInterface $now = null): bool
    {
        return $this->for($conversation, $now)['is_open'];
    }
}
