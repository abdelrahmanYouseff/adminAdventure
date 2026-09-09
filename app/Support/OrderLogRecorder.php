<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OrderLogRecorder
{
    /**
     * Automatic / notification fields that should not count as a staff edit.
     *
     * @var list<string>
     */
    private const IGNORED_ATTRIBUTES = [
        'updated_at',
        'whatsapp_notified_at',
        'delivery_note_whatsapp_notified_at',
        'installation_photos_notified_at',
        'dismantling_photos_notified_at',
        'work_order_issued_notified_at',
    ];

    public static function created(Order $order): OrderLog
    {
        return self::record($order, OrderLog::ACTION_CREATED);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function updated(Order $order, array $changes = []): ?OrderLog
    {
        if ($changes === []) {
            $changes = self::meaningfulChanges($order);
        }

        if ($changes === []) {
            return null;
        }

        if (! Auth::check()) {
            return null;
        }

        return self::record($order, OrderLog::ACTION_UPDATED, $changes);
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function meaningfulChanges(Order $order): array
    {
        $changes = [];

        foreach ($order->getChanges() as $key => $value) {
            if (in_array($key, self::IGNORED_ATTRIBUTES, true)) {
                continue;
            }

            $changes[$key] = [
                'from' => $order->getOriginal($key),
                'to' => $value,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function record(Order $order, string $action, array $changes = []): OrderLog
    {
        $user = Auth::user();
        $actor = $user instanceof User ? $user : null;

        return OrderLog::query()->create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $actor?->id,
            'user_name' => $actor?->customer_name ?: $actor?->email,
            'user_role' => $actor?->role,
            'action' => $action,
            'changes' => $changes !== [] ? $changes : null,
        ]);
    }
}
