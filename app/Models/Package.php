<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'image',
        'price',
        'status',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return MediaStorage::url($this->image);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
