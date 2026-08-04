<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'product_id',
        'product_name',
        'description',
        'statement',
        'quantity',
        'unit_price',
        'discount_amount',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            $netUnitPrice = max(0, (float) $item->unit_price - (float) ($item->discount_amount ?? 0));
            $item->total_price = (int) $item->quantity * $netUnitPrice;
        });

        static::updating(function ($item) {
            if ($item->isDirty(['quantity', 'unit_price', 'discount_amount'])) {
                $netUnitPrice = max(0, (float) $item->unit_price - (float) ($item->discount_amount ?? 0));
                $item->total_price = (int) $item->quantity * $netUnitPrice;
            }
        });
    }
}
