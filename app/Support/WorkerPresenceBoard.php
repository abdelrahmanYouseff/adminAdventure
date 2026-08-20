<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrderAssembler;
use Carbon\Carbon;

class WorkerPresenceBoard
{
    public const ONLINE_MINUTES = 10;

    /**
     * @return array{
     *     workers: list<array<string, mixed>>,
     *     counts: array{online: int, installation: int, dismantling: int, offline: int, total: int}
     * }
     */
    public static function forReturns(): array
    {
        $workers = User::query()
            ->where('role', User::ROLE_WORKER)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name', 'phone', 'last_seen_at']);

        if ($workers->isEmpty()) {
            return [
                'workers' => [],
                'counts' => [
                    'online' => 0,
                    'installation' => 0,
                    'dismantling' => 0,
                    'offline' => 0,
                    'total' => 0,
                ],
            ];
        }

        $workerIds = $workers->pluck('id')->all();
        $workerNames = $workers->mapWithKeys(fn (User $w) => [(int) $w->id => $w->name])->all();
        $today = now()->startOfDay();

        $assignments = WorkerOrderAssembler::query()
            ->with([
                'order:id,order_number,customer_name,activity_date,activity_time,installation_at,dismantling_at,warehouse_returned_at,work_order_approved_at,status',
                'order.workerOrders:id,order_id,status,pickup_photo,installation_date',
            ])
            ->where(function ($query) use ($workerIds, $workerNames) {
                $query->whereIn('user_id', $workerIds);

                $names = array_values(array_filter($workerNames));
                if ($names !== []) {
                    $query->orWhereIn('worker_name', $names);
                }
            })
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['cancelled', 'refunded']))
            ->latest('id')
            ->get();

        $byWorker = [];

        foreach ($assignments as $assignment) {
            /** @var WorkerOrderAssembler $assignment */
            $order = $assignment->order;
            if (! $order) {
                continue;
            }

            $matchedIds = [];
            if ($assignment->user_id && isset($workerNames[(int) $assignment->user_id])) {
                $matchedIds[] = (int) $assignment->user_id;
            }

            foreach ($workerNames as $id => $name) {
                if ($name !== '' && $assignment->worker_name === $name) {
                    $matchedIds[] = (int) $id;
                }
            }

            $matchedIds = array_values(array_unique($matchedIds));
            if ($matchedIds === []) {
                continue;
            }

            $isDismantling = $assignment->isDismantling();
            $payload = $isDismantling
                ? self::dismantlingAppointment($order, $today)
                : self::installationAppointment($order, $today);

            if ($payload === null) {
                continue;
            }

            foreach ($matchedIds as $workerId) {
                $byWorker[$workerId] ??= [
                    'installation' => null,
                    'dismantling' => null,
                ];

                $key = $isDismantling ? 'dismantling' : 'installation';
                if ($byWorker[$workerId][$key] === null) {
                    $byWorker[$workerId][$key] = $payload;
                }
            }
        }

        $rows = [];
        $counts = [
            'online' => 0,
            'installation' => 0,
            'dismantling' => 0,
            'offline' => 0,
            'total' => $workers->count(),
        ];

        foreach ($workers as $worker) {
            $isOnline = filled($worker->last_seen_at)
                && $worker->last_seen_at->gte(now()->subMinutes(User::ONLINE_MINUTES));

            $installation = $byWorker[$worker->id]['installation'] ?? null;
            $dismantling = $byWorker[$worker->id]['dismantling'] ?? null;
            $hasInstallation = $installation !== null;
            $hasDismantling = $dismantling !== null;

            if ($isOnline) {
                $counts['online']++;
            } else {
                $counts['offline']++;
            }
            if ($hasInstallation) {
                $counts['installation']++;
            }
            if ($hasDismantling) {
                $counts['dismantling']++;
            }

            $statusKey = match (true) {
                $hasDismantling && ! $hasInstallation => 'dismantling',
                $hasInstallation && ! $hasDismantling => 'installation',
                $hasInstallation && $hasDismantling => 'both',
                $isOnline => 'active',
                default => 'offline',
            };

            $rows[] = [
                'id' => $worker->id,
                'name' => $worker->name,
                'phone' => $worker->phone,
                'is_online' => $isOnline,
                'connection_label' => $isOnline ? 'متصل' : 'غير متصل',
                'is_active' => $isOnline,
                'has_installation' => $hasInstallation,
                'has_dismantling' => $hasDismantling,
                'installation' => $installation,
                'dismantling' => $dismantling,
                'status_key' => $statusKey,
                'status_label' => match ($statusKey) {
                    'dismantling' => 'ميعاد فك',
                    'installation' => 'ميعاد تركيب',
                    'both' => 'تركيب + فك',
                    'active' => 'نشط',
                    default => 'غير متصل',
                },
                'last_seen_at' => $worker->last_seen_at?->toIso8601String(),
            ];
        }

        usort($rows, function (array $a, array $b) {
            $rank = [
                'both' => 0,
                'dismantling' => 1,
                'installation' => 2,
                'active' => 3,
                'offline' => 4,
            ];

            $cmp = ($rank[$a['status_key']] ?? 9) <=> ($rank[$b['status_key']] ?? 9);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'workers' => $rows,
            'counts' => $counts,
        ];
    }

    /**
     * @return array{order_id: int, order_number: string, customer_name: string, at: string|null, label: string}|null
     */
    private static function installationAppointment(Order $order, Carbon $today): ?array
    {
        $lines = $order->relationLoaded('workerOrders')
            ? $order->workerOrders
            : $order->workerOrders()->get();

        $pendingInstall = $lines->contains(fn ($line) => $line->status !== 'completed');
        $lineInstallDate = $lines->pluck('installation_date')->filter()->first();
        $rawInstallDate = $order->scheduledInstallationDate() ?? $lineInstallDate;
        $installDate = $rawInstallDate ? Carbon::parse($rawInstallDate)->startOfDay() : null;

        if (! $pendingInstall && filled($order->work_order_approved_at)) {
            return null;
        }

        if ($installDate && $installDate->lt($today) && ! $pendingInstall) {
            return null;
        }

        if (! $pendingInstall && ! $installDate) {
            return null;
        }

        $time = $order->scheduledInstallationTime();
        $at = self::formatAppointmentAt($installDate, $time);

        return [
            'order_id' => $order->id,
            'order_number' => (string) $order->order_number,
            'customer_name' => (string) ($order->customer_name ?: '—'),
            'at' => $at,
            'label' => 'ميعاد تركيب',
        ];
    }

    /**
     * @return array{order_id: int, order_number: string, customer_name: string, at: string|null, label: string}|null
     */
    private static function dismantlingAppointment(Order $order, Carbon $today): ?array
    {
        if (filled($order->warehouse_returned_at)) {
            return null;
        }

        $lines = $order->relationLoaded('workerOrders')
            ? $order->workerOrders
            : $order->workerOrders()->get();

        $pendingPickup = $lines->contains(fn ($line) => blank($line->pickup_photo));
        $dismantlingAt = $order->dismantling_at ? Carbon::parse($order->dismantling_at) : null;

        if (! $pendingPickup && $dismantlingAt && $dismantlingAt->copy()->startOfDay()->lt($today)) {
            return null;
        }

        if (! $pendingPickup && ! $dismantlingAt) {
            return null;
        }

        $at = $dismantlingAt?->format('Y-m-d H:i');

        return [
            'order_id' => $order->id,
            'order_number' => (string) $order->order_number,
            'customer_name' => (string) ($order->customer_name ?: '—'),
            'at' => $at,
            'label' => 'ميعاد فك',
        ];
    }

    private static function formatAppointmentAt(?Carbon $date, ?string $time): ?string
    {
        if (! $date) {
            return null;
        }

        $value = $date->format('Y-m-d');
        if (filled($time)) {
            $value .= ' '.$time;
        }

        return $value;
    }
}
