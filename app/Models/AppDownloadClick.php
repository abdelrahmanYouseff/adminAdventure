<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppDownloadClick extends Model
{
    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_ANDROID = 'android';

    protected $fillable = [
        'platform',
        'session_id',
        'user_id',
        'ip_address',
        'user_agent',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
