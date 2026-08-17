<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $preset, ?string $month): array
    {
        [$currentStart, $currentEnd, $preset] = $this->resolveRange($preset, $month);

        $spanDays = $currentStart->diffInDays($currentEnd) + 1;
        $previousStart = $currentStart->copy()->subDays($spanDays);
        $previousEnd = $currentStart->copy()->subSecond();
        $nextStart = $currentEnd->copy()->addSecond();
        $nextEnd = $nextStart->copy()->addDays($spanDays - 1)->endOfDay();

        if ($preset !== 'week') {
            $previousStart = $currentStart->copy()->subMonthNoOverflow()->startOfMonth();
            $previousEnd = $currentStart->copy()->subMonthNoOverflow()->endOfMonth();
            $nextStart = $currentStart->copy()->addMonthNoOverflow()->startOfMonth();
            $nextEnd = $currentStart->copy()->addMonthNoOverflow()->endOfMonth();
        }

        $current = $this->periodSnapshot($currentStart, $currentEnd);
        $previous = $this->periodSnapshot($previousStart, $previousEnd);
        $next = $this->periodSnapshot($nextStart, $nextEnd);
        $series = $this->dailySeries($currentStart, $currentEnd);

        $currentLabel = $this->periodLabel($preset, $currentStart, $currentEnd);
        $previousLabel = $this->periodLabel($preset, $previousStart, $previousEnd);
        $nextLabel = $this->periodLabel($preset, $nextStart, $nextEnd);
        $nextIsFuture = $nextStart->isFuture();

        $kpis = [
            $this->kpi('إجمالي المبيعات', 'sales_total', $current, $previous, $next, 'money'),
            $this->kpi('عدد الطلبات', 'orders_count', $current, $previous, $next, 'count'),
            $this->kpi('المحصّل', 'paid_total', $current, $previous, $next, 'money'),
            $this->kpi('المتبقي', 'remaining_total', $current, $previous, $next, 'money'),
            $this->kpi('متوسط الطلب', 'avg_order', $current, $previous, $next, 'money'),
            $this->kpi('عملاء مميزون', 'unique_customers', $current, $previous, $next, 'count'),
        ];

        return [
            'filters' => [
                'preset' => $preset,
                'month' => $currentStart->format('Y-m'),
            ],
            'available_months' => $this->availableMonths(),
            'period' => $this->periodMeta($currentLabel, $currentStart, $currentEnd, false),
            'previous_period' => $this->periodMeta($previousLabel, $previousStart, $previousEnd, false),
            'next_period' => $this->periodMeta($nextLabel, $nextStart, $nextEnd, $nextIsFuture),
            'kpis' => $kpis,
            'series' => $series,
            'by_status' => $this->withShare($current['by_status'], 'count'),
            'by_payment_method' => $this->withShare($current['by_payment_method'], 'amount'),
            'top_products' => $this->withAmountShare($current['top_products'], $current['sales_total']),
            'top_customers' => $this->withAmountShare($current['top_customers'], $current['sales_total']),
            'weekday' => $current['weekday'],
            'analytics' => $this->analytics($current, $previous, $next, $series, $nextIsFuture),
            'highlights' => $this->highlights($current, $previous, $next, $series, $previousLabel, $nextLabel, $nextIsFuture),
            'insight' => $this->buildInsight($current, $previous, $next, $previousLabel, $nextLabel, $nextIsFuture),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveRange(string $preset, ?string $month): array
    {
        $preset = in_array($preset, ['week', 'month', 'last_month', 'custom'], true)
            ? $preset
            : 'month';

        $now = now();

        if ($preset === 'week') {
            $start = $now->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
            $end = $start->copy()->addDays(6)->endOfDay();

            return [$start, $end, 'week'];
        }

        if ($preset === 'last_month') {
            $start = $now->copy()->subMonthNoOverflow()->startOfMonth();
            $end = $now->copy()->subMonthNoOverflow()->endOfMonth();

            return [$start, $end, 'last_month'];
        }

        if ($preset === 'custom' && is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = Carbon::createFromFormat('Y-m-d', $month.'-01')?->startOfMonth() ?? $now->copy()->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [$start, $end, 'custom'];
        }

        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'month'];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodSnapshot(Carbon $start, Carbon $end): array
    {
        $orders = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->get([
                'id',
                'customer_name',
                'total_amount',
                'amount_paid',
                'status',
                'payment_method',
                'items',
                'currency',
                'created_at',
            ]);

        $active = $orders->reject(fn (Order $order) => in_array($order->status, ['cancelled', 'refunded'], true));
        $cancelled = $orders->where('status', 'cancelled');

        $salesTotal = round((float) $active->sum(fn (Order $order) => (float) $order->total_amount), 2);
        $paidTotal = round((float) $active->sum(fn (Order $order) => (float) ($order->amount_paid ?? 0)), 2);
        $ordersCount = $active->count();
        $allCount = $orders->count();
        $paidOrders = $active
            ->filter(fn (Order $order) => (float) ($order->amount_paid ?? 0) >= ((float) $order->total_amount) - 0.009)
            ->count();

        return [
            'orders_count' => $ordersCount,
            'cancelled_count' => $cancelled->count(),
            'all_orders_count' => $allCount,
            'paid_orders_count' => $paidOrders,
            'unique_customers' => $active->pluck('customer_name')->filter()->unique()->count(),
            'sales_total' => $salesTotal,
            'paid_total' => $paidTotal,
            'remaining_total' => round(max(0, $salesTotal - $paidTotal), 2),
            'avg_order' => $ordersCount > 0 ? round($salesTotal / $ordersCount, 2) : 0.0,
            'collection_rate' => $salesTotal > 0 ? round(($paidTotal / $salesTotal) * 100, 1) : 0.0,
            'cancellation_rate' => $allCount > 0 ? round(($cancelled->count() / $allCount) * 100, 1) : 0.0,
            'by_status' => $this->groupCounts($orders, 'status', [
                'pending' => 'قيد الانتظار',
                'processing' => 'قيد التنفيذ',
                'paid' => 'مدفوع',
                'cancelled' => 'ملغي',
                'refunded' => 'مسترجع',
            ]),
            'by_payment_method' => $this->groupAmounts($active, 'payment_method', [
                'cash' => 'نقدي',
                'bank_transfer' => 'تحويل بنكي',
                'credit_card' => 'بطاقة ائتمان',
                'noon' => 'Noon',
                'paypal' => 'PayPal',
            ]),
            'top_products' => $this->topProducts($active),
            'top_customers' => $this->topCustomers($active),
            'weekday' => $this->weekdayBreakdown($active),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $next
     * @return array<string, mixed>
     */
    private function kpi(string $label, string $key, array $current, array $previous, array $next, string $format): array
    {
        $value = (float) ($current[$key] ?? 0);
        $previousValue = (float) ($previous[$key] ?? 0);
        $nextValue = (float) ($next[$key] ?? 0);

        return [
            'key' => $key,
            'label' => $label,
            'format' => $format,
            'value' => $value,
            'previous' => $previousValue,
            'next' => $nextValue,
            'change' => $this->percentChange($value, $previousValue),
            'next_change' => $this->percentChange($nextValue, $value),
        ];
    }

    /**
     * @return list<array{date: string, label: string, sales: float, orders: int}>
     */
    private function dailySeries(Carbon $start, Carbon $end): array
    {
        $rows = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get(['total_amount', 'created_at']);

        $grouped = $rows->groupBy(fn (Order $order) => optional($order->created_at)->format('Y-m-d'));
        $cursor = $start->copy()->startOfDay();
        $series = [];

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $dayOrders = $grouped->get($key, collect());
            $series[] = [
                'date' => $key,
                'label' => $cursor->format('d/m'),
                'sales' => round((float) $dayOrders->sum(fn (Order $order) => (float) $order->total_amount), 2),
                'orders' => $dayOrders->count(),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function groupCounts(Collection $orders, string $field, array $labels): array
    {
        return $orders
            ->groupBy(fn (Order $order) => $order->{$field} ?: 'other')
            ->map(function (Collection $group, string $key) use ($labels) {
                return [
                    'key' => $key,
                    'label' => $labels[$key] ?? ($key === 'other' ? 'غير محدد' : $key),
                    'count' => $group->count(),
                    'amount' => round((float) $group->sum(fn (Order $order) => (float) $order->total_amount), 2),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function groupAmounts(Collection $orders, string $field, array $labels): array
    {
        return $this->groupCounts($orders, $field, $labels);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{name: string, quantity: int, amount: float}>
     */
    private function topProducts(Collection $orders): array
    {
        $totals = [];

        foreach ($orders as $order) {
            if (! is_array($order->items)) {
                continue;
            }

            foreach ($order->items as $item) {
                $name = trim((string) ($item['name'] ?? $item['product_name'] ?? ''));
                if ($name === '') {
                    $name = 'منتج';
                }

                $qty = (int) ($item['quantity'] ?? 0);
                $amount = isset($item['amount'])
                    ? (float) $item['amount']
                    : round($qty * ((float) ($item['price'] ?? $item['unit_price'] ?? 0)), 2);

                if (! isset($totals[$name])) {
                    $totals[$name] = ['name' => $name, 'quantity' => 0, 'amount' => 0.0];
                }

                $totals[$name]['quantity'] += $qty;
                $totals[$name]['amount'] = round($totals[$name]['amount'] + $amount, 2);
            }
        }

        return collect($totals)
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{name: string, orders: int, amount: float}>
     */
    private function topCustomers(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (Order $order) => trim((string) ($order->customer_name ?: 'عميل بدون اسم')))
            ->map(function (Collection $group, string $name) {
                return [
                    'name' => $name,
                    'orders' => $group->count(),
                    'amount' => round((float) $group->sum(fn (Order $order) => (float) $order->total_amount), 2),
                ];
            })
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function availableMonths(): array
    {
        $first = Order::query()->min('created_at');
        $cursor = now()->startOfMonth();
        $start = $first ? Carbon::parse($first)->startOfMonth() : $cursor->copy();
        $months = [];

        while ($cursor->gte($start) && count($months) < 36) {
            $months[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $this->arabicMonthLabel($cursor),
            ];
            $cursor->subMonthNoOverflow();
        }

        return $months;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $next
     */
    private function buildInsight(
        array $current,
        array $previous,
        array $next,
        string $previousLabel,
        string $nextLabel,
        bool $nextIsFuture,
    ): string {
        $salesChange = $this->percentChange((float) $current['sales_total'], (float) $previous['sales_total']);
        $ordersChange = $this->percentChange((float) $current['orders_count'], (float) $previous['orders_count']);
        $parts = [];

        if ($salesChange > 0.5) {
            $parts[] = 'المبيعات ارتفعت بنسبة '.number_format(abs($salesChange), 1).'% مقارنة ب'.$previousLabel.'';
        } elseif ($salesChange < -0.5) {
            $parts[] = 'المبيعات انخفضت بنسبة '.number_format(abs($salesChange), 1).'% مقارنة ب'.$previousLabel.'';
        } else {
            $parts[] = 'المبيعات مستقرة تقريباً مقارنة ب'.$previousLabel.'';
        }

        if ($ordersChange > 0.5) {
            $parts[] = 'وعدد الطلبات زاد بنسبة '.number_format(abs($ordersChange), 1).'%';
        } elseif ($ordersChange < -0.5) {
            $parts[] = 'وعدد الطلبات قل بنسبة '.number_format(abs($ordersChange), 1).'%';
        }

        $paidShare = (float) $current['sales_total'] > 0
            ? round(((float) $current['paid_total'] / (float) $current['sales_total']) * 100, 1)
            : 0;
        $parts[] = 'ونسبة التحصيل الحالية '.$paidShare.'%';

        if ($nextIsFuture) {
            $parts[] = 'بيانات '.$nextLabel.' لم تكتمل بعد لأنها فترة قادمة.';
        } else {
            $nextChange = $this->percentChange((float) $next['sales_total'], (float) $current['sales_total']);
            if ($nextChange > 0.5) {
                $parts[] = $nextLabel.' جاء أعلى من الفترة الحالية بنسبة '.number_format(abs($nextChange), 1).'%.';
            } elseif ($nextChange < -0.5) {
                $parts[] = $nextLabel.' جاء أقل من الفترة الحالية بنسبة '.number_format(abs($nextChange), 1).'%.';
            } else {
                $parts[] = $nextLabel.' جاء قريباً من مستوى الفترة الحالية.';
            }
        }

        return implode('، ', $parts).'.';
    }

    /**
     * @return array{label: string, start: string, end: string, is_future: bool}
     */
    private function periodMeta(string $label, Carbon $start, Carbon $end, bool $isFuture): array
    {
        return [
            'label' => $label,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'is_future' => $isFuture,
        ];
    }

    private function periodLabel(string $preset, Carbon $start, Carbon $end): string
    {
        if ($preset === 'week') {
            return 'الأسبوع '.$start->format('d/m').' – '.$end->format('d/m/Y');
        }

        return $this->arabicMonthLabel($start);
    }

    private function arabicMonthLabel(Carbon $date): string
    {
        $months = [
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

        return ($months[$date->month] ?? $date->format('F')).' '.$date->year;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{key: int, label: string, count: int, amount: float, share: float}>
     */
    private function weekdayBreakdown(Collection $orders): array
    {
        $labels = [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];

        $total = max(1, (float) $orders->sum(fn (Order $order) => (float) $order->total_amount));
        $rows = [];

        foreach ($labels as $day => $label) {
            $group = $orders->filter(fn (Order $order) => (int) optional($order->created_at)->dayOfWeek === $day);
            $amount = round((float) $group->sum(fn (Order $order) => (float) $order->total_amount), 2);
            $rows[] = [
                'key' => $day,
                'label' => $label,
                'count' => $group->count(),
                'amount' => $amount,
                'share' => round(($amount / $total) * 100, 1),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withShare(array $rows, string $field): array
    {
        $total = max(1, (float) collect($rows)->sum($field));

        return array_map(function (array $row) use ($total, $field) {
            $row['share'] = round(((float) ($row[$field] ?? 0) / $total) * 100, 1);

            return $row;
        }, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withAmountShare(array $rows, float $salesTotal): array
    {
        $total = max(1, $salesTotal);

        return array_map(function (array $row) use ($total) {
            $row['share'] = round(((float) ($row['amount'] ?? 0) / $total) * 100, 1);

            return $row;
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $next
     * @param  list<array{date: string, label: string, sales: float, orders: int}>  $series
     * @return array<string, mixed>
     */
    private function analytics(array $current, array $previous, array $next, array $series, bool $nextIsFuture): array
    {
        $activeDays = collect($series)->filter(fn (array $point) => $point['sales'] > 0);
        $bestDay = $activeDays->sortByDesc('sales')->first();
        $worstDay = $activeDays->sortBy('sales')->first();
        $topProduct = $current['top_products'][0] ?? null;
        $topCustomer = $current['top_customers'][0] ?? null;
        $topMethod = collect($current['by_payment_method'])->sortByDesc('amount')->first();

        return [
            'collection_rate' => $current['collection_rate'],
            'cancellation_rate' => $current['cancellation_rate'],
            'paid_orders_count' => $current['paid_orders_count'],
            'cancelled_count' => $current['cancelled_count'],
            'unique_customers' => $current['unique_customers'],
            'avg_daily_sales' => count($series) > 0
                ? round($current['sales_total'] / max(1, count($series)), 2)
                : 0,
            'best_day' => $bestDay,
            'worst_day' => $worstDay,
            'top_product' => $topProduct,
            'top_customer' => $topCustomer,
            'top_method' => $topMethod,
            'previous_collection_rate' => $previous['collection_rate'],
            'next_collection_rate' => $next['collection_rate'],
            'next_is_future' => $nextIsFuture,
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $next
     * @param  list<array{date: string, label: string, sales: float, orders: int}>  $series
     * @return list<array{tone: string, title: string, text: string}>
     */
    private function highlights(
        array $current,
        array $previous,
        array $next,
        array $series,
        string $previousLabel,
        string $nextLabel,
        bool $nextIsFuture,
    ): array {
        $items = [];
        $salesChange = $this->percentChange((float) $current['sales_total'], (float) $previous['sales_total']);

        if ($salesChange > 0.5) {
            $items[] = [
                'tone' => 'emerald',
                'title' => 'نمو في المبيعات',
                'text' => 'المبيعات أعلى من '.$previousLabel.' بنسبة '.number_format(abs($salesChange), 1).'%.',
            ];
        } elseif ($salesChange < -0.5) {
            $items[] = [
                'tone' => 'rose',
                'title' => 'تراجع في المبيعات',
                'text' => 'المبيعات أقل من '.$previousLabel.' بنسبة '.number_format(abs($salesChange), 1).'%.',
            ];
        } else {
            $items[] = [
                'tone' => 'slate',
                'title' => 'استقرار نسبي',
                'text' => 'حجم المبيعات قريب من مستوى '.$previousLabel.'.',
            ];
        }

        $items[] = [
            'tone' => ((float) $current['collection_rate'] >= 70 ? 'emerald' : 'amber'),
            'title' => 'نسبة التحصيل',
            'text' => 'تم تحصيل '.number_format((float) $current['collection_rate'], 1).'% من قيمة الطلبات، والمتبقي '.number_format((float) $current['remaining_total'], 2).' ر.س.',
        ];

        $bestDay = collect($series)->sortByDesc('sales')->first();
        if ($bestDay && (float) $bestDay['sales'] > 0) {
            $items[] = [
                'tone' => 'sky',
                'title' => 'أقوى يوم بيع',
                'text' => $bestDay['label'].' حقق '.number_format((float) $bestDay['sales'], 2).' ر.س عبر '.$bestDay['orders'].' طلب.',
            ];
        }

        $topProduct = $current['top_products'][0] ?? null;
        if ($topProduct) {
            $share = (float) $current['sales_total'] > 0
                ? round(((float) $topProduct['amount'] / (float) $current['sales_total']) * 100, 1)
                : 0;
            $items[] = [
                'tone' => 'violet',
                'title' => 'المنتج الأكثر مبيعاً',
                'text' => $topProduct['name'].' يمثل '.$share.'% من المبيعات بكمية '.$topProduct['quantity'].'.',
            ];
        }

        if ($nextIsFuture) {
            $items[] = [
                'tone' => 'slate',
                'title' => $nextLabel,
                'text' => 'الفترة التالية لم تكتمل بعد، والمقارنة ستكون أوضح بعد انتهائها.',
            ];
        } else {
            $nextChange = $this->percentChange((float) $next['sales_total'], (float) $current['sales_total']);
            $items[] = [
                'tone' => $nextChange >= 0 ? 'emerald' : 'rose',
                'title' => 'مقابل '.$nextLabel,
                'text' => $nextChange >= 0
                    ? $nextLabel.' جاء أعلى بنسبة '.number_format(abs($nextChange), 1).'%.'
                    : $nextLabel.' جاء أقل بنسبة '.number_format(abs($nextChange), 1).'%.',
            ];
        }

        if ((float) $current['cancellation_rate'] > 0) {
            $items[] = [
                'tone' => 'amber',
                'title' => 'الإلغاءات',
                'text' => 'نسبة الإلغاء '.number_format((float) $current['cancellation_rate'], 1).'% بواقع '.(int) $current['cancelled_count'].' طلب ملغي.',
            ];
        }

        return $items;
    }

    private function percentChange(float $current, float $previous): float
    {
        if (abs($previous) < 0.009) {
            return $current > 0.009 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
