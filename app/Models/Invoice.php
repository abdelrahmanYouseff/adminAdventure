<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'user_id',
        'rental_id',
        'invoice_number',
        'amount',
        'status',
        'payment_method',
        'issued_at',
        'due_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the invoice.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the rental for this invoice.
     */
    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Get the product through the rental.
     */
    public function product()
    {
        return $this->hasOneThrough(Product::class, Rental::class, 'id', 'id', 'rental_id', 'product_id');
    }

    /**
     * Check if the invoice is overdue.
     */
    public function getIsOverdueAttribute()
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    /**
     * Check if the invoice is paid.
     */
    public function getIsPaidAttribute()
    {
        return $this->status === 'paid';
    }

    /**
     * Get the order for this invoice.
     */
    public function order()
    {
        return $this->hasOne(Order::class);
    }

    /**
     * Generate a unique invoice number: S-YYYYMM{counter from 100}.
     * Example: S-202607100
     */
    public static function generateInvoiceNumber(): string
    {
        return \App\Support\MonthlyDocumentNumber::next(
            'S',
            self::query(),
            'invoice_number'
        );
    }
}
