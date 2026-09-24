<?php
namespace App\Services;
use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
class CommissionReportService
{
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
                'commission_total' => round((float) $rows->sum('commission'), 2),
            ],
            'rows' => $rows->values()->all(),
        ];
    }
    public function rowsForMonth(Carbon $start, Carbon $end): Collection
    {
        $invoices = Invoice::query()
            ->finalPaid()
            ->where('excluded_from_commissions', false)
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'user:id,customer_name',
                'order:id,invoice_id,order_number,customer_name,total_amount,currency,status,items',
                'order.products',
                'order.workerOrders:id,order_id,product_name',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        return $invoices->map(function (Invoice $invoice) {
            $order = $invoice->order;
            $productNames = $order ? $this->productNames($order) : [];
            $totalAmount = round((float) $invoice->amount, 2);
            return [
                'id' => $invoice->id,
                'order_date' => $invoice->created_at?->format('Y-m-d'),
                'order_number' => $order?->order_number,
                'customer_name' => $this->displayCustomerName($order?->customer_name ?: $invoice->user?->customer_name),
                'invoice_number' => $invoice->invoice_number,
                'invoice_id' => $invoice->id,
                'product_names' => $productNames,
                'products_label' => $productNames !== [] ? implode('، ', $productNames) : '—',
                'games_count' => $order ? $this->gamesCount($order) : 0,
                'total_amount' => $totalAmount,
                'commission' => self::commissionForAmount($totalAmount),
                'currency' => $order?->currency ?: 'SAR',
            ];
        });
    }
    public static function commissionForAmount(float $amount): float
    {
        $amount = round($amount, 2);
        foreach (self::commissionTiers() as [$from, $to, $commission]) {
            if ($amount >= $from && $amount <= $to) {
                return (float) $commission;
            }
        }
        if ($amount > 100000) {
            return 150.0;
        }
        return self::minimumCommission();
    }
    public static function minimumCommission(): float
    {
        return (float) (self::commissionTiers()[0][2] ?? 15);
    }
    private static function commissionTiers(): array
    {
        return [
            [499, 999, 15],
            [1000, 1499, 20],
            [1500, 1999, 25],
            [2000, 2499, 30],
            [2500, 10000, 35],
            [10001, 15000, 50],
            [15001, 25000, 75],
            [25001, 50000, 100],
            [50001, 75000, 125],
            [75001, 100000, 150],
        ];
    }
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
    private function productNames(Order $order): array
    {
        if ($order->relationLoaded('products') && $order->products->isNotEmpty()) {
            return $order->products
                ->map(function ($product) {
                    $name = trim((string) ($product->product_name ?? ''));
                    if ($name === '') {
                        return null;
                    }
                    $qty = max(1, (int) ($product->pivot->quantity ?? 1));
                    return $qty > 1 ? $name.' ×'.$qty : $name;
                })
                ->filter()
                ->values()
                ->all();
        }
        if (is_array($order->items) && $order->items !== []) {
            return collect($order->items)
                ->map(function ($item) {
                    if (! is_array($item)) {
                        return null;
                    }
                    $name = trim((string) ($item['name'] ?? $item['product_name'] ?? ''));
                    if ($name === '') {
                        return null;
                    }
                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    return $qty > 1 ? $name.' ×'.$qty : $name;
                })
                ->filter()
                ->values()
                ->all();
        }
        if ($order->relationLoaded('workerOrders') && $order->workerOrders->isNotEmpty()) {
            return $order->workerOrders
                ->pluck('product_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        return [];
    }
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
    private function displayCustomerName(?string $n): ?string
    {
        if ($n === null) { return null; }
        $x = preg_replace('/\s*\x{0627}\x{0644}\x{0631}\x{0642}\x{0645}\s*\x{0627}\x{0644}\x{0636}\x{0631}\x{064A}\x{0628}\x{064A}\s*[:\x{FF1A}]?\s*\S+/u', ' ', $n);
        $x = trim(preg_replace('/\s+/u', ' ', preg_replace('/\b\d{10,15}\b/u', ' ', (string) $x) ?? "") ?? "");
        return $x !== "" ? $x : trim($n);
    }
}











































