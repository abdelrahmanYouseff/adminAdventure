<?php

namespace App\Services\Inbox;

use App\Models\InboxContact;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\InboxSetting;
use App\Models\InboxWebhookLog;
use App\Support\InboxPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InboundMessageProcessor
{
    public function __construct(
        private WhatsAppGraphClient $graph,
        private QaRatingFlow $qa,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?string $phoneNumberId = null): void
    {
        $entry = $payload['entry'][0]['changes'][0]['value'] ?? null;

        if (! is_array($entry)) {
            InboxWebhookLog::query()->create([
                'event' => 'inbound',
                'outcome' => 'ignored',
                'reason' => 'missing_value',
                'http_status' => 204,
                'phone_number_id' => $phoneNumberId,
                'payload' => $payload,
            ]);

            return;
        }

        $phoneNumberId = $phoneNumberId
            ?: (string) data_get($entry, 'metadata.phone_number_id', '');

        if (! $this->graph->belongsToThisSystem($phoneNumberId)) {
            InboxWebhookLog::query()->create([
                'event' => 'inbound',
                'outcome' => 'ignored',
                'reason' => 'foreign_phone_number_id',
                'http_status' => 204,
                'phone_number_id' => $phoneNumberId,
                'payload' => $payload,
            ]);

            return;
        }

        $statuses = $entry['statuses'][0] ?? null;
        if (is_array($statuses)) {
            $this->applyStatus($statuses, $phoneNumberId, $payload);

            return;
        }

        $message = $entry['messages'][0] ?? null;
        if (! is_array($message)) {
            InboxWebhookLog::query()->create([
                'event' => 'inbound',
                'outcome' => 'ignored',
                'reason' => 'no_message_or_status',
                'http_status' => 204,
                'phone_number_id' => $phoneNumberId,
                'payload' => $payload,
            ]);

            return;
        }

        $this->storeInbound($message, $entry, $phoneNumberId, $payload);
    }

    /**
     * @param  array<string, mixed>  $status
     * @param  array<string, mixed>  $payload
     */
    private function applyStatus(array $status, string $phoneNumberId, array $payload): void
    {
        $externalId = (string) ($status['id'] ?? '');
        $waStatus = strtolower((string) ($status['status'] ?? ''));

        $mapped = match ($waStatus) {
            'sent' => InboxMessage::STATUS_SENT,
            'delivered' => InboxMessage::STATUS_DELIVERED,
            'read' => InboxMessage::STATUS_READ,
            'failed' => InboxMessage::STATUS_FAILED,
            default => null,
        };

        if ($externalId === '' || $mapped === null) {
            InboxWebhookLog::query()->create([
                'event' => 'status',
                'outcome' => 'ignored',
                'reason' => 'unknown_status',
                'http_status' => 204,
                'phone_number_id' => $phoneNumberId,
                'external_message_id' => $externalId ?: null,
                'payload' => $payload,
            ]);

            return;
        }

        $message = InboxMessage::query()->where('external_message_id', $externalId)->first();

        if ($message) {
            $rank = [
                InboxMessage::STATUS_QUEUED => 0,
                InboxMessage::STATUS_SENT => 1,
                InboxMessage::STATUS_DELIVERED => 2,
                InboxMessage::STATUS_READ => 3,
                InboxMessage::STATUS_FAILED => 4,
            ];

            $current = $rank[$message->status] ?? 0;
            $next = $rank[$mapped] ?? 0;

            if ($mapped === InboxMessage::STATUS_FAILED || $next >= $current) {
                $error = data_get($status, 'errors.0.message');
                $message->forceFill([
                    'status' => $mapped,
                    'error_message' => is_string($error) ? $error : $message->error_message,
                ])->save();
            }
        }

        InboxWebhookLog::query()->create([
            'event' => 'status',
            'outcome' => $message ? 'updated' : 'orphan',
            'reason' => $waStatus,
            'http_status' => 204,
            'phone_number_id' => $phoneNumberId,
            'external_message_id' => $externalId,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $payload
     */
    private function storeInbound(array $message, array $entry, string $phoneNumberId, array $payload): void
    {
        $externalId = (string) ($message['id'] ?? '');

        if ($externalId !== '' && InboxMessage::query()->where('external_message_id', $externalId)->exists()) {
            InboxWebhookLog::query()->create([
                'event' => 'message',
                'outcome' => 'duplicate',
                'reason' => 'already_stored',
                'http_status' => 204,
                'phone_number_id' => $phoneNumberId,
                'external_message_id' => $externalId,
            ]);

            return;
        }

        $from = InboxPhone::e164((string) ($message['from'] ?? ''));

        if ($from === '') {
            InboxWebhookLog::query()->create([
                'event' => 'message',
                'outcome' => 'ignored',
                'reason' => 'missing_from',
                'http_status' => 204,
                'phone_number_id' => $phoneNumberId,
                'external_message_id' => $externalId ?: null,
                'payload' => $payload,
            ]);

            return;
        }

        $profileName = (string) data_get($entry, 'contacts.0.profile.name', '');
        $mapped = $this->mapInboundType($message);

        $record = DB::transaction(function () use ($from, $profileName, $mapped, $externalId) {
            $contact = InboxContact::query()->firstOrCreate(
                ['phone_number' => $from],
                ['name' => $profileName !== '' ? $profileName : null, 'subscribed' => true]
            );

            if ($profileName !== '' && $contact->name !== $profileName) {
                $contact->name = $profileName;
            }

            $contact->last_active_at = now();
            $contact->save();

            $conversation = InboxConversation::query()->firstOrCreate(
                ['contact_id' => $contact->id],
                ['status' => InboxConversation::STATUS_OPEN]
            );

            if ($conversation->status === InboxConversation::STATUS_CLOSED) {
                $conversation->status = InboxConversation::STATUS_OPEN;
                $conversation->save();
            }

            $inboxMessage = InboxMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => InboxMessage::SENDER_CONTACT,
                'sender_id' => null,
                'direction' => InboxMessage::DIRECTION_INBOUND,
                'message_type' => $mapped['type'],
                'payload' => $mapped['payload'],
                'external_message_id' => $externalId !== '' ? $externalId : null,
                'status' => InboxMessage::STATUS_DELIVERED,
            ]);

            $conversation->applyLastMessage($inboxMessage);

            return [$conversation->fresh(), $inboxMessage, $mapped];
        });

        /** @var InboxConversation $conversation */
        /** @var InboxMessage $inboxMessage */
        /** @var array{type: string, payload: array<string, mixed>, media_id: ?string, mime: ?string, filename: ?string, kind: ?string} $mapped */
        [$conversation, $inboxMessage, $mapped] = $record;

        if ($mapped['media_id']) {
            $upload = $this->graph->downloadInboundMedia(
                $mapped['media_id'],
                $mapped['mime'],
                $mapped['filename']
            );

            if ($upload) {
                $payloadData = $inboxMessage->payload ?? [];
                $payloadData['media_uuid'] = $upload->uuid;
                $payloadData['media_url'] = $upload->publicUrl();
                $payloadData['mime_type'] = $upload->mime;
                $inboxMessage->payload = $payloadData;
                $inboxMessage->save();
            }
        }

        InboxWebhookLog::query()->create([
            'event' => 'message',
            'outcome' => 'stored',
            'reason' => $mapped['type'],
            'http_status' => 204,
            'phone_number_id' => $phoneNumberId,
            'external_message_id' => $externalId ?: null,
            'payload' => $payload,
        ]);

        $this->afterCommit($conversation, $inboxMessage);
    }

    private function afterCommit(InboxConversation $conversation, InboxMessage $message): void
    {
        try {
            if ($this->qa->handleInbound($conversation, $message)) {
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Inbox QA flow failed', ['error' => $e->getMessage()]);
        }

        $settings = InboxSetting::current();

        if (! $settings->botIsEnabled()) {
            return;
        }

        if ($conversation->isBotPaused() || $conversation->needs_human_agent) {
            return;
        }

        $job = new \App\Jobs\ReplyWithInboxBot($conversation->id, $message->id);

        if (config('services.whatsapp.dispatch_sync')) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{type: string, payload: array<string, mixed>, media_id: ?string, mime: ?string, filename: ?string, kind: ?string}
     */
    private function mapInboundType(array $message): array
    {
        $waType = (string) ($message['type'] ?? 'text');

        $type = match ($waType) {
            'image', 'sticker', 'video' => InboxMessage::TYPE_IMAGE,
            'document', 'audio' => InboxMessage::TYPE_DOCUMENT,
            'location' => InboxMessage::TYPE_LOCATION,
            default => InboxMessage::TYPE_TEXT,
        };

        $payload = ['raw' => $message, 'wa_type' => $waType];
        $mediaId = null;
        $mime = null;
        $filename = null;
        $kind = null;

        if ($waType === 'text') {
            $payload['text'] = (string) data_get($message, 'text.body', '');
        } elseif ($waType === 'interactive') {
            $payload['text'] = (string) (
                data_get($message, 'interactive.button_reply.title')
                ?? data_get($message, 'interactive.list_reply.title')
                ?? data_get($message, 'interactive.nfm_reply.response_json')
                ?? 'Interactive'
            );
            $payload['button_id'] = data_get($message, 'interactive.button_reply.id')
                ?? data_get($message, 'interactive.list_reply.id');
        } elseif ($waType === 'button') {
            $payload['text'] = (string) data_get($message, 'button.text', 'Button');
            $payload['button_id'] = data_get($message, 'button.payload');
        } elseif ($waType === 'location') {
            $payload['latitude'] = data_get($message, 'location.latitude');
            $payload['longitude'] = data_get($message, 'location.longitude');
            $payload['name'] = data_get($message, 'location.name');
            $payload['address'] = data_get($message, 'location.address');
        } else {
            $node = is_array($message[$waType] ?? null) ? $message[$waType] : [];
            $payload['caption'] = (string) ($node['caption'] ?? '');
            $payload['text'] = $payload['caption'];
            $payload['filename'] = $node['filename'] ?? null;
            $payload['mime_type'] = $node['mime_type'] ?? null;
            $mediaId = isset($node['id']) ? (string) $node['id'] : null;
            $mime = $node['mime_type'] ?? null;
            $filename = $node['filename'] ?? null;
            $kind = $waType === 'audio' ? 'audio' : $waType;
            $payload['kind'] = $kind;
        }

        return [
            'type' => $type,
            'payload' => $payload,
            'media_id' => $mediaId,
            'mime' => is_string($mime) ? $mime : null,
            'filename' => is_string($filename) ? $filename : null,
            'kind' => $kind,
        ];
    }
}
