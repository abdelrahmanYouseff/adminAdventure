<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
