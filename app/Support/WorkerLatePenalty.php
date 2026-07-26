<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkerLatePenalty
{
    /**
     * Compare scheduled activity datetime with the earliest installation completion.
     *
     * @return array{
     *     is_late: bool,
     *     delay_minutes: int,
     *     delay_hours: int,
     *     delay_remainder_minutes: int,
     *     scheduled_at: string|null,
     *     installed_at: string|null
     * }|null
     */
    public static function forOrder(Order $order): ?array
    {
        $rawTime = $order->getAttributes()['activity_time'] ?? null;
        $activityDate = $order->activity_date;

        if (blank($rawTime) || ! $activityDate) {
            return null;
        }

        $lines = $order->relationLoaded('workerOrders')
            ? $order->workerOrders
            : $order->workerOrders()->get();

        $installedAt = self::earliestCompletedAt($lines);

        if (! $installedAt) {
            return null;
        }

        $datePart = $activityDate instanceof Carbon
            ? $activityDate->format('Y-m-d')
            : Carbon::parse($activityDate)->format('Y-m-d');

        $timePart = Carbon::parse($rawTime)->format('H:i:s');
        $scheduledAt = Carbon::parse($datePart.' '.$timePart);

        if ($installedAt->lessThanOrEqualTo($scheduledAt)) {
            return [
                'is_late' => false,
                'delay_minutes' => 0,
                'delay_hours' => 0,
                'delay_remainder_minutes' => 0,
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'installed_at' => $installedAt->toIso8601String(),
            ];
        }

        $delayMinutes = (int) $scheduledAt->diffInMinutes($installedAt);

        return [
            'is_late' => $delayMinutes > 0,
            'delay_minutes' => $delayMinutes,
            'delay_hours' => intdiv($delayMinutes, 60),
            'delay_remainder_minutes' => $delayMinutes % 60,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'installed_at' => $installedAt->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $lines
     */
    private static function earliestCompletedAt(Collection $lines): ?Carbon
    {
        $timestamps = $lines
            ->map(fn ($line) => $line->completed_at)
            ->filter()
            ->values();

        if ($timestamps->isEmpty()) {
            return null;
        }

        return $timestamps
            ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
            ->sortBy(fn (Carbon $carbon) => $carbon->timestamp)
            ->first();
    }
}
