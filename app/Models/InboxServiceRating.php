<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxServiceRating extends Model
{
    public const STATUS_INVITE = 'invite';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STEP_AWAITING_SCORE = 'awaiting_score';

    public const STEP_AWAITING_COMMENT = 'awaiting_comment';

    public const STEP_DONE = 'done';

    protected $table = 'inbox_service_ratings';

    protected $fillable = [
        'conversation_id',
        'order_id',
        'status',
        'step',
        'score',
        'comment',
        'media_uuid',
        'invited_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'invited_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(InboxConversation::class, 'conversation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_INVITE, self::STATUS_IN_PROGRESS], true);
    }
}
