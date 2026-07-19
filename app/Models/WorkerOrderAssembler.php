<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerOrderAssembler extends Model
{
    protected $fillable = [
        'order_id',
        'worker_order_id',
        'worker_name',
        'user_id',
        'created_by',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function workerOrder(): BelongsTo
    {
        return $this->belongsTo(WorkerOrder::class);
    }

    public function workerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
