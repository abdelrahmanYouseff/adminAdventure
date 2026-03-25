<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'category_name',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    /**
     * Scope: categories visible in the API (is_visible = true).
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
