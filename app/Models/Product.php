<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'show_on_storefront',
        'image',
        'category_id',
        'brand_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'show_on_storefront' => 'boolean',
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
     * Catalog products for admin pickers (orders/quotations): active + visible on store.
     * Custom one-off lines stay out of the picker list.
     */
    public function scopeCatalog($query)
    {
        return $query
            ->where('status', 'active')
            ->where('show_on_storefront', true);
    }

    /**
     * Scope: products visible on the public website (Adventure World brand only).
     * Internal brands (e.g. WStation, Plate) and custom order/quotation lines stay hidden.
     */
    public function scopeStorefront($query)
    {
        return $query
            ->where('status', 'active')
            ->where('show_on_storefront', true)
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
            && (bool) $this->show_on_storefront
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
                ->where('show_on_storefront', true)
        );
    }

    /**
     * Create an internal product for a one-off line from orders/quotations.
     * Never appears on the public website.
     */
    public static function createCustomLine(
        string $name,
        ?string $description,
        float $unitPrice,
        ?int $brandId = null,
    ): self {
        return static::query()->create([
            'product_name' => trim($name) !== '' ? trim($name) : 'صنف مخصص',
            'description' => filled($description) ? trim($description) : null,
            'price' => round(max(0, $unitPrice), 2),
            'insurance_amount' => 0,
            'status' => 'active',
            'show_on_storefront' => false,
            'brand_id' => $brandId ?: Brand::default()->id,
            'category_id' => null,
            'image' => null,
        ]);
    }

    /**
     * Full URL for the product image (for use in frontend).
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }
        return \App\Support\MediaStorage::url($this->image);
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
