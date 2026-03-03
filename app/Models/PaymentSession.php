<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSession extends Model
{
    protected $fillable = [
        'merchant_reference',
        'user_id',
        'amount',
        'currency',
        'payload',
        'noon_order_id',
        'used_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
