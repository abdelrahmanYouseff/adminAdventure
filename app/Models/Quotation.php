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
        'terms',
        'subtotal',
        'discount_total',
        'tax_amount',
        'insurance_amount',
        'total_amount',
        'amount_paid',
        'show_online_payment',
        'payment_token',
        'status',
        'user_id',
        'approved_at',
        'approved_by',
        'accountant_approved_at',
        'accountant_approved_by',
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
        'show_online_payment' => 'boolean',
        'terms' => 'array',
        'approved_at' => 'datetime',
        'accountant_approved_at' => 'datetime',
    ];

    public function amountDue(): float
    {
        $total = round((float) ($this->total_amount ?? 0), 2);
        $paid = round((float) ($this->amount_paid ?? 0), 2);

        return round(max(0, $total - $paid), 2);
    }

    public function ensurePaymentToken(): string
    {
        if ($this->payment_token) {
            return $this->payment_token;
        }

        $this->forceFill([
            'payment_token' => bin2hex(random_bytes(24)),
        ])->save();

        return (string) $this->payment_token;
    }

    public function paymentUrl(): ?string
    {
        if (! $this->show_online_payment) {
            return null;
        }

        if ($this->amountDue() <= 0.009) {
            return null;
        }

        return url('/q/'.$this->ensurePaymentToken());
    }

    public function noonPaymentUrl(): ?string
    {
        return $this->paymentUrl();
    }

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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function accountantApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_approved_by');
    }

    public function isManagerApproved(): bool
    {
        return filled($this->approved_at) || $this->status === 'accepted';
    }

    public function isAccountantApproved(): bool
    {
        if (filled($this->accountant_approved_at)) {
            return true;
        }

        $order = $this->relationLoaded('order') ? $this->order : $this->order()->first();

        return filled($order?->operations_released_at);
    }

    /**
     * pending_approval | pending_accountant | released | rejected | expired
     */
    public function approvalStage(): string
    {
        if ($this->status === 'rejected') {
            return 'rejected';
        }

        if ($this->status === 'expired') {
            return 'expired';
        }

        if ($this->isAccountantApproved()) {
            return 'released';
        }

        if ($this->isManagerApproved()) {
            return 'pending_accountant';
        }

        return 'pending_approval';
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
