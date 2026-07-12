<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WorkerOrder;
use App\Services\DeliveryNotePdfService;
use App\Services\WorkerOrderSyncService;
use App\Support\DeliveryNotePdfData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class WorkerOrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'pending';

        return Inertia::render('WorkerOrders/Index', [
            'workOrders' => Inertia::defer(fn () => $this->paginatedWorkOrders($request, $status)),
            'stats' => Inertia::defer(fn () => $this->workOrderStats()),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function show(string $workOrderKey, WorkerOrderSyncService $syncService)
    {
        $order = $this->resolveWorkOrder($workOrderKey, $syncService);

        $order->load([
            'invoice:id,invoice_number',
            'workerOrders' => fn ($query) => $query->orderBy('line_index'),
            'workerOrders.completedByUser:id,name,customer_name',
        ]);

        return Inertia::render('WorkerOrders/Show', [
            'workOrder' => $this->formatWorkOrderDetail($order),
        ]);
    }

    public function deliveryNote(string $workOrderKey, DeliveryNotePdfService $pdfService, WorkerOrderSyncService $syncService): Response
    {
        $order = $this->resolveWorkOrder($workOrderKey, $syncService);

        $data = DeliveryNotePdfData::fromOrder($order);
        $pdf = $pdfService->render($data);
        $filename = 'delivery-note-'.$data->referenceNumber().'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function complete(Request $request, WorkerOrder $workerOrder)
    {
        if ($workerOrder->status === 'completed') {
            return back()->withErrors([
                'installation_photo' => 'تم رفع صورة التركيب مسبقاً لهذا المنتج.',
            ]);
        }

        $validated = $request->validate([
            'installation_photo' => ['required', 'image', 'max:5120'],
        ], [
            'installation_photo.required' => 'يجب إرفاق صورة للتركيب من أرض الواقع.',
            'installation_photo.image' => 'يجب أن يكون الملف صورة.',
            'installation_photo.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        $path = $validated['installation_photo']->store('worker-installations', 'public');

        if ($workerOrder->installation_photo) {
            Storage::disk('public')->delete($workerOrder->installation_photo);
        }

        $workerOrder->loadMissing('order.invoice');

        $workerOrder->update([
            'installation_photo' => $path,
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        $redirectToShow = $request->boolean('redirect_to_show', true);

        if ($redirectToShow) {
            $reference = $workerOrder->order->invoice?->invoice_number
                ?? $workerOrder->order->order_number;

            return redirect()
                ->route('worker-orders.show', $reference)
                ->with('success', 'تم رفع صورة التركيب وإرسال المنتج للمراجعة.');
        }

        return redirect()
            ->route('worker-orders.index', ['status' => 'completed'])
            ->with('success', 'تم رفع صورة التركيب وإرسال الطلب للمراجعة. يمكن للمسؤول مراجعته في قسم «مرفوعة للمراجعة».');
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedWorkOrders(Request $request, string $status): array
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

        if ($status === 'completed') {
            $query->orderByDesc(
                WorkerOrder::query()
                    ->select('completed_at')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderByDesc('completed_at')
                    ->limit(1)
            );
        } else {
            $query->orderByRaw('activity_date IS NULL')
                ->orderBy('activity_date')
                ->orderByDesc('created_at');
        }

        return $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Order $order) => $this->formatWorkOrderSummary($order))
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

    /**
     * @return array<string, mixed>
     */
    private function formatWorkOrderSummary(Order $order): array
    {
        $firstLine = $order->workerOrders->first();
        $pendingLines = (int) ($order->pending_lines ?? 0);
        $totalLines = (int) ($order->total_lines ?? $order->workerOrders->count());

        return [
            'id' => $order->id,
            'reference_number' => $order->invoice?->invoice_number ?? $order->order_number,
            'order_number' => $order->order_number,
            'invoice_number' => $order->invoice?->invoice_number,
            'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
            'customer_address' => $firstLine?->customer_address ?? $order->address,
            'installation_date' => ($firstLine?->installation_date ?? $order->activity_date)?->format('Y-m-d'),
            'status' => $pendingLines > 0 ? 'pending' : 'completed',
            'products_count' => $totalLines,
            'pending_count' => $pendingLines,
            'completed_count' => (int) ($order->completed_lines ?? 0),
            'location_slug' => $order->location_slug,
            'preview_products' => $order->workerOrders->take(3)->map(fn (WorkerOrder $line) => [
                'name' => $line->product_name,
                'image_url' => $line->product_image_url,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWorkOrderDetail(Order $order): array
    {
        $summary = $this->formatWorkOrderSummary($order);

        return array_merge($summary, [
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'address' => $order->address ?: $summary['customer_address'],
            'lines' => $order->workerOrders->map(fn (WorkerOrder $line) => $this->formatWorkerOrderLine($line))->values()->all(),
            'delivery_note_url' => '/worker-orders/'.rawurlencode($summary['reference_number']).'/delivery-note',
        ]);
    }

    private function resolveWorkOrder(string $workOrderKey, WorkerOrderSyncService $syncService): Order
    {
        $order = Order::query()
            ->whereKey($workOrderKey)
            ->orWhere('order_number', $workOrderKey)
            ->orWhereHas('invoice', fn ($query) => $query->where('invoice_number', $workOrderKey))
            ->first();

        if (! $order && ctype_digit($workOrderKey)) {
            $workerOrder = WorkerOrder::query()->find((int) $workOrderKey);
            $order = $workerOrder?->order;
        }

        abort_unless($order, 404);

        if (! $order->workerOrders()->exists()) {
            $syncService->syncFromOrder($order->fresh());
            $order->unsetRelation('workerOrders');
        }

        abort_unless($order->workerOrders()->exists(), 404);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWorkerOrderLine(WorkerOrder $workerOrder): array
    {
        return [
            'id' => $workerOrder->id,
            'product_name' => $workerOrder->product_name,
            'product_image_url' => $workerOrder->product_image_url,
            'status' => $workerOrder->status,
            'installation_photo_url' => $workerOrder->installation_photo_url,
            'completed_at' => $workerOrder->completed_at?->toIso8601String(),
            'completed_by_user' => $workerOrder->completedByUser ? [
                'id' => $workerOrder->completedByUser->id,
                'name' => $workerOrder->completedByUser->name,
            ] : null,
        ];
    }
}
