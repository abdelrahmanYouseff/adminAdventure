<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderNote;
use Carbon\CarbonInterface;
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
            ->orderByRaw('dismantling_at IS NULL')
            ->orderBy('dismantling_at')
            ->orderByDesc('id');

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
            ->whereNotIn('status', ['cancelled', 'refunded']);
    }

    private function isEligibleReturn(Order $order): bool
    {
        return ! in_array($order->status, ['cancelled', 'refunded'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReturn(Order $order): array
    {
        $lines = $order->relationLoaded('workerOrders')
            ? $order->workerOrders
            : $order->workerOrders()->orderBy('line_index')->get();

        $products = $lines->isNotEmpty()
            ? $lines->map(fn (WorkerOrder $line) => [
                'id' => $line->id,
                'product_name' => $line->product_name,
            ])->values()->all()
            : collect($order->items ?? [])->values()->map(function ($item, int $index) {
                $row = is_array($item) ? $item : [];

                return [
                    'id' => $index + 1,
                    'product_name' => (string) ($row['name'] ?? $row['product_name'] ?? 'صنف'),
                ];
            })->all();

        $notes = $order->relationLoaded('workerNotes')
            ? $order->workerNotes
            : collect();

        $dismantlingMeta = $this->dismantlingMeta($order->dismantling_at);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'products_count' => count($products),
            'products' => $products,
            'dismantling_at' => $order->dismantling_at?->toIso8601String(),
            'days_until_dismantling' => $dismantlingMeta['days'],
            'dismantling_label' => $dismantlingMeta['label'],
            'dismantling_tone' => $dismantlingMeta['tone'],
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

    /**
     * @return array{days: int|null, label: string, tone: string}
     */
    private function dismantlingMeta(?CarbonInterface $dismantlingAt): array
    {
        if (! $dismantlingAt) {
            return [
                'days' => null,
                'label' => 'بدون تاريخ فك',
                'tone' => 'muted',
            ];
        }

        $days = (int) now()->startOfDay()->diffInDays($dismantlingAt->copy()->startOfDay(), false);

        if ($days > 0) {
            return [
                'days' => $days,
                'label' => 'باقي '.$days.' '.($days === 1 ? 'يوم' : 'أيام'),
                'tone' => $days <= 3 ? 'warn' : 'ok',
            ];
        }

        if ($days === 0) {
            return [
                'days' => 0,
                'label' => 'اليوم موعد الفك',
                'tone' => 'due',
            ];
        }

        $overdue = abs($days);

        return [
            'days' => $days,
            'label' => 'متأخر '.$overdue.' '.($overdue === 1 ? 'يوم' : 'أيام'),
            'tone' => 'overdue',
        ];
    }
}
