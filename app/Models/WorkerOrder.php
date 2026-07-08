<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkerOrder extends Model
{
    protected $fillable = [
        'order_id',
        'line_index',
        'product_id',
        'product_name',
        'product_image',
        'customer_name',
        'customer_address',
        'installation_date',
        'status',
        'installation_photo',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'installation_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'product_image_url',
        'installation_photo_url',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getProductImageUrlAttribute(): ?string
    {
        return $this->resolveStorageUrl($this->product_image);
    }

    public function getInstallationPhotoUrlAttribute(): ?string
    {
        return $this->resolveStorageUrl($this->installation_photo);
    }

    private function resolveStorageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset('storage/'.ltrim($path, '/'));
    }
}
