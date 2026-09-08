<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboxConversation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'inbox_conversations';

    protected $fillable = [
        'contact_id',
        'status',
        'assigned_user_id',
        'last_message_at',
        'last_inbound_at',
        'bot_paused_until',
        'needs_human_agent',
        'handoff_reason',
        'ai_lead_requirements',
        'last_message_preview',
        'last_message_direction',
        'last_sender_type',
        'last_message_type',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'bot_paused_until' => 'datetime',
            'needs_human_agent' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(InboxContact::class, 'contact_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'conversation_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(InboxServiceRating::class, 'conversation_id');
    }

    public function isBotPaused(?CarbonInterface $now = null): bool
    {
        if ($this->needs_human_agent) {
            return true;
        }

        if (! $this->bot_paused_until) {
            return false;
        }

        return $this->bot_paused_until->gt($now ?? now());
    }

    public function pauseBotForMinutes(int $minutes, bool $needsHuman = false, ?string $reason = null): void
    {
        $this->forceFill([
            'bot_paused_until' => $minutes >= 525600
                ? now()->addYears(20)
                : now()->addMinutes(max(1, $minutes)),
            'needs_human_agent' => $needsHuman,
            'handoff_reason' => $reason,
        ])->save();
    }

    public function requestHuman(string $reason = 'manual'): void
    {
        $this->forceFill([
            'needs_human_agent' => true,
            'handoff_reason' => $reason,
            'bot_paused_until' => now()->addYears(20),
        ])->save();
    }

    public function resumeBot(): void
    {
        $this->forceFill([
            'needs_human_agent' => false,
            'handoff_reason' => null,
            'bot_paused_until' => null,
        ])->save();
    }

    public function applyLastMessage(InboxMessage $message): void
    {
        $preview = $message->previewText();

        $this->forceFill([
            'last_message_at' => $message->created_at ?? now(),
            'last_message_preview' => mb_substr($preview, 0, 180),
            'last_message_direction' => $message->direction,
            'last_sender_type' => $message->sender_type,
            'last_message_type' => $message->message_type,
            'last_inbound_at' => $message->direction === InboxMessage::DIRECTION_INBOUND
                ? ($message->created_at ?? now())
                : $this->last_inbound_at,
        ])->save();
    }
}
