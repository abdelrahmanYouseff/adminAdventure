<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InboxContact extends Model
{
    protected $table = 'inbox_contacts';

    protected $fillable = [
        'phone_number',
        'name',
        'metadata',
        'last_active_at',
        'subscribed',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_active_at' => 'datetime',
            'subscribed' => 'boolean',
        ];
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(InboxConversation::class, 'contact_id');
    }

    public function displayName(): string
    {
        $name = trim((string) $this->name);

        return $name !== '' ? $name : $this->phone_number;
    }
}
