<?php

namespace App\Http\Controllers\MainApp;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Services\WorkerOrderSyncService;
use App\Support\WorkOrderPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = $request->string('status')->toString() ?: 'pending';
        $search = trim($request->string('search')->toString());

        if (! in_array($status, ['pending', 'completed', 'all'], true)) {
            $status = 'pending';
        }

        $workOrders = $this->paginatedWorkOrders($status, $search);

        return Inertia::render('WorkOrders/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->customer_name ?: $user->name ?: $user->email,
                'role_label' => $user->roleLabel(),
            ],
            'workOrders' => $workOrders,
            'stats' => $this->workOrderStats(),
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function show(string $workOrderKey, WorkerOrderSyncService $syncService): Response
    {
        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);
        WorkOrderPresenter::loadDetailRelations($order);

        return Inertia::render('WorkOrders/Show', [
            'workOrder' => WorkOrderPresenter::detail($order),
            'availableWorkers' => WorkOrderPresenter::availableWorkers(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedWorkOrders(string $status, string $search = ''): array
    {
        $query = Order::query()
            ->whereHas('workerOrders')
            ->with([
                'invoice:id,invoice_number',
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
            ])
            ->withCount([
                'workerOrders as total_lines',
                'workerOrders as pending_lines' => fn ($q) => $q->where('status', 'pending'),
                'workerOrders as completed_lines' => fn ($q) => $q->where('status', 'completed'),
            ]);

        if ($status === 'pending') {
            $query->whereHas('workerOrders', fn ($q) => $q->where('status', 'pending'));
        } elseif ($status === 'completed') {
            $query->whereDoesntHave('workerOrders', fn ($q) => $q->where('status', 'pending'))
                ->whereHas('workerOrders', fn ($q) => $q->where('status', 'completed'));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('workerOrders', function ($workerOrder) use ($search) {
                        $workerOrder->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($status === 'completed') {
            $query->orderByDesc(
                WorkerOrder::query()
                    ->select('completed_at')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderByDesc('completed_at')
                    ->limit(1)
            );
        } else {
            $query->orderByDesc(
                WorkerOrder::query()
                    ->select('created_at')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderByDesc('created_at')
                    ->limit(1)
            )->orderByDesc('orders.created_at');
        }

        return $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order) => WorkOrderPresenter::summary($order))
            ->toArray();
    }

    /**
     * @return array{pending: int, completed: int, total: int}
     */
    private function workOrderStats(): array
    {
        return [
            'pending' => Order::whereHas('workerOrders', fn ($q) => $q->where('status', 'pending'))->count(),
            'completed' => Order::whereHas('workerOrders')
                ->whereDoesntHave('workerOrders', fn ($q) => $q->where('status', 'pending'))
                ->count(),
            'total' => Order::whereHas('workerOrders')->count(),
        ];
    }
}
