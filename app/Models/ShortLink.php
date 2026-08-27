<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortLink extends Model
{
    public const TYPE_DELIVERY_NOTE = 'delivery_note';

    public const TYPE_QUOTATION_PAYMENT = 'quotation_payment';

    protected $fillable = [
        'code',
        'type',
        'order_id',
        'target_key',
        'expires_at',
        'hits',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'hits' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
