<?php

namespace App\Services\Inbox;

use App\Models\InboxContact;
use App\Models\InboxConversation;
use App\Models\InboxMediaUpload;
use App\Models\InboxMessage;
use App\Models\InboxSetting;
use App\Models\User;
use App\Support\InboxPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OutboundMessageSender
{
    public function __construct(
        private WhatsAppGraphClient $graph,
        private ConversationWindow $window,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function send(InboxConversation $conversation, User $user, array $input, string $senderType = InboxMessage::SENDER_USER): InboxMessage
    {
        $conversation->loadMissing('contact');
        $contact = $conversation->contact;
        $to = InboxPhone::normalize((string) $contact?->phone_number);

        if ($to === '') {
            throw ValidationException::withMessages(['phone' => 'رقم جهة الاتصال غير صالح.']);
        }

        $type = (string) ($input['type'] ?? InboxMessage::TYPE_TEXT);
        $isTemplate = $type === InboxMessage::TYPE_TEMPLATE;

        if (! $isTemplate && ! $this->window->isOpen($conversation)) {
            throw ValidationException::withMessages([
                'body' => 'نافذة الـ 24 ساعة مغلقة. أرسل قالباً معتمداً.',
            ]);
        }

        $graphPayload = $this->buildGraphPayload($to, $type, $input);
        $result = $this->graph->sendMessage($graphPayload);

        $message = DB::transaction(function () use ($conversation, $user, $type, $input, $result, $senderType) {
            $message = InboxMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => $senderType,
                'sender_id' => $senderType === InboxMessage::SENDER_USER ? $user->id : null,
                'direction' => InboxMessage::DIRECTION_OUTBOUND,
                'message_type' => $type,
                'payload' => $this->payloadForStorage($type, $input),
                'external_message_id' => $result['message_id'],
                'status' => $result['success'] ? InboxMessage::STATUS_SENT : InboxMessage::STATUS_FAILED,
                'error_message' => $result['success'] ? null : $result['error'],
            ]);

            $conversation->applyLastMessage($message);

            if ($senderType === InboxMessage::SENDER_USER) {
                if (! $conversation->assigned_user_id) {
                    $conversation->assigned_user_id = $user->id;
                }

                $minutes = (int) InboxSetting::current()->whatsapp_ai_bot_pause_minutes;
                $conversation->pauseBotForMinutes($minutes, false, 'agent_reply');
            }

            $conversation->save();

            return $message;
        });

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'body' => $result['error'] ?: 'فشل إرسال واتساب',
            ]);
        }

        return $message;
    }

    public function sendBotText(InboxConversation $conversation, string $body): InboxMessage
    {
        $to = InboxPhone::normalize((string) $conversation->contact?->phone_number);

        $result = $this->graph->sendText($to, $body);

        $message = InboxMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => InboxMessage::SENDER_BOT,
            'sender_id' => null,
            'direction' => InboxMessage::DIRECTION_OUTBOUND,
            'message_type' => InboxMessage::TYPE_TEXT,
            'payload' => ['text' => $body],
            'external_message_id' => $result['message_id'],
            'status' => $result['success'] ? InboxMessage::STATUS_SENT : InboxMessage::STATUS_FAILED,
            'error_message' => $result['success'] ? null : $result['error'],
        ]);

        $conversation->applyLastMessage($message);

        return $message;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buildGraphPayload(string $to, string $type, array $input): array
    {
        return match ($type) {
            InboxMessage::TYPE_TEMPLATE => [
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => (string) $input['template_name'],
                    'language' => ['code' => (string) ($input['language'] ?? 'ar')],
                    'components' => $this->templateComponents($input),
                ],
            ],
            InboxMessage::TYPE_IMAGE, InboxMessage::TYPE_DOCUMENT => $this->mediaPayload($to, $type, $input),
            InboxMessage::TYPE_LOCATION => [
                'to' => $to,
                'type' => 'location',
                'location' => [
                    'latitude' => $input['latitude'],
                    'longitude' => $input['longitude'],
                    'name' => $input['name'] ?? null,
                    'address' => $input['address'] ?? null,
                ],
            ],
            default => [
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => true,
                    'body' => (string) ($input['body'] ?? $input['text'] ?? ''),
                ],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function templateComponents(array $input): array
    {
        $components = [];
        $header = $input['header_media'] ?? null;

        if (is_array($header) && filled($header['type'] ?? null)) {
            $mediaType = (string) $header['type'];
            $parameter = ['type' => $mediaType];

            if (filled($header['id'] ?? null)) {
                $parameter[$mediaType] = array_filter([
                    'id' => $header['id'],
                    'filename' => $header['filename'] ?? null,
                ]);
            } elseif (filled($header['link'] ?? null)) {
                $parameter[$mediaType] = array_filter([
                    'link' => $header['link'],
                    'filename' => $header['filename'] ?? null,
                ]);
            }

            $components[] = [
                'type' => 'header',
                'parameters' => [$parameter],
            ];
        } elseif (filled($input['header_text'] ?? null)) {
            $components[] = [
                'type' => 'header',
                'parameters' => [[
                    'type' => 'text',
                    'text' => (string) $input['header_text'],
                ]],
            ];
        }

        $parameters = $input['parameters'] ?? [];
        $bodyParams = [];

        if (is_array($parameters)) {
            if (isset($parameters['body']) && is_array($parameters['body'])) {
                foreach ($parameters['body'] as $value) {
                    $bodyParams[] = ['type' => 'text', 'text' => (string) $value];
                }
            } else {
                foreach ($parameters as $key => $value) {
                    if (is_int($key) || $key === 'body') {
                        $bodyParams[] = ['type' => 'text', 'text' => (string) $value];
                    } elseif (is_string($key) && ! in_array($key, ['header', 'named'], true)) {
                        $bodyParams[] = [
                            'type' => 'text',
                            'parameter_name' => $key,
                            'text' => (string) $value,
                        ];
                    }
                }
            }

            if (isset($parameters['named']) && is_array($parameters['named'])) {
                foreach ($parameters['named'] as $name => $value) {
                    $bodyParams[] = [
                        'type' => 'text',
                        'parameter_name' => (string) $name,
                        'text' => (string) $value,
                    ];
                }
            }
        }

        if ($bodyParams !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParams,
            ];
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function mediaPayload(string $to, string $type, array $input): array
    {
        $node = array_filter([
            'caption' => $input['caption'] ?? $input['body'] ?? null,
            'filename' => $input['filename'] ?? null,
            'link' => $input['link'] ?? null,
            'id' => $input['media_id'] ?? null,
        ]);

        return [
            'to' => $to,
            'type' => $type === InboxMessage::TYPE_DOCUMENT ? 'document' : 'image',
            $type === InboxMessage::TYPE_DOCUMENT ? 'document' : 'image' => $node,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function payloadForStorage(string $type, array $input): array
    {
        if ($type === InboxMessage::TYPE_TEMPLATE) {
            $header = $input['header_media'] ?? null;
            $mediaUrl = null;

            if (is_array($header) && filled($header['upload_uuid'] ?? null)) {
                $upload = InboxMediaUpload::query()->find($header['upload_uuid']);
                $mediaUrl = $upload?->publicUrl();
            } elseif (is_array($header) && filled($header['link'] ?? null)) {
                $mediaUrl = $header['link'];
            }

            return [
                'template_name' => $input['template_name'] ?? null,
                'language' => $input['language'] ?? null,
                'parameters' => $input['parameters'] ?? [],
                'header_media' => $header,
                'header_media_url' => $mediaUrl,
                'text' => $input['preview_text'] ?? ('Template: '.($input['template_name'] ?? '')),
            ];
        }

        return [
            'text' => $input['body'] ?? $input['text'] ?? null,
            'caption' => $input['caption'] ?? null,
            'media_url' => $input['link'] ?? null,
            'filename' => $input['filename'] ?? null,
            'latitude' => $input['latitude'] ?? null,
            'longitude' => $input['longitude'] ?? null,
            'name' => $input['name'] ?? null,
            'address' => $input['address'] ?? null,
        ];
    }

    public function startConversation(string $phone, ?string $name = null): InboxConversation
    {
        if (! InboxPhone::isValidE164($phone)) {
            throw ValidationException::withMessages(['phone' => 'أدخل رقماً بصيغة دولية صحيحة.']);
        }

        $e164 = InboxPhone::e164($phone);

        $contact = InboxContact::query()->firstOrCreate(
            ['phone_number' => $e164],
            ['name' => $name, 'subscribed' => true, 'last_active_at' => now()]
        );

        return InboxConversation::query()->firstOrCreate(
            ['contact_id' => $contact->id],
            ['status' => InboxConversation::STATUS_OPEN]
        );
    }
}
