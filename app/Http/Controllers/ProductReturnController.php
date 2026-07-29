<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $search = trim($request->string('search')->toString());

        $query = $this->eligibleReturnsQuery()
            ->with([
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
                'warehouseReturnedBy:id,customer_name',
                'workerNotes' => fn ($q) => $q->latest(),
                'workerNotes.user:id,customer_name,role',
            ])
            ->orderByDesc('updated_at');

        if ($status === 'pending') {
            $query->whereNull('warehouse_returned_at');
        } elseif ($status === 'returned') {
            $query->whereNotNull('warehouse_returned_at');
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $returns = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => $this->formatReturn($order));

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
            'stats' => [
                'pending' => (clone $this->eligibleReturnsQuery())->whereNull('warehouse_returned_at')->count(),
                'returned' => (clone $this->eligibleReturnsQuery())->whereNotNull('warehouse_returned_at')->count(),
            ],
            'filters' => [
                'status' => in_array($status, ['pending', 'returned', 'all'], true) ? $status : 'pending',
                'search' => $search,
            ],
        ]);
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->isEligibleReturn($order), 404);

        if ($order->warehouse_returned_at) {
            return back()->with('error', 'تم تسجيل استرجاع هذا الطلب للمستودع مسبقاً.');
        }

        $order->forceFill([
            'warehouse_returned_at' => now(),
            'warehouse_returned_by' => $request->user()?->id,
        ])->save();

        return back()->with('success', 'تم تأكيد استرجاع منتجات الطلب '.$order->order_number.' للمستودع. أصبح التأمين ظاهرًا الآن في صفحة استرداد التأمين.');
    }

    public function storeNote(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->isEligibleReturn($order), 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'يجب كتابة الملاحظة.',
            'body.max' => 'الملاحظة يجب ألا تتجاوز 2000 حرف.',
        ]);

        WorkerOrderNote::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        return back()->with('success', 'تم إضافة الملاحظة.');
    }

    private function eligibleReturnsQuery(): Builder
    {
        return Order::query()
            ->whereHas('workerOrders')
            ->whereDoesntHave('workerOrders', function (Builder $query) {
                $query->where(function (Builder $inner) {
                    $inner->where('status', '!=', 'completed')
                        ->orWhereNull('installation_photo')
                        ->orWhereNull('pickup_photo');
                });
            });
    }

    private function isEligibleReturn(Order $order): bool
    {
        return $order->hasAllWorkerPhotos();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReturn(Order $order): array
    {
        $lines = $order->relationLoaded('workerOrders')
            ? $order->workerOrders
            : $order->workerOrders()->orderBy('line_index')->get();

        $conditions = $lines
            ->pluck('pickup_condition')
            ->filter()
            ->countBy()
            ->all();

        $latestPickup = $lines
            ->pluck('pickup_at')
            ->filter()
            ->sortDesc()
            ->first();

        $notes = $order->relationLoaded('workerNotes')
            ? $order->workerNotes
            : collect();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'products_count' => $lines->count(),
            'products' => $lines->map(fn (WorkerOrder $line) => [
                'id' => $line->id,
                'product_name' => $line->product_name,
                'pickup_condition' => $line->pickup_condition,
                'pickup_at' => $line->pickup_at?->toIso8601String(),
            ])->values()->all(),
            'condition_summary' => $conditions,
            'latest_pickup_at' => $latestPickup?->toIso8601String(),
            'warehouse_returned_at' => $order->warehouse_returned_at?->toIso8601String(),
            'warehouse_returned_by_name' => $order->warehouseReturnedBy?->name,
            'is_returned' => filled($order->warehouse_returned_at),
            'can_confirm' => blank($order->warehouse_returned_at),
            'notes' => $notes->map(fn (WorkerOrderNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'user_name' => $note->user?->name ?: 'مستخدم',
                'user_role' => $note->user?->roleLabel() ?? 'مستخدم',
                'created_at' => $note->created_at?->toIso8601String(),
            ])->values()->all(),
            'notes_count' => $notes->count(),
        ];
    }
}
