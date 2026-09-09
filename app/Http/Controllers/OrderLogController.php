<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $filter = $request->string('filter')->toString() ?: 'all';

        if (! in_array($filter, ['all', 'edited'], true)) {
            $filter = 'all';
        }

        $query = Order::query()
            ->whereHas('orderLogs')
            ->with(['orderLogs' => fn ($builder) => $builder->orderBy('id')])
            ->latest('id');

        if ($filter === 'edited') {
            $query->whereHas('orderLogs', fn ($builder) => $builder->where('action', OrderLog::ACTION_UPDATED));
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('orderLogs', function ($logs) use ($search) {
                        $logs->where('user_name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => $this->presentOrder($order));

        return Inertia::render('OrderLogs/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'filter' => $filter,
            ],
            'stats' => [
                'all' => Order::query()->whereHas('orderLogs')->count(),
                'edited' => Order::query()
                    ->whereHas('orderLogs', fn ($builder) => $builder->where('action', OrderLog::ACTION_UPDATED))
                    ->count(),
                'events' => OrderLog::query()->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(Order $order): array
    {
        $created = $order->orderLogs->firstWhere('action', OrderLog::ACTION_CREATED)
            ?? $order->orderLogs->first();
        $updates = $order->orderLogs
            ->where('action', OrderLog::ACTION_UPDATED)
            ->values();
        $lastUpdate = $updates->last();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'created_by' => $created ? $this->presentActor($created) : $this->unknownActor(),
            'created_at' => $created?->created_at?->toIso8601String() ?? $order->created_at?->toIso8601String(),
            'updated_by' => $lastUpdate ? $this->presentActor($lastUpdate) : null,
            'updated_at' => $lastUpdate?->created_at?->toIso8601String(),
            'updates' => $updates->map(fn (OrderLog $log) => [
                'id' => $log->id,
                'user' => $this->presentActor($log),
                'created_at' => $log->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array{id: int|null, name: string, role: string|null, role_label: string|null}
     */
    private function presentActor(OrderLog $log): array
    {
        $name = $log->user_name
            ?: $log->user?->customer_name
            ?: $log->user?->email;

        if (! $name) {
            $name = $log->action === OrderLog::ACTION_CREATED ? 'النظام' : 'غير معروف';
        }

        $role = $log->user_role ?: $log->user?->role;

        return [
            'id' => $log->user_id,
            'name' => $name,
            'role' => $role,
            'role_label' => $this->roleLabel($role),
        ];
    }

    /**
     * @return array{id: null, name: string, role: null, role_label: null}
     */
    private function unknownActor(): array
    {
        return [
            'id' => null,
            'name' => 'غير مسجّل',
            'role' => null,
            'role_label' => null,
        ];
    }

    private function roleLabel(?string $role): ?string
    {
        if (! $role) {
            return null;
        }

        return match ($role) {
            'admin' => 'ادمن',
            'general_manager' => 'مدير عام',
            'manager' => 'مسئول',
            'accounts' => 'حسابات',
            'workers_manager' => 'مدير العمال',
            'worker' => 'عامل',
            'warehouse_keeper' => 'أمين مستودع',
            default => 'عميل',
        };
    }
}
