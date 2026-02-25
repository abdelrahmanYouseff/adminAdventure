<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\CartItem;
use App\Models\Rental;
use App\Models\Package;

class Product extends Model
{
    protected $fillable = [
        'product_name',
        'description',
        'price',
        'status',
        'image',
        'category_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    protected $appends = ['image_url'];

    /**
     * Full URL for the product image (for use in frontend).
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }
        // Use asset() so the URL works with any APP_URL and avoids symlink issues
        return Str::startsWith($this->image, ['http://', 'https://'])
            ? $this->image
            : asset('storage/'.ltrim($this->image, '/'));
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the cart items for this product.
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the rentals for this product.
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class);
    }

    /**
     * Get the orders for this product.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_product')
                    ->withPivot('quantity', 'price')
                    ->withTimestamps();
    }
}
