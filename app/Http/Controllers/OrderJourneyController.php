<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderJourney;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderJourneyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $stage = $request->string('stage')->toString() ?: 'all';

        $orders = $this->filteredQuery($search, $stage)
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Order $order) => OrderJourney::summary($order));

        return Inertia::render('OrderJourney/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'stage' => in_array($stage, $this->stageKeys(), true) ? $stage : 'all',
            ],
            'stages' => $this->stageOptions(),
        ]);
    }

    public function show(Order $order): Response
    {
        abort_unless($order->isReleasedToOperations(), 404);

        $this->loadJourneyRelations($order);

        return Inertia::render('OrderJourney/Show', [
            'journey' => OrderJourney::detail($order),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    private function filteredQuery(string $search, string $stage)
    {
        $query = Order::query()->releasedToOperations()->with($this->summaryRelations());

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', "%{$search}%"));
            });
        }

        return $this->applyStage($query, $stage);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Order>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    private function applyStage($query, string $stage)
    {
        return match ($stage) {
            'payment' => $query->whereDoesntHave(
                'paymentReceipts',
                fn ($receipts) => $receipts->where('approval_status', 'approved'),
            ),
            'assignment' => $query
                ->whereHas('workerOrders')
                ->whereDoesntHave('workerAssemblers', fn ($assemblers) => $assemblers->installation()),
            'installation' => $query->whereHas('workerOrders', fn ($lines) => $lines->where('status', 'pending')),
            'approval' => $query
                ->whereHas('workerOrders')
                ->whereDoesntHave('workerOrders', fn ($lines) => $lines->where('status', 'pending'))
                ->whereNull('work_order_approved_at'),
            'return' => $query
                ->whereNotNull('work_order_approved_at')
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->whereNull('warehouse_returned_at'),
            'done' => $query
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->whereNotNull('warehouse_returned_at'),
            default => $query,
        };
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function stageOptions(): array
    {
        return [
            ['key' => 'all', 'label' => 'كل المراحل'],
            ['key' => 'payment', 'label' => 'بانتظار المحاسب'],
            ['key' => 'assignment', 'label' => 'بانتظار التعيين'],
            ['key' => 'installation', 'label' => 'قيد التركيب'],
            ['key' => 'approval', 'label' => 'بانتظار التعميد'],
            ['key' => 'return', 'label' => 'بانتظار الاسترجاع'],
            ['key' => 'done', 'label' => 'مكتملة'],
        ];
    }

    /**
     * @return list<string>
     */
    private function stageKeys(): array
    {
        return array_column($this->stageOptions(), 'key');
    }

    /**
     * @return array<int|string, mixed>
     */
    private function summaryRelations(): array
    {
        return [
            'quotation:id,quotation_number,total_amount,created_at,status',
            'invoice:id,invoice_number',
            'paymentReceipts.approvedBy:id,customer_name',
            'paymentReceipts.recordedBy:id,customer_name',
            'workerOrders.completedByUser:id,customer_name',
            'workerAssemblers',
            'workOrderApprovedBy:id,customer_name',
            'warehouseReturnedBy:id,customer_name',
            'warehouseKeeperApprovedBy:id,customer_name',
        ];
    }

    private function loadJourneyRelations(Order $order): void
    {
        $order->load($this->summaryRelations());
    }
}
