<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address',
        'activity_date',
        'activity_time',
        'invoice_id',
        'quotation_id',
        'order_number',
        'location_slug',
        'total_amount',
        'discount_total',
        'amount_paid',
        'insurance_amount',
        'insurance_original_amount',
        'insurance_status',
        'insurance_refunded_at',
        'currency',
        'status',
        'payment_method',
        'payment_status',
        'payment_id',
        'payment_order_reference',
        'whatsapp_notified_at',
        'notes',
        'items',
        'work_order_approved_at',
        'work_order_approved_by',
        'warehouse_returned_at',
        'warehouse_returned_by',
        'insurance_manager_approved_at',
        'insurance_manager_approved_by',
        'insurance_gm_approved_at',
        'insurance_gm_approved_by',
        'insurance_accounts_approved_at',
        'insurance_accounts_approved_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'insurance_original_amount' => 'decimal:2',
        'items' => 'array',
        'activity_date' => 'date',
        'whatsapp_notified_at' => 'datetime',
        'insurance_refunded_at' => 'datetime',
        'work_order_approved_at' => 'datetime',
        'warehouse_returned_at' => 'datetime',
        'insurance_manager_approved_at' => 'datetime',
        'insurance_gm_approved_at' => 'datetime',
        'insurance_accounts_approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'remaining_amount',
    ];

    public function getRemainingAmountAttribute(): float
    {
        $total = (float) ($this->attributes['total_amount'] ?? 0);
        $paid = (float) ($this->attributes['amount_paid'] ?? 0);

        return round(max(0, $total - $paid), 2);
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->location_slug)) {
                $order->location_slug = self::generateLocationSlug();
            }
        });
    }

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the invoice for this order.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Quotation that originated this order (if any).
     */
    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Get the products for this order.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_product')
                    ->withPivot('quantity', 'price', 'discount_amount', 'insurance_amount')
                    ->withTimestamps();
    }

    /**
     * Resolve brand from order products or line items JSON.
     */
    public function resolveBrandId(): int
    {
        if ($this->relationLoaded('products') && $this->products->isNotEmpty()) {
            $brandId = $this->products->first(fn ($product) => $product->brand_id !== null)?->brand_id;
            if ($brandId) {
                return (int) $brandId;
            }
        }

        $brandId = $this->products()->whereNotNull('brand_id')->value('products.brand_id');
        if ($brandId) {
            return (int) $brandId;
        }

        if (is_array($this->items)) {
            $productIds = collect($this->items)
                ->pluck('product_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($productIds !== []) {
                return Product::resolveBrandIdForIds($productIds);
            }
        }

        return (int) Brand::default()->id;
    }

    public function workerOrders()
    {
        return $this->hasMany(WorkerOrder::class);
    }

    public function workerAssemblers()
    {
        return $this->hasMany(WorkerOrderAssembler::class);
    }

    public function workerNotes()
    {
        return $this->hasMany(WorkerOrderNote::class);
    }

    public function paymentReceipts()
    {
        return $this->hasMany(OrderPaymentReceipt::class);
    }

    public function hasApprovedPaymentReceipt(): bool
    {
        if ($this->relationLoaded('paymentReceipts')) {
            return $this->paymentReceipts
                ->contains(fn ($receipt) => $receipt->approval_status === OrderPaymentReceipt::STATUS_APPROVED);
        }

        return $this->paymentReceipts()
            ->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)
            ->exists();
    }

    public function workOrderApprovedBy()
    {
        return $this->belongsTo(User::class, 'work_order_approved_by');
    }

    public function warehouseReturnedBy()
    {
        return $this->belongsTo(User::class, 'warehouse_returned_by');
    }

    public function insuranceManagerApprovedBy()
    {
        return $this->belongsTo(User::class, 'insurance_manager_approved_by');
    }

    public function insuranceGmApprovedBy()
    {
        return $this->belongsTo(User::class, 'insurance_gm_approved_by');
    }

    public function insuranceAccountsApprovedBy()
    {
        return $this->belongsTo(User::class, 'insurance_accounts_approved_by');
    }

    public function hasAllWorkerPhotos(): bool
    {
        $lines = $this->relationLoaded('workerOrders')
            ? $this->workerOrders
            : $this->workerOrders()->get();

        if ($lines->isEmpty()) {
            return false;
        }

        return $lines->every(
            fn (WorkerOrder $line) => $line->status === 'completed'
                && filled($line->installation_photo)
                && filled($line->pickup_photo)
        );
    }

    public function scopeAssignedToWorker($query, User $user)
    {
        $workerId = (int) $user->id;
        $workerName = (string) $user->name;

        return $query->whereHas('workerAssemblers', function ($q) use ($workerId, $workerName) {
            $q->where(function ($inner) use ($workerId, $workerName) {
                $inner->where('user_id', $workerId);

                if ($workerName !== '') {
                    $inner->orWhere('worker_name', $workerName);
                }
            });
        });
    }

    public function isAssignedToWorker(User $user): bool
    {
        return $this->workerAssemblers()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id);

                if ($user->name !== '') {
                    $query->orWhere('worker_name', $user->name);
                }
            })
            ->exists();
    }

    /**
     * Generate a unique order number.
     */
    public static function generateOrderNumber()
    {
        $prefix = 'ORD';
        $year = date('Y');
        $month = date('m');

        $lastOrder = self::where('order_number', 'like', "{$prefix}-{$year}{$month}-%")
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $newNumber);
    }

    public static function generateLocationSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(5));
        } while (self::where('location_slug', $slug)->exists());

        return $slug;
    }
}
