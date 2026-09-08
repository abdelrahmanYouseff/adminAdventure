<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxQuickReply extends Model
{
    protected $table = 'inbox_quick_replies';

    protected $fillable = [
        'title',
        'body',
    ];
}
