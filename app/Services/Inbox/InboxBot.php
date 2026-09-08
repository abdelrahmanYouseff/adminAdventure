<?php

namespace App\Services\Inbox;

use App\Models\InboxConversation;
use App\Models\InboxMediaUpload;
use App\Models\InboxMessage;
use App\Models\InboxSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InboxBot
{
    public function __construct(
        private InboxBotTools $tools,
        private OutboundMessageSender $outbound,
        private QaRatingFlow $qa,
    ) {}

    public function reply(InboxConversation $conversation, InboxMessage $inbound): void
    {
        $settings = InboxSetting::current();
        $conversation->refresh();

        if (! $settings->botIsEnabled()) {
            return;
        }

        if ($conversation->needs_human_agent || $conversation->isBotPaused()) {
            return;
        }

        if ($this->qa->activeFor($conversation)) {
            return;
        }

        $latestInbound = InboxMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', InboxMessage::DIRECTION_INBOUND)
            ->latest('id')
            ->first();

        if (! $latestInbound || $latestInbound->id !== $inbound->id) {
            return;
        }

        $lock = Cache::lock('inbox-bot-'.$conversation->id, 45);

        if (! $lock->get()) {
            return;
        }

        try {
            $userText = $this->inboundText($inbound);

            if ($userText === null) {
                $this->outbound->sendBotText(
                    $conversation,
                    "تعذر تفريغ الملاحظة الصوتية، الرجاء كتابة رسالتك نصاً.\nWe could not transcribe the voice note. Please type your message."
                );

                return;
            }

            $answer = $this->complete($conversation, $settings, $userText);

            if ($answer === null || trim($answer) === '') {
                return;
            }

            $conversation->refresh();

            if ($conversation->needs_human_agent || $conversation->isBotPaused()) {
                return;
            }

            $max = max(80, (int) $settings->whatsapp_ai_max_reply_chars);
            $answer = mb_substr(trim($answer), 0, $max);

            $this->outbound->sendBotText($conversation, $answer);
        } finally {
            $lock->release();
        }
    }

    private function inboundText(InboxMessage $message): ?string
    {
        $payload = $message->payload ?? [];
        $text = trim((string) ($payload['text'] ?? $payload['caption'] ?? ''));

        if ($message->isVoiceNote()) {
            $transcript = $this->transcribe($payload['media_uuid'] ?? null);

            return $transcript;
        }

        if ($message->message_type === InboxMessage::TYPE_LOCATION) {
            return 'Location: '.($payload['name'] ?? '').' '.($payload['address'] ?? '').' '.$payload['latitude'].','.$payload['longitude'];
        }

        return $text !== '' ? $text : ($message->previewText());
    }

    private function transcribe(mixed $uuid): ?string
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $upload = InboxMediaUpload::query()->find($uuid);
        $contents = $upload?->contents();

        if (! $upload || ! $contents) {
            return null;
        }

        $settings = InboxSetting::current();
        $key = $settings->resolvedOpenaiKey();

        if ($key === '') {
            return null;
        }

        $filename = $upload->original_name ?: ('voice.'.$this->ext($upload->mime));

        try {
            $response = Http::timeout(60)
                ->withToken($key)
                ->attach('file', $contents, $filename)
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                    'response_format' => 'json',
                ]);

            if (! $response->successful()) {
                Log::warning('Inbox voice transcription failed', ['body' => $response->body()]);

                return null;
            }

            $text = trim((string) $response->json('text'));

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('Inbox voice transcription error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function complete(InboxConversation $conversation, InboxSetting $settings, string $userText): ?string
    {
        $key = $settings->resolvedOpenaiKey();
        $model = $settings->whatsapp_ai_model ?: 'gpt-4o-mini';

        $history = InboxMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $messages = [
            [
                'role' => 'system',
                'content' => trim(implode("\n\n", array_filter([
                    $settings->whatsapp_ai_system_prompt,
                    'Purpose: '.$settings->whatsapp_ai_purpose,
                    'Tone: '.$settings->whatsapp_ai_tone,
                    'Handoff rules: '.$settings->whatsapp_ai_handoff_rules,
                    'Company: عالم المغامرة للترفيه. Timezone: '.$settings->timezone,
                    'Max reply characters: '.$settings->whatsapp_ai_max_reply_chars,
                ]))),
            ],
        ];

        foreach ($history as $item) {
            $content = (string) (($item->payload['text'] ?? '') ?: $item->previewText());
            $role = $item->direction === InboxMessage::DIRECTION_INBOUND ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $userText];

        try {
            for ($i = 0; $i < 4; $i++) {
                $response = Http::timeout(45)
                    ->withToken($key)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => $messages,
                        'tools' => $this->tools->definitions(),
                        'tool_choice' => 'auto',
                        'temperature' => 0.4,
                    ]);

                if (! $response->successful()) {
                    Log::warning('Inbox OpenAI failed', ['body' => $response->body()]);

                    return null;
                }

                $choice = $response->json('choices.0.message') ?? [];
                $toolCalls = $choice['tool_calls'] ?? [];

                if (is_array($toolCalls) && $toolCalls !== []) {
                    $messages[] = $choice;

                    foreach ($toolCalls as $call) {
                        $name = (string) data_get($call, 'function.name', '');
                        $argsRaw = (string) data_get($call, 'function.arguments', '{}');
                        $args = json_decode($argsRaw, true);
                        if (! is_array($args)) {
                            $args = [];
                        }

                        $result = $this->tools->call($conversation, $name, $args);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $call['id'] ?? uniqid('tool'),
                            'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                        ];
                    }

                    continue;
                }

                return isset($choice['content']) && is_string($choice['content'])
                    ? $choice['content']
                    : null;
            }
        } catch (\Throwable $e) {
            Log::warning('Inbox bot complete error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function ext(?string $mime): string
    {
        return match (strtolower((string) $mime)) {
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            default => 'ogg',
        };
    }
}
