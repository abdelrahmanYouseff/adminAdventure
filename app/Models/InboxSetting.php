<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class InboxSetting extends Model
{
    protected $table = 'inbox_settings';

    protected $fillable = [
        'timezone',
        'verify_token',
        'waba_id',
        'app_secret',
        'whatsapp_ai_enabled',
        'whatsapp_ai_openai_key',
        'whatsapp_ai_model',
        'whatsapp_ai_system_prompt',
        'whatsapp_ai_purpose',
        'whatsapp_ai_tone',
        'whatsapp_ai_handoff_rules',
        'whatsapp_ai_max_reply_chars',
        'whatsapp_ai_bot_pause_minutes',
        'qa_rating_enabled',
        'qa_rating_delay_amount',
        'qa_rating_delay_unit',
        'qa_rating_template_name',
        'qa_rating_template_language',
        'qa_rating_notify_emails',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_ai_enabled' => 'boolean',
            'qa_rating_enabled' => 'boolean',
            'whatsapp_ai_max_reply_chars' => 'integer',
            'whatsapp_ai_bot_pause_minutes' => 'integer',
            'qa_rating_delay_amount' => 'integer',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();

        if ($row) {
            return $row;
        }

        return static::query()->create(static::defaults());
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'timezone' => 'Asia/Riyadh',
            'verify_token' => (string) config('services.whatsapp.verify_token', ''),
            'waba_id' => (string) config('services.whatsapp.waba_id', ''),
            'whatsapp_ai_enabled' => false,
            'whatsapp_ai_model' => (string) config('services.openai.model', 'gpt-4o-mini'),
            'whatsapp_ai_system_prompt' => static::defaultSystemPrompt(),
            'whatsapp_ai_purpose' => static::defaultPurpose(),
            'whatsapp_ai_tone' => static::defaultTone(),
            'whatsapp_ai_handoff_rules' => static::defaultHandoffRules(),
            'whatsapp_ai_max_reply_chars' => 800,
            'whatsapp_ai_bot_pause_minutes' => 720,
            'qa_rating_enabled' => false,
            'qa_rating_delay_amount' => 1,
            'qa_rating_delay_unit' => 'days',
            'qa_rating_template_language' => 'ar',
        ];
    }

    public function resolvedWabaId(): string
    {
        $fromEnv = trim((string) config('services.whatsapp.waba_id', ''));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return trim((string) $this->waba_id);
    }

    public function resolvedVerifyToken(): string
    {
        $fromEnv = trim((string) config('services.whatsapp.verify_token', ''));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return trim((string) $this->verify_token);
    }

    public function resolvedAppSecret(): string
    {
        $fromEnv = trim((string) config('services.whatsapp.app_secret', ''));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return $this->plainAppSecret();
    }

    public function resolvedOpenaiKey(): string
    {
        $fromEnv = trim((string) config('services.openai.api_key', ''));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return $this->plainOpenaiKey();
    }

    public function hasOpenaiKey(): bool
    {
        return $this->resolvedOpenaiKey() !== '';
    }

    public function hasAppSecret(): bool
    {
        return $this->resolvedAppSecret() !== '';
    }

    public function setEncryptedAppSecret(?string $value): void
    {
        $trimmed = is_string($value) ? trim($value) : '';
        $this->app_secret = $trimmed === '' ? null : Crypt::encryptString($trimmed);
    }

    public function setEncryptedOpenaiKey(?string $value): void
    {
        $trimmed = is_string($value) ? trim($value) : '';
        $this->whatsapp_ai_openai_key = $trimmed === '' ? null : Crypt::encryptString($trimmed);
    }

    public function plainAppSecret(): string
    {
        return $this->decryptNullable($this->attributes['app_secret'] ?? null);
    }

    public function plainOpenaiKey(): string
    {
        return $this->decryptNullable($this->attributes['whatsapp_ai_openai_key'] ?? null);
    }

    /**
     * @return list<string>
     */
    public function qaNotifyEmails(): array
    {
        $raw = trim((string) $this->qa_rating_notify_emails);

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $email) => trim($email),
            preg_split('/[\s,;]+/', $raw) ?: []
        )));
    }

    public function botIsEnabled(): bool
    {
        return $this->whatsapp_ai_enabled && $this->hasOpenaiKey();
    }

    public static function defaultSystemPrompt(): string
    {
        return <<<'TXT'
You are the WhatsApp customer-service assistant for عالم المغامرة للترفيه (Adventure World), an entertainment equipment rental company in Saudi Arabia.
Help customers with products, quotations, orders, installation/dismantling, invoices, and lead capture.
Always reply in the customer's language (Arabic or English). Keep answers concise and within the character limit.
Never invent prices, stock, or booking details — use the provided tools. If you cannot help, request a human agent.
Phone numbers and order IDs must be written as-is.
TXT;
    }

    public static function defaultPurpose(): string
    {
        return 'Answer Adventure World rental customers on WhatsApp: catalog questions, existing orders, invoices, scheduling, and capturing lead requirements.';
    }

    public static function defaultTone(): string
    {
        return 'Friendly, professional, concise. Bilingual Arabic/English. No slang. Confirm facts before promising.';
    }

    public static function defaultHandoffRules(): string
    {
        return 'Hand off to a human when: the customer is angry, asks for a manager, disputes payment or damage, needs a custom quote beyond catalog tools, or you cannot answer after using tools.';
    }

    private function decryptNullable(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }
}
