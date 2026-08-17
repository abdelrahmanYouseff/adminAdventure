<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use App\Models\WorkerOrderNote;
use App\Services\WorkerOrderSyncService;
use Carbon\Carbon;

class WorkOrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Order $order, ?User $user = null): array
    {
        $user ??= auth()->user();
        $firstLine = $order->workerOrders->first();
        $pendingLines = (int) ($order->pending_lines ?? 0);
        $totalLines = (int) ($order->total_lines ?? $order->workerOrders->count());
        $photosReady = $order->hasAllWorkerPhotos();
        $isApproved = (bool) $order->work_order_approved_at;
        $roleCanApprove = InsuranceApprovalChain::canUserApproveStep(
            $user,
            InsuranceApprovalChain::STEP_WORKERS_MANAGER,
        );
        // مدير العمال يقدر يعتمد اكتمال التركيب بدون انتظار صور العامل
        $canApproveWithoutPhotos = (bool) $user?->isWorkersManager();
        $canApprove = $roleCanApprove
            && ! $isApproved
            && ($photosReady || $canApproveWithoutPhotos);

        $isAssigned = self::hasAssignedInstallationWorkers($order);

        return [
            'id' => $order->id,
            'reference_number' => $order->invoice?->invoice_number ?? $order->order_number,
            'order_number' => $order->order_number,
            'invoice_number' => $order->invoice?->invoice_number,
            'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
            'customer_address' => $firstLine?->customer_address ?? $order->address,
            'installation_date' => ($firstLine?->installation_date ?? $order->activity_date)?->format('Y-m-d'),
            'activity_time' => ($order->getAttributes()['activity_time'] ?? null)
                ? Carbon::parse($order->getAttributes()['activity_time'])->format('H:i')
                : null,
            'status' => $pendingLines > 0 ? 'pending' : 'completed',
            'products_count' => $totalLines,
            'pending_count' => $pendingLines,
            'completed_count' => (int) ($order->completed_lines ?? 0),
            'location_slug' => $order->location_slug,
            'photos_ready' => $photosReady,
            'is_approved' => $isApproved,
            'can_approve' => $canApprove,
            'is_assigned' => $isAssigned,
            'approved_at' => $order->work_order_approved_at?->toIso8601String(),
            'currency' => $order->currency ?: 'SAR',
            'total_amount' => (float) $order->total_amount,
            'amount_paid' => (float) ($order->amount_paid ?? 0),
            'remaining_amount' => (float) $order->remaining_amount,
            'preview_products' => $order->workerOrders->take(3)->map(fn (WorkerOrder $line) => [
                'name' => $line->product_name,
                'image_url' => $line->product_image_url,
            ])->values()->all(),
        ];
    }

    private static function hasAssignedInstallationWorkers(Order $order): bool
    {
        if (isset($order->assigned_workers_count)) {
            return (int) $order->assigned_workers_count > 0;
        }

        if ($order->relationLoaded('workerAssemblers')) {
            return $order->workerAssemblers->contains(
                fn (WorkerOrderAssembler $assembler) => $assembler->isInstallation(),
            );
        }

        return $order->workerAssemblers()->installation()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Order $order, ?User $user = null): array
    {
        $summary = self::summary($order, $user);
        $lines = $order->workerOrders;
        $installedCount = $lines->where('status', 'completed')->count();
        $total = $lines->count();
        $installationAssemblers = $order->workerAssemblers
            ->filter(fn (WorkerOrderAssembler $assembler) => $assembler->isInstallation())
            ->values();
        $assignedWorkers = $installationAssemblers->pluck('worker_name')->unique()->values()->all();

        $eventStatus = 'pending';
        if ($installedCount === $total && $total > 0) {
            $eventStatus = 'completed';
        } elseif ($installedCount > 0) {
            $eventStatus = 'in_progress';
        }

        return array_merge($summary, [
            'event_status' => $eventStatus,
            'created_at' => $order->created_at?->toIso8601String(),
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'address' => $order->address ?: $summary['customer_address'],
            'assigned_workers' => $assignedWorkers,
            'installation_progress' => [
                'done' => $installedCount,
                'total' => $total,
            ],
            'photo_stats' => [
                'installation' => $lines->whereNotNull('installation_photo')->count(),
                'pickup' => $lines->whereNotNull('pickup_photo')->count(),
            ],
            'lines' => $lines->map(fn (WorkerOrder $line) => self::line($line))->values()->all(),
            'assemblers' => $installationAssemblers
                ->map(fn (WorkerOrderAssembler $assembler) => [
                    'id' => $assembler->id,
                    'worker_name' => $assembler->worker_name,
                    'user_id' => $assembler->user_id,
                    'task_type' => $assembler->task_type ?: WorkerOrderAssembler::TYPE_INSTALLATION,
                    'created_at' => $assembler->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'notes' => $order->workerNotes
                ->map(fn (WorkerOrderNote $note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'user_name' => $note->user?->name ?: 'مستخدم',
                    'user_role' => $note->user?->roleLabel() ?? 'مستخدم',
                    'created_at' => $note->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'timeline' => self::timeline($order),
            'delivery_note_url' => '/worker-orders/'.rawurlencode($summary['reference_number']).'/delivery-note',
            'approved_by_name' => $order->workOrderApprovedBy?->name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function line(WorkerOrder $workerOrder): array
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
            'pickup_photo_url' => $workerOrder->pickup_photo_url,
            'pickup_at' => $workerOrder->pickup_at?->toIso8601String(),
            'pickup_condition' => $workerOrder->pickup_condition,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function timeline(Order $order): array
    {
        $items = [];

        $items[] = [
            'key' => 'created',
            'title' => 'تم إنشاء الفعالية',
            'description' => 'تم تسجيل أمر العمل رقم '.$order->order_number,
            'timestamp' => $order->created_at?->toIso8601String(),
            'user_name' => null,
            'completed' => true,
        ];

        $firstAssembler = $order->workerAssemblers
            ->filter(fn (WorkerOrderAssembler $assembler) => $assembler->isInstallation())
            ->sortBy('created_at')
            ->first();
        $items[] = [
            'key' => 'worker_assigned',
            'title' => 'تعيين عمال التركيب',
            'description' => $firstAssembler
                ? 'تم تسجيل العامل: '.$firstAssembler->worker_name
                : 'لم يتم تسجيل عمال تركيب بعد',
            'timestamp' => $firstAssembler?->created_at?->toIso8601String(),
            'user_name' => $firstAssembler?->worker_name,
            'completed' => (bool) $firstAssembler,
        ];

        foreach ($order->workerOrders as $line) {
            $items[] = [
                'key' => 'install_'.$line->id,
                'title' => 'اكتمال التركيب',
                'description' => $line->product_name,
                'timestamp' => $line->completed_at?->toIso8601String(),
                'user_name' => $line->completedByUser?->name,
                'completed' => $line->status === 'completed',
            ];
        }

        $allDone = $order->workerOrders->isNotEmpty()
            && $order->workerOrders->every(fn (WorkerOrder $line) => $line->status === 'completed' && filled($line->installation_photo));

        $latestInstall = $order->workerOrders
            ->filter(fn (WorkerOrder $line) => $line->completed_at !== null)
            ->sortByDesc(fn (WorkerOrder $line) => $line->completed_at?->getTimestamp() ?? 0)
            ->first();

        $items[] = [
            'key' => 'completed',
            'title' => 'اكتمال الفعالية',
            'description' => $allDone ? 'تم إنهاء التركيب بنجاح' : 'بانتظار اكتمال التركيب',
            'timestamp' => $allDone ? $latestInstall?->completed_at?->toIso8601String() : null,
            'user_name' => null,
            'completed' => $allDone,
        ];

        $items[] = [
            'key' => 'approved',
            'title' => 'تعميد مدير العمال',
            'description' => $order->work_order_approved_at
                ? 'تم اعتماد اكتمال التركيب — بانتظار سلسلة التعميدات في استرداد التأمين'
                : 'بانتظار تعميد مدير العمال بعد رفع صور التركيب',
            'timestamp' => $order->work_order_approved_at?->toIso8601String(),
            'user_name' => $order->workOrderApprovedBy?->name,
            'completed' => (bool) $order->work_order_approved_at,
        ];

        return $items;
    }

    public static function resolve(string $workOrderKey, WorkerOrderSyncService $syncService): Order
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

        if (! $order->workerOrders()->exists() && $order->hasApprovedPaymentReceipt()) {
            $syncService->syncFromOrder($order->fresh());
            $order->unsetRelation('workerOrders');
        }

        abort_unless($order->workerOrders()->exists(), 404);

        return $order;
    }

    public static function loadDetailRelations(Order $order): Order
    {
        $order->load([
            'invoice:id,invoice_number',
            'workerOrders' => fn ($query) => $query->orderBy('line_index'),
            'workerOrders.completedByUser:id,customer_name',
            'workerAssemblers' => fn ($query) => $query->latest(),
            'workerNotes' => fn ($query) => $query->latest(),
            'workerNotes.user:id,customer_name,role',
            'workOrderApprovedBy:id,customer_name',
        ]);

        return $order;
    }

    /**
     * @return list<array{id: int, name: string, phone: string|null, email: string|null}>
     */
    public static function availableWorkers(): array
    {
        return User::query()
            ->where('role', User::ROLE_WORKER)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name', 'phone', 'email'])
            ->map(fn (User $worker) => [
                'id' => $worker->id,
                'name' => $worker->name,
                'phone' => $worker->phone,
                'email' => $worker->email,
            ])
            ->values()
            ->all();
    }
}
