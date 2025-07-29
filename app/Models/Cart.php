<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the cart.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the cart items for the cart.
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the total price of all items in the cart.
     */
    public function getTotalPriceAttribute()
    {
        return $this->items->sum(function ($item) {
            $days = \Carbon\Carbon::parse($item->rental_start_date)
                ->diffInDays($item->rental_end_date) + 1;
            return $item->product->price * $item->quantity * $days;
        });
    }
}
