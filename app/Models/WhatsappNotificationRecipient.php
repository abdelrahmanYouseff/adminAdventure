<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappNotificationRecipient extends Model
{
    protected $fillable = [
        'phone',
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function displayPhone(): string
    {
        $digits = preg_replace('/\D/', '', $this->phone) ?? '';

        if (str_starts_with($digits, '966') && strlen($digits) === 12) {
            return '+966 '.substr($digits, 3, 2).' '.substr($digits, 5, 3).' '.substr($digits, 8);
        }

        return '+'.$digits;
    }
}
