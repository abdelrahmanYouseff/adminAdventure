<?php

namespace App\Http\Controllers;

use App\Models\AppDownloadClick;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Package;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(User::ROLE_WORKER, User::ROLE_ACCOUNTS, User::ROLE_WAREHOUSE_KEEPER)) {
            return redirect()->route($user->homeRouteName());
        }

        $totalProducts = Product::count();
        $totalInvoices = Invoice::count();
        $totalPackages = Package::count();
        $totalQuotations = Quotation::count();
        $totalOrders = Order::query()
            ->releasedToOperations()
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->count();

        $invoiceSeries = $this->dailyCounts(Invoice::query(), 7);
        $quotationSeries = $this->dailyCounts(Quotation::query(), 7);
        $packageSeries = $this->dailyCounts(Package::query(), 7);
        $productSeries = $this->dailyCounts(Product::query(), 7);

        $alerts = $this->buildAlerts();

        return Inertia::render('Dashboard', [
            'userName' => $user?->name ?: 'مستخدم',
            'updatedAt' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'totalProducts' => $totalProducts,
            'totalInvoices' => $totalInvoices,
            'totalPackages' => $totalPackages,
            'totalQuotations' => $totalQuotations,
            'totalOrders' => $totalOrders,
            'statChanges' => [
                'quotations' => $this->dayOverDayChange(Quotation::query()),
                'packages' => $this->dayOverDayChange(Package::query()),
                'invoices' => $this->dayOverDayChange(Invoice::query()),
                'products' => $this->dayOverDayChange(Product::query()),
            ],
            'sparklines' => [
                'quotations' => $quotationSeries,
                'packages' => $packageSeries,
                'invoices' => $invoiceSeries,
                'products' => $productSeries,
            ],
            'performanceSeries' => [
                'labels' => collect($invoiceSeries)->pluck('label')->values()->all(),
                'values' => collect($invoiceSeries)->pluck('count')->values()->all(),
            ],
            'alerts' => $alerts,
            'appDownloadStats' => [
                'ios' => AppDownloadClick::query()
                    ->where('platform', AppDownloadClick::PLATFORM_IOS)
                    ->count(),
                'android' => AppDownloadClick::query()
                    ->where('platform', AppDownloadClick::PLATFORM_ANDROID)
                    ->count(),
                'ios_today' => AppDownloadClick::query()
                    ->where('platform', AppDownloadClick::PLATFORM_IOS)
                    ->whereDate('clicked_at', today())
                    ->count(),
                'android_today' => AppDownloadClick::query()
                    ->where('platform', AppDownloadClick::PLATFORM_ANDROID)
                    ->whereDate('clicked_at', today())
                    ->count(),
                'ios_change' => $this->downloadDayChange(AppDownloadClick::PLATFORM_IOS),
                'android_change' => $this->downloadDayChange(AppDownloadClick::PLATFORM_ANDROID),
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<array{label: string, count: int}>
     */
    private function dailyCounts($query, int $days): array
    {
        $dateColumn = $query->getModel()->getCreatedAtColumn() ?: 'created_at';
        $series = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $series[] = [
                'label' => $day->translatedFormat('D'),
                'count' => (clone $query)->whereDate($dateColumn, $day)->count(),
            ];
        }

        return $series;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function dayOverDayChange($query): int
    {
        $dateColumn = $query->getModel()->getCreatedAtColumn() ?: 'created_at';
        $today = (clone $query)->whereDate($dateColumn, today())->count();
        $yesterday = (clone $query)->whereDate($dateColumn, today()->subDay())->count();

        if ($yesterday === 0) {
            return $today > 0 ? 100 : 0;
        }

        return (int) round((($today - $yesterday) / $yesterday) * 100);
    }

    private function downloadDayChange(string $platform): int
    {
        $today = AppDownloadClick::query()
            ->where('platform', $platform)
            ->whereDate('clicked_at', today())
            ->count();
        $yesterday = AppDownloadClick::query()
            ->where('platform', $platform)
            ->whereDate('clicked_at', today()->subDay())
            ->count();

        if ($yesterday === 0) {
            return $today > 0 ? 100 : 0;
        }

        return (int) round((($today - $yesterday) / $yesterday) * 100);
    }

    /**
     * @return list<array{title: string, time: string, tone: string}>
     */
    private function buildAlerts(): array
    {
        $alerts = [];

        $latestOrder = Order::query()
            ->releasedToOperations()
            ->latest('id')
            ->first();

        if ($latestOrder) {
            $alerts[] = [
                'title' => 'طلب جديد #'.$latestOrder->order_number,
                'time' => $this->relativeTime($latestOrder->created_at),
                'tone' => 'blue',
            ];
        }

        $overdueInvoices = Invoice::query()
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();

        if ($overdueInvoices > 0) {
            $alerts[] = [
                'title' => $overdueInvoices.' فاتورة تحتاج متابعة',
                'time' => 'منذ ساعة',
                'tone' => 'amber',
            ];
        }

        $alerts[] = [
            'title' => 'تحديث النظام بنجاح',
            'time' => 'منذ 3 ساعات',
            'tone' => 'emerald',
        ];

        return array_slice($alerts, 0, 4);
    }

    private function relativeTime(?Carbon $time): string
    {
        if (! $time) {
            return 'الآن';
        }

        $minutes = $time->diffInMinutes(now());
        if ($minutes < 1) {
            return 'الآن';
        }
        if ($minutes < 60) {
            return 'منذ '.$minutes.' د';
        }

        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return 'منذ '.$hours.' ساعة';
        }

        return 'منذ '.(int) floor($hours / 24).' يوم';
    }
}
