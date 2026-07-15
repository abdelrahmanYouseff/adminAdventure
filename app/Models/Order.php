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
        'invoice_id',
        'order_number',
        'location_slug',
        'total_amount',
        'currency',
        'status',
        'payment_method',
        'payment_status',
        'payment_id',
        'payment_order_reference',
        'whatsapp_notified_at',
        'notes',
        'items',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'items' => 'array',
        'activity_date' => 'date',
        'whatsapp_notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
     * Get the products for this order.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_product')
                    ->withPivot('quantity', 'price')
                    ->withTimestamps();
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
