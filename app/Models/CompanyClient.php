<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyClient extends Model
{
    protected $fillable = [
        'company_name',
        'contact_name',
        'phone',
        'phone_secondary',
        'email',
        'address',
        'tax_number',
        'iban',
        'iban_image',
        'notes',
    ];

    protected $appends = [
        'iban_image_url',
    ];

    public function getIbanImageUrlAttribute(): ?string
    {
        if (! $this->iban_image) {
            return null;
        }

        return \App\Support\MediaStorage::url($this->iban_image);
    }
}
