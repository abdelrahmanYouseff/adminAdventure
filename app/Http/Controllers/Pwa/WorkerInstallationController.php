<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderNote;
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
        abort_unless($order->isAssignedToWorker($user), 403, 'هذا الطلب غير معيّن لك.');

        $order->load([
            'workerOrders' => fn ($q) => $q->orderBy('line_index'),
            'workerNotes' => fn ($q) => $q->latest(),
            'workerNotes.user:id,customer_name,role',
        ]);

        $assignmentType = $order->primaryWorkerAssignmentType($user);
        $isDismantling = $order->workerIsInDismantlingPhase($user);
        $lines = $order->workerOrders;
        $firstLine = $lines->first();
        $address = $firstLine?->customer_address ?? $order->address;

        if ($isDismantling) {
            $pendingCount = $lines->whereNull('pickup_photo')->count();
            $completedCount = $lines->whereNotNull('pickup_photo')->count();
            $scheduledDate = $order->dismantling_at?->format('Y-m-d');
            $scheduledTime = $order->dismantling_at?->format('H:i');
            $isApproved = filled($order->warehouse_returned_at);
        } else {
            $pendingCount = $lines->where('status', 'pending')->count();
            $completedCount = $lines->where('status', 'completed')->count();
            $scheduledDate = ($order->scheduledInstallationDate() ?? $firstLine?->installation_date)?->format('Y-m-d');
            $scheduledTime = $order->scheduledInstallationTime();
            $isApproved = (bool) $order->work_order_approved_at;
        }

        return Inertia::render('InstallationShow', [
            'installation' => [
                'id' => $order->id,
                'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'map_url' => $this->resolveMapUrl($address),
                'installation_date' => $scheduledDate,
                'activity_time' => $scheduledTime,
                'products_count' => $lines->count(),
                'pending_count' => $pendingCount,
                'completed_count' => $completedCount,
                'is_approved' => $isApproved,
                // العمال لا يحذفون الصور — حذف الصور متاح لمدير العمال من لوحة التحكم فقط.
                'can_replace_photos' => false,
                'status' => $pendingCount > 0 ? 'pending' : 'completed',
                'task_type' => $isDismantling ? 'dismantling' : ($assignmentType === 'both' ? 'both' : 'installation'),
                'task_label' => $isDismantling ? 'فك' : ($assignmentType === 'both' ? 'تركيب + فك' : 'تركيب'),
                'products' => $lines->map(fn (WorkerOrder $line) => [
                    'id' => $line->id,
                    'product_name' => $line->product_name,
                    'product_image_url' => $line->product_image_url,
                    'status' => $isDismantling
                        ? (filled($line->pickup_photo) ? 'completed' : 'pending')
                        : $line->status,
                    'installation_photo_url' => $isDismantling
                        ? $line->pickup_photo_url
                        : $line->installation_photo_url,
                    'completed_at' => $isDismantling
                        ? $line->pickup_at?->toIso8601String()
                        : $line->completed_at?->toIso8601String(),
                ])->values()->all(),
                'notes' => $order->workerNotes
                    ->map(fn (WorkerOrderNote $note) => [
                        'id' => $note->id,
                        'body' => $note->body,
                        'user_name' => $note->user?->name ?: 'مستخدم',
                        'is_mine' => (int) $note->user_id === (int) $user->id,
                        'created_at' => $note->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function storeNote(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($order->isAssignedToWorker($user), 403, 'هذا الطلب غير معيّن لك.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'يجب كتابة الملاحظة.',
            'body.max' => 'الملاحظة يجب ألا تتجاوز 2000 حرف.',
        ]);

        WorkerOrderNote::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'body' => trim($validated['body']),
        ]);

        return redirect()
            ->route('pwa.installations.show', $order)
            ->with('success', 'تم حفظ الملاحظة.');
    }

    public function complete(Request $request, WorkerOrder $workerOrder): RedirectResponse
    {
        $user = $request->user();
        $workerOrder->loadMissing('order');

        abort_unless(
            $workerOrder->order && $workerOrder->order->isAssignedToWorker($user),
            403,
            'هذا الطلب غير معيّن لك.',
        );

        $isDismantling = $workerOrder->order->workerIsInDismantlingPhase($user);

        if ($isDismantling) {
            if (filled($workerOrder->pickup_photo)) {
                return back()->withErrors([
                    'installation_photo' => 'تم رفع صورة الفك مسبقاً لهذا المنتج.',
                ]);
            }

            $validated = $request->validate([
                'installation_photo' => ['required', 'image', 'max:5120'],
            ], [
                'installation_photo.required' => 'يجب تصوير المنتج عند الفك من أرض الواقع قبل الإرسال.',
                'installation_photo.image' => 'يجب أن يكون الملف صورة.',
                'installation_photo.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
            ]);

            $path = $validated['installation_photo']->store('worker-pickups', 'public');

            if ($workerOrder->pickup_photo) {
                Storage::disk('public')->delete($workerOrder->pickup_photo);
            }

            $workerOrder->update([
                'pickup_photo' => $path,
                'pickup_at' => now(),
                'pickup_by' => $user->id,
                'pickup_condition' => 'returned',
            ]);

            return redirect()
                ->route('pwa.installations.show', $workerOrder->order_id)
                ->with('success', 'تم إرسال صورة الفك بنجاح. ستظهر للمسؤول في تفاصيل الاسترجاع.');
        }

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

    public function destroyPhoto(Request $request, WorkerOrder $workerOrder): RedirectResponse
    {
        abort(403, 'لا يمكن للعامل حذف الصور. تواصل مع مدير العمال إن كانت الصورة تحتاج إعادة رفع.');
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
