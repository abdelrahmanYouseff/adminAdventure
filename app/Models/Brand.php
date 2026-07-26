<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            if (! $brand->slug) {
                $brand->slug = static::uniqueSlug($brand->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return Str::startsWith($this->logo, ['http://', 'https://'])
            ? $this->logo
            : asset('storage/'.ltrim($this->logo, '/'));
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public static function default(): self
    {
        return static::query()->firstOrCreate(
            ['slug' => 'adventure-world'],
            [
                'name' => 'شركة عالم المغامرة',
                'description' => 'المنتجات الخاصة بشركة عالم المغامرة',
                'is_active' => true,
            ],
        );
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
