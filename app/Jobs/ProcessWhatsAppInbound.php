<?php

namespace App\Jobs;

use App\Services\Inbox\InboundMessageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppInbound implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public ?string $phoneNumberId = null,
    ) {}

    public function handle(InboundMessageProcessor $processor): void
    {
        $processor->handle($this->payload, $this->phoneNumberId);
    }
}
