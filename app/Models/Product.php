<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        'insurance_amount',
        'status',
        'image',
        'category_id',
        'brand_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
    ];

    protected $appends = ['image_url'];

    /**
     * Scope: active products only (status = 'active').
     * Used by API endpoints — admin dashboard bypasses this scope intentionally.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: products visible on the public website (Adventure World brand only).
     * Internal brands (e.g. WStation, Plate) stay admin-only.
     */
    public function scopeStorefront($query)
    {
        return $query
            ->where('status', 'active')
            ->where('brand_id', Brand::storefront()->id);
    }

    public static function resolveBrandIdForIds(array $productIds): int
    {
        $ids = array_values(array_filter(array_map('intval', $productIds)));
        if ($ids === []) {
            return (int) Brand::default()->id;
        }

        $brandId = static::query()
            ->whereIn('id', $ids)
            ->whereNotNull('brand_id')
            ->value('brand_id');

        return $brandId ? (int) $brandId : (int) Brand::default()->id;
    }

    public function isStorefrontVisible(): bool
    {
        return $this->status === 'active'
            && (int) $this->brand_id === (int) Brand::storefront()->id;
    }

    /**
     * Validation rule: product must be active and belong to the public storefront brand.
     */
    public static function storefrontExistsRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('products', 'id')->where(
            fn ($query) => $query
                ->where('brand_id', Brand::storefront()->id)
                ->where('status', 'active')
        );
    }

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

    public function brand()
    {
        return $this->belongsTo(Brand::class);
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
