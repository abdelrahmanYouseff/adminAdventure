<?php

namespace App\Http\Controllers\MainApp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\Quotation;
use App\Support\MainAppModules;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $modules = MainAppModules::forUser($user);

        $quickStats = $this->quickStatsFor($user);

        return Inertia::render('Home', [
            'user' => [
                'id' => $user->id,
                'name' => $user->customer_name ?: $user->name ?: $user->email,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => $user->roleLabel(),
            ],
            'modules' => $modules,
            'quickStats' => $quickStats,
        ]);
    }

    /**
     * @return list<array{label: string, value: string|int, hint?: string}>
     */
    private function quickStatsFor($user): array
    {
        $stats = [];

        if ($user->hasAnyRole('admin', 'general_manager', 'manager')) {
            $stats[] = [
                'label' => 'طلبات اليوم',
                'value' => Order::query()->whereDate('created_at', today())->count(),
                'hint' => 'طلبات جديدة',
            ];
            $stats[] = [
                'label' => 'عروض الأسعار',
                'value' => Quotation::query()->count(),
            ];
        }

        if ($user->hasAnyRole('admin', 'general_manager', 'manager', 'accounts')) {
            $stats[] = [
                'label' => 'سندات معلّقة',
                'value' => OrderPaymentReceipt::query()
                    ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
                    ->count(),
                'hint' => 'بانتظار الاعتماد',
            ];
            $stats[] = [
                'label' => 'الفواتير',
                'value' => Invoice::query()->count(),
            ];
        }

        if ($user->hasAnyRole('admin', 'general_manager', 'manager', 'workers_manager')) {
            $stats[] = [
                'label' => 'أوامر معلّقة',
                'value' => Order::query()
                    ->whereHas('workerOrders')
                    ->whereNull('work_order_approved_at')
                    ->count(),
            ];
        }

        if ($user->isWarehouseKeeper() || $user->hasAnyRole('admin', 'general_manager', 'manager')) {
            $stats[] = [
                'label' => 'استرجاع معلّق',
                'value' => Order::query()
                    ->whereNotNull('work_order_approved_at')
                    ->whereNull('warehouse_returned_at')
                    ->count(),
            ];
        }

        return array_slice($stats, 0, 4);
    }
}
