<?php

namespace App\Http\Controllers;

use App\Models\WorkerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class WorkerOrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'pending';

        return Inertia::render('WorkerOrders/Index', [
            'workerOrders' => Inertia::defer(fn () => $this->paginatedWorkerOrders($request, $status)),
            'stats' => Inertia::defer(fn () => $this->workerOrderStats()),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function complete(Request $request, WorkerOrder $workerOrder)
    {
        if ($workerOrder->status === 'completed') {
            return back()->withErrors([
                'installation_photo' => 'تم تسجيل التركيب مسبقاً لهذا الطلب.',
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

        $workerOrder->update([
            'installation_photo' => $path,
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('worker-orders.index', ['status' => 'completed'])
            ->with('success', 'تم تسجيل التركيب بنجاح. يمكن للمسؤول مراجعة صورة التركيب في قسم «تم التركيب».');
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedWorkerOrders(Request $request, string $status): array
    {
        $query = WorkerOrder::query()
            ->with([
                'order:id,order_number,location_slug,address',
                'completedByUser:id,customer_name',
            ]);

        if ($status === 'completed') {
            $query->orderByDesc('completed_at');
        } else {
            $query->orderByRaw('installation_date IS NULL')
                ->orderBy('installation_date')
                ->orderByDesc('created_at');
        }

        if (in_array($status, ['pending', 'completed'], true)) {
            $query->where('status', $status);
        }

        return $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn (WorkerOrder $workerOrder) => $this->formatWorkerOrder($workerOrder))
            ->toArray();
    }

    /**
     * @return array{pending: int, completed: int, total: int}
     */
    private function workerOrderStats(): array
    {
        return [
            'pending' => WorkerOrder::where('status', 'pending')->count(),
            'completed' => WorkerOrder::where('status', 'completed')->count(),
            'total' => WorkerOrder::count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWorkerOrder(WorkerOrder $workerOrder): array
    {
        return [
            'id' => $workerOrder->id,
            'product_name' => $workerOrder->product_name,
            'product_image_url' => $workerOrder->product_image_url,
            'customer_name' => $workerOrder->customer_name,
            'customer_address' => $workerOrder->customer_address,
            'installation_date' => $workerOrder->installation_date?->format('Y-m-d'),
            'status' => $workerOrder->status,
            'installation_photo_url' => $workerOrder->installation_photo_url,
            'completed_at' => $workerOrder->completed_at?->toIso8601String(),
            'completed_by_user' => $workerOrder->completedByUser ? [
                'id' => $workerOrder->completedByUser->id,
                'name' => $workerOrder->completedByUser->name,
            ] : null,
            'order' => $workerOrder->order ? [
                'id' => $workerOrder->order->id,
                'order_number' => $workerOrder->order->order_number,
                'location_slug' => $workerOrder->order->location_slug,
                'address' => $workerOrder->order->address,
            ] : null,
        ];
    }
}
