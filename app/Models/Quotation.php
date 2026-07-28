<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_number',
        'brand_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'company_tax_number',
        'valid_until',
        'activity_at',
        'installation_at',
        'dismantling_at',
        'notes',
        'subtotal',
        'discount_total',
        'tax_amount',
        'insurance_amount',
        'total_amount',
        'amount_paid',
        'status',
        'user_id',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'activity_at' => 'datetime',
        'installation_at' => 'datetime',
        'dismantling_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Order::class);
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
