<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Jobs\SendDismantlingPhotosEmail;
use App\Jobs\SendInstallationPhotosEmail;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use App\Models\WorkerOrderNote;
use App\Support\OrderWhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Support\MediaStorage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WorkerInstallationController extends Controller
{
    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($order->isAssignedToWorker($user), 403, 'هذا الطلب غير معيّن لك.');

        $order->load([
            'workerOrders' => fn ($q) => $q->orderBy('line_index'),
            'workerNotes' => fn ($q) => $q->latest(),
            'workerNotes.user:id,customer_name,role',
        ]);

        $requestedTask = $request->string('task')->toString();
        $canonical = $this->canonicalTaskQuery($order, $user, $requestedTask);
        if ($canonical !== null) {
            return redirect()->route('pwa.installations.show', [
                'order' => $order->id,
                'task' => $canonical,
            ]);
        }

        $isDismantling = $this->resolveTaskIsDismantling($order, $user, $requestedTask);
        $lines = $order->workerOrders;
        $firstLine = $lines->first();
        $address = $firstLine?->customer_address ?? $order->address;

        if ($isDismantling) {
            abort_unless(
                $order->canEnterReturnsFlow(),
                403,
                'لا يمكن بدء الفك قبل تعميد التركيب من المسؤول.',
            );
        }

        if ($isDismantling) {
            $pendingCount = $lines->whereNull('pickup_photo')->count();
            $completedCount = $lines->whereNotNull('pickup_photo')->count();
            $scheduledDate = $order->dismantling_at?->format('Y-m-d');
            $scheduledTime = $order->dismantling_at?->format('H:i');
            $isApproved = filled($order->warehouse_returned_at);
            $notes = $order->workerNotes
                ->filter(fn (WorkerOrderNote $note) => $this->isDismantlingRelatedNote($note->body))
                ->values();
        } else {
            $pendingCount = $lines->where('status', 'pending')->count();
            $completedCount = $lines->where('status', 'completed')->count();
            $scheduledDate = ($order->scheduledInstallationDate() ?? $firstLine?->installation_date)?->format('Y-m-d');
            $scheduledTime = $order->scheduledInstallationTime();
            $isApproved = (bool) $order->work_order_approved_at;
            $notes = $order->workerNotes
                ->reject(fn (WorkerOrderNote $note) => $this->isDismantlingRelatedNote($note->body))
                ->values();
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
                'can_replace_photos' => false,
                'status' => $pendingCount > 0 ? 'pending' : 'completed',
                'task_type' => $isDismantling ? 'dismantling' : 'installation',
                'task_label' => $isDismantling ? 'فك' : 'تركيب',
                'rejection_reason' => $isDismantling && filled($order->warehouse_rejected_at) && blank($order->warehouse_returned_at)
                    ? (string) ($order->warehouse_rejection_reason ?: '')
                    : null,
                'products' => $lines->map(fn (WorkerOrder $line) => [
                    'id' => $line->id,
                    'product_name' => $line->product_name,
                    'product_image_url' => $line->product_image_url,
                    'status' => $isDismantling
                        ? (filled($line->pickup_photo) ? 'completed' : 'pending')
                        : $line->status,
                    // Dismantling never exposes installation photos.
                    'installation_photo_url' => $isDismantling
                        ? $line->pickup_photo_url
                        : $line->installation_photo_url,
                    'completed_at' => $isDismantling
                        ? $line->pickup_at?->toIso8601String()
                        : $line->completed_at?->toIso8601String(),
                ])->values()->all(),
                'notes' => $notes
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

        $task = $this->resolveTaskIsDismantling($order, $user, (string) $request->input('task_type', $request->query('task', '')))
            ? 'dismantling'
            : 'installation';

        return redirect()
            ->route('pwa.installations.show', ['order' => $order->id, 'task' => $task])
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

        $isDismantling = $this->resolveTaskIsDismantling(
            $workerOrder->order,
            $user,
            (string) $request->input('task_type', ''),
        );

        if ($isDismantling) {
            abort_unless(
                $workerOrder->order->canEnterReturnsFlow(),
                403,
                'لا يمكن رفع صور الفك قبل تعميد التركيب من المسؤول.',
            );

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

            $path = MediaStorage::store($validated['installation_photo'], 'worker-pickups');
            $oldPhoto = $workerOrder->pickup_photo;

            $workerOrder->update([
                'pickup_photo' => $path,
                'pickup_at' => now(),
                'pickup_by' => $user->id,
                'pickup_condition' => 'returned',
            ]);

            if ($oldPhoto && $oldPhoto !== $path) {
                MediaStorage::delete($oldPhoto);
            }

            $this->notifyDismantlingPhotosIfComplete($workerOrder->order, (int) $user->id);

            return redirect()
                ->route('pwa.installations.show', [
                    'order' => $workerOrder->order_id,
                    'task' => 'dismantling',
                ])
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

        $path = MediaStorage::store($validated['installation_photo'], 'worker-installations');
        $oldPhoto = $workerOrder->installation_photo;

        $workerOrder->update([
            'installation_photo' => $path,
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);

        if ($oldPhoto && $oldPhoto !== $path) {
            MediaStorage::delete($oldPhoto);
        }

        $this->notifyInstallationPhotosIfComplete($workerOrder->order, (int) $user->id);

        return redirect()
            ->route('pwa.installations.show', [
                'order' => $workerOrder->order_id,
                'task' => 'installation',
            ])
            ->with('success', 'تم تسجيل صورة التركيب بنجاح.');
    }

    public function destroyPhoto(Request $request, WorkerOrder $workerOrder): RedirectResponse
    {
        abort(403, 'لا يمكن للعامل حذف الصور. تواصل مع مدير العمال إن كانت الصورة تحتاج إعادة رفع.');
    }

    private function notifyInstallationPhotosIfComplete(?Order $order, int $workerUserId): void
    {
        if (! $order) {
            return;
        }

        $order->refresh();
        $order->loadMissing(['workerOrders']);

        if ($order->installation_photos_notified_at !== null) {
            return;
        }

        $lines = $order->workerOrders;
        if ($lines->isEmpty() || $lines->contains(fn (WorkerOrder $line) => $line->status !== 'completed' || blank($line->installation_photo))) {
            return;
        }

        try {
            $job = new SendInstallationPhotosEmail($order->id, $workerUserId);
            app()->call([$job, 'handle']);
        } catch (Throwable $e) {
            Log::error('Failed to send installation photos email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyDismantlingPhotosIfComplete(?Order $order, int $workerUserId): void
    {
        if (! $order) {
            return;
        }

        $order->refresh();
        $order->loadMissing(['workerOrders']);

        if ($order->dismantling_photos_notified_at !== null) {
            return;
        }

        $lines = $order->workerOrders;
        if ($lines->isEmpty() || $lines->contains(fn (WorkerOrder $line) => blank($line->pickup_photo))) {
            return;
        }

        try {
            // Sync so the email goes out without depending on a queue worker.
            $job = new SendDismantlingPhotosEmail($order->id, $workerUserId);
            app()->call([$job, 'handle']);
        } catch (Throwable $e) {
            Log::error('Failed to send dismantling photos email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * When the URL has no task, force a canonical task so فك never opens as تركيب.
     */
    private function canonicalTaskQuery(Order $order, $user, string $requestedTask): ?string
    {
        if (in_array($requestedTask, [
            WorkerOrderAssembler::TYPE_INSTALLATION,
            WorkerOrderAssembler::TYPE_DISMANTLING,
        ], true)) {
            return null;
        }

        $hasInstallation = $order->isAssignedToWorker($user, WorkerOrderAssembler::TYPE_INSTALLATION);
        $hasDismantling = $order->isAssignedToWorker($user, WorkerOrderAssembler::TYPE_DISMANTLING);

        if ($hasDismantling && ! $hasInstallation) {
            if (! $order->canEnterReturnsFlow()) {
                return null;
            }

            return WorkerOrderAssembler::TYPE_DISMANTLING;
        }

        if ($hasInstallation && ! $hasDismantling) {
            return WorkerOrderAssembler::TYPE_INSTALLATION;
        }

        if ($hasInstallation && $hasDismantling) {
            if (! $order->canEnterReturnsFlow()) {
                return WorkerOrderAssembler::TYPE_INSTALLATION;
            }

            // Bare URL with both assignments: keep install-first only while install photos pending.
            return $order->hasAllWorkerPhotos()
                ? WorkerOrderAssembler::TYPE_DISMANTLING
                : WorkerOrderAssembler::TYPE_INSTALLATION;
        }

        return null;
    }

    /**
     * Prefer explicit task from the separate list card / form; otherwise fall back
     * to the legacy phase detection (dismantling after installation when both).
     */
    private function resolveTaskIsDismantling(Order $order, $user, string $requestedTask): bool
    {
        $requestedTask = trim($requestedTask);

        if ($requestedTask === WorkerOrderAssembler::TYPE_DISMANTLING) {
            abort_unless(
                $order->isAssignedToWorker($user, WorkerOrderAssembler::TYPE_DISMANTLING),
                403,
                'هذا الطلب غير معيّن لك كمهمة فك.',
            );
            abort_unless(
                $order->canEnterReturnsFlow(),
                403,
                'لا يمكن بدء الفك قبل تعميد التركيب من المسؤول.',
            );

            return true;
        }

        if ($requestedTask === WorkerOrderAssembler::TYPE_INSTALLATION) {
            abort_unless(
                $order->isAssignedToWorker($user, WorkerOrderAssembler::TYPE_INSTALLATION),
                403,
                'هذا الطلب غير معيّن لك كمهمة تركيب.',
            );

            return false;
        }

        return $order->workerIsInDismantlingPhase($user);
    }

    private function isDismantlingRelatedNote(string $body): bool
    {
        $body = mb_strtolower($body);

        return str_contains($body, 'فك')
            || str_contains($body, 'استرجاع')
            || str_contains($body, 'رفض')
            || str_contains($body, 'تعميد الاسترجاع')
            || str_contains($body, 'pickup')
            || str_contains($body, 'dismantl');
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
