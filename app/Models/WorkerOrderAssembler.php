<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerOrderAssembler extends Model
{
    public const TYPE_INSTALLATION = 'installation';

    public const TYPE_DISMANTLING = 'dismantling';

    protected $fillable = [
        'order_id',
        'worker_order_id',
        'worker_name',
        'task_type',
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

    public function isInstallation(): bool
    {
        return ($this->task_type ?: self::TYPE_INSTALLATION) === self::TYPE_INSTALLATION;
    }

    public function isDismantling(): bool
    {
        return $this->task_type === self::TYPE_DISMANTLING;
    }

    public function scopeInstallation(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) {
            $inner->where('task_type', self::TYPE_INSTALLATION)
                ->orWhereNull('task_type');
        });
    }

    public function scopeDismantling(Builder $query): Builder
    {
        return $query->where('task_type', self::TYPE_DISMANTLING);
    }

    public function taskLabel(): string
    {
        return $this->isDismantling() ? 'فك' : 'تركيب';
    }
}
