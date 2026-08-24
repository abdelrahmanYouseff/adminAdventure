<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'status',
        'subject',
        'order_number',
        'recipients',
        'recipients_count',
        'error_message',
        'meta',
        'sent_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'meta' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
