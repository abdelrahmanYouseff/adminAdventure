<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'valid_until',
        'notes',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'user_id',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Quotation $quotation) {
            if (empty($quotation->quotation_number)) {
                $quotation->quotation_number = self::generateQuotationNumber();
            }
        });
    }

    /**
     * Generate a unique quotation number: QA-YYYYMM{counter from 100}.
     * Example: QA-202607100
     */
    public static function generateQuotationNumber(): string
    {
        return \App\Support\MonthlyDocumentNumber::next(
            'QA',
            self::query(),
            'quotation_number'
        );
    }
}
