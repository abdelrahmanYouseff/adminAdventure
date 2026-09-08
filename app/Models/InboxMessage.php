<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const SENDER_CONTACT = 'contact';

    public const SENDER_USER = 'user';

    public const SENDER_BOT = 'bot';

    public const SENDER_SYSTEM = 'system';

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_LOCATION = 'location';

    public const TYPE_TEMPLATE = 'template';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    protected $table = 'inbox_messages';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'direction',
        'message_type',
        'payload',
        'external_message_id',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(InboxConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function previewText(): string
    {
        $payload = $this->payload ?? [];
        $caption = trim((string) ($payload['caption'] ?? $payload['text'] ?? ''));

        return match ($this->message_type) {
            self::TYPE_IMAGE => $caption !== '' ? $caption : 'Photo',
            self::TYPE_DOCUMENT => $this->isVoiceNote()
                ? 'Voice note'
                : ($caption !== '' ? $caption : ((string) ($payload['filename'] ?? 'Document'))),
            self::TYPE_LOCATION => (string) ($payload['name'] ?? $payload['address'] ?? 'Location'),
            self::TYPE_TEMPLATE => (string) ($payload['template_name'] ?? 'Template'),
            default => $caption !== '' ? $caption : 'Message',
        };
    }

    public function isVoiceNote(): bool
    {
        $mime = strtolower((string) (($this->payload['mime_type'] ?? '') ?: ''));
        $kind = strtolower((string) (($this->payload['kind'] ?? '') ?: ''));

        return $kind === 'audio' || str_starts_with($mime, 'audio/');
    }
}
