<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxWebhookLog extends Model
{
    protected $table = 'inbox_webhook_logs';

    protected $fillable = [
        'event',
        'outcome',
        'reason',
        'http_status',
        'phone_number_id',
        'external_message_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'http_status' => 'integer',
        ];
    }
}
