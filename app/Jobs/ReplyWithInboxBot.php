<?php

namespace App\Jobs;

use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Services\Inbox\InboxBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReplyWithInboxBot implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $conversationId,
        public int $messageId,
    ) {}

    public function handle(InboxBot $bot): void
    {
        $conversation = InboxConversation::query()->with('contact')->find($this->conversationId);
        $message = InboxMessage::query()->find($this->messageId);

        if (! $conversation || ! $message) {
            return;
        }

        $bot->reply($conversation, $message);
    }
}
