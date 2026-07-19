<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Support\OrderWhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WorkerInstallationController extends Controller
{
    public function show(Order $order): Response
    {
        $user = request()->user();
        abort_unless($order->isAssignedToWorker($user), 403, 'هذا التركيب غير معيّن لك.');

        $order->load([
            'workerOrders' => fn ($q) => $q->orderBy('line_index'),
        ]);

        $lines = $order->workerOrders;
        $pendingInstallCount = $lines->where('status', 'pending')->count();
        $completedInstallCount = $lines->where('status', 'completed')->count();
        $pendingPickupCount = $lines
            ->where('status', 'completed')
            ->filter(fn (WorkerOrder $line) => blank($line->pickup_photo))
            ->count();
        $firstLine = $lines->first();
        $address = $firstLine?->customer_address ?? $order->address;

        return Inertia::render('InstallationShow', [
            'installation' => [
                'id' => $order->id,
                'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'map_url' => $this->resolveMapUrl($address),
                'installation_date' => ($firstLine?->installation_date ?? $order->activity_date)?->format('Y-m-d'),
                'products_count' => $lines->count(),
                'pending_count' => $pendingInstallCount,
                'completed_count' => $completedInstallCount,
                'pending_pickup_count' => $pendingPickupCount,
                'status' => $pendingInstallCount > 0 ? 'pending' : 'completed',
                'products' => $lines->map(fn (WorkerOrder $line) => [
                    'id' => $line->id,
                    'product_name' => $line->product_name,
                    'product_image_url' => $line->product_image_url,
                    'status' => $line->status,
                    'installation_photo_url' => $line->installation_photo_url,
                    'completed_at' => $line->completed_at?->toIso8601String(),
                    'pickup_photo_url' => $line->pickup_photo_url,
                    'pickup_at' => $line->pickup_at?->toIso8601String(),
                    'pickup_condition' => $line->pickup_condition,
                ])->values()->all(),
            ],
        ]);
    }

    public function complete(Request $request, WorkerOrder $workerOrder): RedirectResponse
    {
        $user = $request->user();
        $workerOrder->loadMissing('order');

        abort_unless(
            $workerOrder->order && $workerOrder->order->isAssignedToWorker($user),
            403,
            'هذا التركيب غير معيّن لك.',
        );

        if ($workerOrder->status === 'completed') {
            return back()->withErrors([
                'installation_photo' => 'تم رفع صورة التركيب مسبقاً لهذا المنتج.',
            ]);
        }

        $validated = $request->validate([
            'installation_photo' => ['required', 'image', 'max:5120'],
        ], [
            'installation_photo.required' => 'يجب تصوير التركيب من أرض الواقع قبل الإرسال.',
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
            'completed_by' => $user->id,
        ]);

        return redirect()
            ->route('pwa.installations.show', $workerOrder->order_id)
            ->with('success', 'تم تسجيل صورة التركيب بنجاح.');
    }

    public function pickup(Request $request, WorkerOrder $workerOrder): RedirectResponse
    {
        $user = $request->user();
        $workerOrder->loadMissing('order');

        abort_unless(
            $workerOrder->order && $workerOrder->order->isAssignedToWorker($user),
            403,
            'هذا التركيب غير معيّن لك.',
        );

        if ($workerOrder->status !== 'completed') {
            return back()->withErrors([
                'pickup_photo' => 'يجب تسجيل صورة التركيب أولاً قبل صورة الاستلام.',
            ]);
        }

        if ($workerOrder->pickup_photo) {
            return back()->withErrors([
                'pickup_photo' => 'تم رفع صورة الاستلام مسبقاً لهذا المنتج.',
            ]);
        }

        $validated = $request->validate([
            'pickup_photo' => ['required', 'image', 'max:5120'],
            'pickup_condition' => ['required', 'in:excellent,good,damaged,broken'],
        ], [
            'pickup_photo.required' => 'يجب تصوير المنتج عند الاستلام قبل الفك.',
            'pickup_photo.image' => 'يجب أن يكون الملف صورة.',
            'pickup_photo.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
            'pickup_condition.required' => 'يجب تحديد حالة المنتج عند الاستلام.',
            'pickup_condition.in' => 'حالة المنتج غير صالحة.',
        ]);

        $path = $validated['pickup_photo']->store('worker-pickups', 'public');

        $workerOrder->update([
            'pickup_photo' => $path,
            'pickup_at' => now(),
            'pickup_by' => $user->id,
            'pickup_condition' => $validated['pickup_condition'],
        ]);

        return redirect()
            ->route('pwa.installations.show', $workerOrder->order_id)
            ->with('success', 'تم تسجيل صورة الاستلام قبل الفك بنجاح.');
    }

    private function resolveMapUrl(?string $address): ?string
    {
        if (! $address || trim($address) === '') {
            return null;
        }

        $trimmed = trim($address);

        if (preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }

        return OrderWhatsAppMessage::locationMapsUrl($trimmed);
    }
}
