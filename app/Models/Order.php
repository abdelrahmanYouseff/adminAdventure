<?php

namespace App\Models;

use App\Support\OrderInsuranceCalculator;
use App\Support\PublicAppUrl;
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
        'installation_at',
        'dismantling_at',
        'invoice_id',
        'quotation_id',
        'operations_released_at',
        'operations_released_by',
        'skip_work_order',
        'order_number',
        'location_slug',
        'total_amount',
        'discount_total',
        'tax_amount',
        'amount_paid',
        'payment_token',
        'insurance_amount',
        'insurance_original_amount',
        'insurance_status',
        'insurance_refunded_at',
        'insurance_refund_requested_at',
        'insurance_refund_requested_by',
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
        'dismantling_photos_notified_at',
        'installation_photos_notified_at',
        'work_order_issued_notified_at',
        'warehouse_returned_by',
        'warehouse_keeper_approved_at',
        'warehouse_keeper_approved_by',
        'warehouse_rejection_reason',
        'warehouse_rejected_at',
        'warehouse_rejected_by',
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
        'tax_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'insurance_original_amount' => 'decimal:2',
        'items' => 'array',
        'activity_date' => 'date',
        'installation_at' => 'datetime',
        'dismantling_at' => 'datetime',
        'whatsapp_notified_at' => 'datetime',
        'insurance_refunded_at' => 'datetime',
        'insurance_refund_requested_at' => 'datetime',
        'operations_released_at' => 'datetime',
        'skip_work_order' => 'boolean',
        'work_order_approved_at' => 'datetime',
        'warehouse_returned_at' => 'datetime',
        'dismantling_photos_notified_at' => 'datetime',
        'installation_photos_notified_at' => 'datetime',
        'work_order_issued_notified_at' => 'datetime',
        'warehouse_keeper_approved_at' => 'datetime',
        'warehouse_rejected_at' => 'datetime',
        'insurance_manager_approved_at' => 'datetime',
        'insurance_gm_approved_at' => 'datetime',
        'insurance_accounts_approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'remaining_amount',
    ];

    /**
     * Combine a calendar date with an optional H:i time into a datetime string.
     */
    public static function combineDateAndTime(?string $date, ?string $time): ?string
    {
        if (! filled($date)) {
            return null;
        }

        $time = filled($time) ? $time : '00:00';

        return $date.' '.$time;
    }

    public function scheduledInstallationDate(): mixed
    {
        return $this->installation_at ?? $this->activity_date;
    }

    public function scheduledInstallationTime(): ?string
    {
        if ($this->installation_at) {
            return $this->installation_at->format('H:i');
        }

        $raw = $this->getAttributes()['activity_time'] ?? null;

        return $raw ? \Carbon\Carbon::parse($raw)->format('H:i') : null;
    }

    public function getRemainingAmountAttribute(): float
    {
        $total = (float) ($this->attributes['total_amount'] ?? 0);
        $paid = (float) ($this->attributes['amount_paid'] ?? 0);

        return round(max(0, $total - $paid), 2);
    }

    public function ensurePaymentToken(): string
    {
        if ($this->payment_token) {
            return $this->payment_token;
        }

        $token = bin2hex(random_bytes(24));

        // Persist through the query builder: list/detail screens decorate the
        // model with computed attributes that are not real columns, and save()
        // would try to write them.
        static::query()->whereKey($this->getKey())->update(['payment_token' => $token]);

        $this->attributes['payment_token'] = $token;
        $this->syncOriginalAttribute('payment_token');

        return $token;
    }

    public function noonPaymentUrl(): ?string
    {
        if (
            $this->remaining_amount <= 0.009
            || in_array($this->status, ['cancelled', 'refunded'], true)
        ) {
            return null;
        }

        return PublicAppUrl::to('/pay/order/'.$this->ensurePaymentToken());
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

    public function operationsReleasedBy()
    {
        return $this->belongsTo(User::class, 'operations_released_by');
    }

    /**
     * Direct store orders are always visible. Quotation orders with recorded
     * payment stay hidden until the accountant releases them; zero-payment
     * quotations go live as soon as the manager approves.
     */
    public function isReleasedToOperations(): bool
    {
        if (! $this->quotation_id) {
            return true;
        }

        return filled($this->operations_released_at);
    }

    /**
     * Work orders require the order to be live and at least one payment receipt
     * approved by the accountant (partial payment is enough). Orders flagged
     * skip_work_order never get work orders.
     */
    public function shouldReleaseWorkOrders(): bool
    {
        if ($this->skip_work_order) {
            return false;
        }

        if (! $this->isReleasedToOperations()) {
            return false;
        }

        return $this->hasApprovedPaymentReceipt();
    }

    public function skipsWorkOrder(): bool
    {
        return (bool) $this->skip_work_order;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Order>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    public function scopeReleasedToOperations($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('quotation_id')
                ->orWhereNotNull('operations_released_at');
        });
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

    public function warehouseKeeperApprovedBy()
    {
        return $this->belongsTo(User::class, 'warehouse_keeper_approved_by');
    }

    public function isWorkOrderClosed(): bool
    {
        return filled($this->warehouse_keeper_approved_at);
    }

    public function canEnterReturnsFlow(): bool
    {
        return filled($this->work_order_approved_at)
            && ! in_array($this->status, ['cancelled', 'refunded'], true);
    }

    public function canEnterWarehouseQueue(): bool
    {
        return $this->canEnterReturnsFlow()
            && filled($this->warehouse_returned_at)
            && filled($this->warehouse_returned_by)
            && blank($this->warehouse_keeper_approved_at);
    }

    public function insuranceRefundRequestedBy()
    {
        return $this->belongsTo(User::class, 'insurance_refund_requested_by');
    }

    public function warehouseRejectedBy()
    {
        return $this->belongsTo(User::class, 'warehouse_rejected_by');
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
        );
    }

    public function scopeAssignedToWorker($query, User $user, ?string $taskType = null)
    {
        $workerId = (int) $user->id;
        $workerName = (string) $user->name;

        return $query->whereHas('workerAssemblers', function ($q) use ($workerId, $workerName, $taskType) {
            $q->where(function ($inner) use ($workerId, $workerName) {
                $inner->where('user_id', $workerId);

                if ($workerName !== '') {
                    $inner->orWhere('worker_name', $workerName);
                }
            });

            if ($taskType === WorkerOrderAssembler::TYPE_DISMANTLING) {
                $q->where('task_type', WorkerOrderAssembler::TYPE_DISMANTLING);
            } elseif ($taskType === WorkerOrderAssembler::TYPE_INSTALLATION) {
                $q->where(function ($inner) {
                    $inner->where('task_type', WorkerOrderAssembler::TYPE_INSTALLATION)
                        ->orWhereNull('task_type');
                });
            }
        });
    }

    public function isAssignedToWorker(User $user, ?string $taskType = null): bool
    {
        return $this->workerAssemblers()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id);

                if ($user->name !== '') {
                    $query->orWhere('worker_name', $user->name);
                }
            })
            ->when(
                $taskType === WorkerOrderAssembler::TYPE_DISMANTLING,
                fn ($query) => $query->where('task_type', WorkerOrderAssembler::TYPE_DISMANTLING),
            )
            ->when(
                $taskType === WorkerOrderAssembler::TYPE_INSTALLATION,
                fn ($query) => $query->where(function ($inner) {
                    $inner->where('task_type', WorkerOrderAssembler::TYPE_INSTALLATION)
                        ->orWhereNull('task_type');
                }),
            )
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function workerAssignmentTypes(User $user): array
    {
        $types = $this->workerAssemblers()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id);

                if ($user->name !== '') {
                    $query->orWhere('worker_name', $user->name);
                }
            })
            ->pluck('task_type')
            ->map(fn ($type) => $type ?: WorkerOrderAssembler::TYPE_INSTALLATION)
            ->unique()
            ->values()
            ->all();

        return array_values($types);
    }

    public function primaryWorkerAssignmentType(User $user): string
    {
        $types = $this->workerAssignmentTypes($user);

        if (in_array(WorkerOrderAssembler::TYPE_DISMANTLING, $types, true)
            && ! in_array(WorkerOrderAssembler::TYPE_INSTALLATION, $types, true)) {
            return WorkerOrderAssembler::TYPE_DISMANTLING;
        }

        if (in_array(WorkerOrderAssembler::TYPE_INSTALLATION, $types, true)
            && in_array(WorkerOrderAssembler::TYPE_DISMANTLING, $types, true)) {
            return 'both';
        }

        return WorkerOrderAssembler::TYPE_INSTALLATION;
    }

    /**
     * Whether the worker should currently upload dismantling (pickup) photos
     * rather than installation photos — same camera sequence as installation.
     */
    public function workerIsInDismantlingPhase(User $user): bool
    {
        if (! $this->isAssignedToWorker($user, WorkerOrderAssembler::TYPE_DISMANTLING)) {
            return false;
        }

        if (! $this->canEnterReturnsFlow()) {
            return false;
        }

        // Dismantling-only assignment from Returns.
        if (! $this->isAssignedToWorker($user, WorkerOrderAssembler::TYPE_INSTALLATION)) {
            return true;
        }

        // Both: switch to dismantling after installation photos are done.
        return $this->hasAllWorkerPhotos();
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
