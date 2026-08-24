<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CommissionReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?string $month): array
    {
        [$start, $end, $monthKey] = $this->resolveMonth($month);
        $rows = $this->rowsForMonth($start, $end);

        return [
            'filters' => [
                'month' => $monthKey,
            ],
            'available_months' => $this->availableMonths(),
            'period' => [
                'label' => $this->monthLabel($start),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'summary' => [
                'orders_count' => $rows->count(),
                'games_count' => (int) $rows->sum('games_count'),
                'total_amount' => round((float) $rows->sum('total_amount'), 2),
            ],
            'rows' => $rows->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsForMonth(Carbon $start, Carbon $end): Collection
    {
        $orders = Order::query()
            ->releasedToOperations()
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'products',
                'workerOrders:id,order_id',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
                'order_number',
                'customer_name',
                'total_amount',
                'currency',
                'items',
                'created_at',
            ]);

        return $orders->map(fn (Order $order) => [
            'id' => $order->id,
            'order_date' => $order->created_at?->format('Y-m-d'),
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'games_count' => $this->gamesCount($order),
            'total_amount' => round((float) $order->total_amount, 2),
            'currency' => $order->currency ?: 'SAR',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function resolveMonth(?string $month): array
    {
        $now = now();

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = Carbon::createFromFormat('Y-m-d', $month.'-01')?->startOfMonth()
                ?? $now->copy()->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [$start, $end, $start->format('Y-m')];
        }

        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        return [$start, $end, $start->format('Y-m')];
    }

    private function gamesCount(Order $order): int
    {
        if ($order->relationLoaded('products') && $order->products->isNotEmpty()) {
            return (int) $order->products->sum(
                fn ($product) => max(1, (int) ($product->pivot->quantity ?? 1)),
            );
        }

        if (is_array($order->items) && $order->items !== []) {
            return (int) collect($order->items)->sum(
                fn ($item) => max(1, (int) (is_array($item) ? ($item['quantity'] ?? 1) : 1)),
            );
        }

        if ($order->relationLoaded('workerOrders') && $order->workerOrders->isNotEmpty()) {
            return $order->workerOrders->count();
        }

        return 0;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function availableMonths(): array
    {
        $months = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $months[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $this->monthLabel($cursor),
            ];
            $cursor = $cursor->copy()->subMonthNoOverflow();
        }

        return $months;
    }

    private function monthLabel(Carbon $date): string
    {
        $labels = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ];

        return ($labels[(int) $date->format('n')] ?? $date->format('F')).' '.$date->format('Y');
    }
}
