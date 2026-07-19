<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use App\Models\WorkerOrderNote;
use App\Services\DeliveryNotePdfService;
use App\Services\WorkerOrderSyncService;
use App\Support\DeliveryNotePdfData;
use App\Support\InsuranceApprovalChain;
use App\Support\OrderInsuranceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'workerOrders.completedByUser:id,customer_name',
            'workerOrders.pickupByUser:id,customer_name',
            'workerAssemblers' => fn ($query) => $query->latest(),
            'workerNotes' => fn ($query) => $query->latest(),
            'workerNotes.user:id,customer_name,role',
            'workOrderApprovedBy:id,customer_name',
        ]);

        return Inertia::render('WorkerOrders/Show', [
            'workOrder' => $this->formatWorkOrderDetail($order),
            'availableWorkers' => User::query()
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
                ->all(),
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
        $this->assertCanUploadWorkerPhotos($request);

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

    public function completePickup(Request $request, WorkerOrder $workerOrder)
    {
        $this->assertCanUploadWorkerPhotos($request);

        if ($workerOrder->status !== 'completed') {
            return back()->withErrors([
                'pickup_photo' => 'يجب رفع صورة التركيب أولاً قبل صورة الاستلام والفك.',
            ]);
        }

        if ($workerOrder->pickup_photo) {
            return back()->withErrors([
                'pickup_photo' => 'تم رفع صورة الاستلام والفك مسبقاً لهذا المنتج.',
            ]);
        }

        $validated = $request->validate([
            'pickup_photo' => ['required', 'image', 'max:5120'],
            'pickup_condition' => ['required', 'in:excellent,good,damaged,broken'],
        ], [
            'pickup_photo.required' => 'يجب إرفاق صورة عند الاستلام والفك من عند العميل.',
            'pickup_photo.image' => 'يجب أن يكون الملف صورة.',
            'pickup_photo.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
            'pickup_condition.required' => 'يجب تحديد حالة المنتج عند الاستلام.',
            'pickup_condition.in' => 'حالة المنتج غير صالحة.',
        ]);

        $path = $validated['pickup_photo']->store('worker-pickups', 'public');

        $workerOrder->loadMissing('order.invoice');

        $workerOrder->update([
            'pickup_photo' => $path,
            'pickup_at' => now(),
            'pickup_by' => $request->user()->id,
            'pickup_condition' => $validated['pickup_condition'],
        ]);

        $reference = $workerOrder->order->invoice?->invoice_number
            ?? $workerOrder->order->order_number;

        return redirect()
            ->route('worker-orders.show', $reference)
            ->with('success', 'تم رفع صورة الاستلام والفك بنجاح.');
    }

    public function approve(Request $request, string $workOrderKey, WorkerOrderSyncService $syncService)
    {
        $user = $request->user();
        abort_unless(
            $user?->hasAnyRole(User::ROLE_ADMIN, User::ROLE_WORKERS_MANAGER),
            403,
            'تعميد أمر العمل مخصص لمدير العمال فقط.',
        );

        $order = $this->resolveWorkOrder($workOrderKey, $syncService);
        $order->load(['workerOrders', 'products', 'invoice:id,invoice_number']);

        if ($order->work_order_approved_at) {
            return back()->with('error', 'تم تعميد أمر العمل مسبقاً من مدير العمال.');
        }

        if (! $order->hasAllWorkerPhotos()) {
            return back()->with('error', 'لا يمكن التعميد قبل رفع العامل لصور التركيب وصور الاستلام لجميع المنتجات.');
        }

        $updates = [
            'work_order_approved_at' => now(),
            'work_order_approved_by' => $user->id,
        ];

        $insuranceAmount = $this->resolveInsuranceAmountForApproval($order);

        if ($insuranceAmount > 0) {
            $updates['insurance_amount'] = $insuranceAmount;

            if (! $order->insurance_original_amount) {
                $updates['insurance_original_amount'] = $insuranceAmount;
            }

            if (! in_array($order->insurance_status, ['refunded', 'withheld'], true)) {
                $updates['insurance_status'] = 'pending';
            }
        }

        $order->update($updates);

        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        $message = 'تم تعميد أمر العمل من مدير العمال بنجاح.';
        if ($insuranceAmount > 0) {
            $message .= ' ظهر مبلغ التأمين في صفحة استرداد التأمين وبانتظار تعميد المسئول ثم المدير العام ثم المحاسب.';
        }

        return redirect()
            ->route('worker-orders.show', $reference)
            ->with('success', $message);
    }

    public function storeAssembler(Request $request, string $workOrderKey, WorkerOrderSyncService $syncService)
    {
        $user = $request->user();
        abort_unless(
            $user?->hasAnyRole(
                User::ROLE_ADMIN,
                User::ROLE_GENERAL_MANAGER,
                User::ROLE_MANAGER,
                User::ROLE_WORKERS_MANAGER,
            ),
            403,
            'غير مصرح لك بتعيين العمال.',
        );

        $order = $this->resolveWorkOrder($workOrderKey, $syncService);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_WORKER)),
            ],
        ], [
            'user_id.required' => 'يجب اختيار العامل.',
            'user_id.exists' => 'العامل المحدد غير صالح.',
        ]);

        $worker = User::query()->findOrFail($validated['user_id']);

        $alreadyAssigned = WorkerOrderAssembler::query()
            ->where('order_id', $order->id)
            ->where(function ($query) use ($worker) {
                $query->where('user_id', $worker->id)
                    ->orWhere('worker_name', $worker->name);
            })
            ->exists();

        if ($alreadyAssigned) {
            return back()->withErrors([
                'user_id' => 'هذا العامل معيّن مسبقاً لهذا الأمر.',
            ]);
        }

        WorkerOrderAssembler::create([
            'order_id' => $order->id,
            'worker_order_id' => null,
            'worker_name' => $worker->name,
            'user_id' => $worker->id,
            'created_by' => $user->id,
        ]);

        $order->loadMissing('invoice:id,invoice_number');
        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        return redirect()
            ->route('worker-orders.show', $reference)
            ->with('success', 'تم تعيين العامل للتركيب بنجاح.');
    }

    public function destroyAssembler(string $workOrderKey, WorkerOrderAssembler $assembler, WorkerOrderSyncService $syncService)
    {
        $user = request()->user();
        abort_unless(
            $user?->hasAnyRole(
                User::ROLE_ADMIN,
                User::ROLE_GENERAL_MANAGER,
                User::ROLE_MANAGER,
                User::ROLE_WORKERS_MANAGER,
            ),
            403,
            'غير مصرح لك بحذف تعيين العمال.',
        );

        $order = $this->resolveWorkOrder($workOrderKey, $syncService);

        abort_unless($assembler->order_id === $order->id, 404);

        $assembler->delete();

        $order->loadMissing('invoice:id,invoice_number');
        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        return redirect()
            ->route('worker-orders.show', $reference)
            ->with('success', 'تم حذف العامل.');
    }

    public function storeNote(Request $request, string $workOrderKey, WorkerOrderSyncService $syncService)
    {
        $order = $this->resolveWorkOrder($workOrderKey, $syncService);

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

        $order->loadMissing('invoice:id,invoice_number');
        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        return redirect()
            ->route('worker-orders.show', $reference)
            ->with('success', 'تم إضافة الملاحظة.');
    }

    public function destroyNote(string $workOrderKey, WorkerOrderNote $note, WorkerOrderSyncService $syncService)
    {
        $user = request()->user();
        abort_unless(
            $user?->hasAnyRole(User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER),
            403,
            'غير مصرح لك بحذف الملاحظات.',
        );

        $order = $this->resolveWorkOrder($workOrderKey, $syncService);

        abort_unless($note->order_id === $order->id, 404);

        $note->delete();

        $order->loadMissing('invoice:id,invoice_number');
        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        return redirect()
            ->route('worker-orders.show', $reference)
            ->with('success', 'تم حذف الملاحظة.');
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
        $photosReady = $order->hasAllWorkerPhotos();
        $isApproved = (bool) $order->work_order_approved_at;
        $user = auth()->user();
        $canApprove = $photosReady
            && ! $isApproved
            && InsuranceApprovalChain::canUserApproveStep($user, InsuranceApprovalChain::STEP_WORKERS_MANAGER);

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
            'photos_ready' => $photosReady,
            'is_approved' => $isApproved,
            'can_approve' => $canApprove,
            'approved_at' => $order->work_order_approved_at?->toIso8601String(),
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
        $lines = $order->workerOrders;
        $installedCount = $lines->where('status', 'completed')->count();
        $pickupDoneCount = $lines->whereNotNull('pickup_photo')->count();
        $total = $lines->count();
        $assignedWorkers = $order->workerAssemblers->pluck('worker_name')->unique()->values()->all();

        $eventStatus = 'pending';
        if ($installedCount === $total && $total > 0 && $pickupDoneCount === $total) {
            $eventStatus = 'completed';
        } elseif ($installedCount === $total && $total > 0) {
            $eventStatus = 'pickup';
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
            'pickup_progress' => [
                'done' => $pickupDoneCount,
                'total' => $installedCount,
            ],
            'photo_stats' => [
                'installation' => $lines->whereNotNull('installation_photo')->count(),
                'pickup' => $pickupDoneCount,
            ],
            'lines' => $lines->map(fn (WorkerOrder $line) => $this->formatWorkerOrderLine($line))->values()->all(),
            'assemblers' => $order->workerAssemblers
                ->map(fn (WorkerOrderAssembler $assembler) => [
                    'id' => $assembler->id,
                    'worker_name' => $assembler->worker_name,
                    'user_id' => $assembler->user_id,
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
            'timeline' => $this->buildTimeline($order),
            'delivery_note_url' => '/worker-orders/'.rawurlencode($summary['reference_number']).'/delivery-note',
            'approved_by_name' => $order->workOrderApprovedBy?->name,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(Order $order): array
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

        $firstAssembler = $order->workerAssemblers->sortBy('created_at')->first();
        $items[] = [
            'key' => 'worker_assigned',
            'title' => 'تعيين العمال',
            'description' => $firstAssembler
                ? 'تم تسجيل العامل: '.$firstAssembler->worker_name
                : 'لم يتم تسجيل عمال بعد',
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

            $items[] = [
                'key' => 'pickup_'.$line->id,
                'title' => 'اكتمال الاستلام والفك',
                'description' => $line->product_name,
                'timestamp' => $line->pickup_at?->toIso8601String(),
                'user_name' => $line->pickupByUser?->name,
                'completed' => (bool) $line->pickup_photo,
            ];
        }

        $allDone = $order->workerOrders->isNotEmpty()
            && $order->workerOrders->every(fn (WorkerOrder $line) => $line->status === 'completed' && $line->pickup_photo);

        $latestPickup = $order->workerOrders
            ->filter(fn (WorkerOrder $line) => $line->pickup_at !== null)
            ->sortByDesc(fn (WorkerOrder $line) => $line->pickup_at?->getTimestamp() ?? 0)
            ->first();

        $items[] = [
            'key' => 'completed',
            'title' => 'اكتمال الفعالية',
            'description' => $allDone ? 'تم إنهاء التركيب والاستلام بنجاح' : 'بانتظار اكتمال جميع المراحل',
            'timestamp' => $allDone ? $latestPickup?->pickup_at?->toIso8601String() : null,
            'user_name' => null,
            'completed' => $allDone,
        ];

        $items[] = [
            'key' => 'approved',
            'title' => 'تعميد مدير العمال',
            'description' => $order->work_order_approved_at
                ? 'تم اعتماد اكتمال التركيب والاستلام — بانتظار سلسلة التعميدات في استرداد التأمين'
                : 'بانتظار تعميد مدير العمال بعد رفع كل الصور',
            'timestamp' => $order->work_order_approved_at?->toIso8601String(),
            'user_name' => $order->workOrderApprovedBy?->name,
            'completed' => (bool) $order->work_order_approved_at,
        ];

        return $items;
    }

    private function assertCanUploadWorkerPhotos(Request $request): void
    {
        $user = $request->user();

        // مدير العمال يعتمد فقط بعد رفع العامل للصور — لا يرفعها بنفسه.
        abort_if(
            $user?->isWorkersManager(),
            403,
            'لا يمكن لمدير العمال رفع الصور. يجب على العامل رفع صور التركيب والاستلام أولاً ثم التعميد.',
        );
    }

    /**
     * مبلغ التأمين المحفوظ على الطلب، أو المحسوب من المنتجات عند التعميد.
     */
    private function resolveInsuranceAmountForApproval(Order $order): float
    {
        $stored = round((float) $order->insurance_amount, 2);
        if ($stored > 0) {
            return $stored;
        }

        $fromPivot = round((float) $order->products->sum(
            fn ($product) => (float) ($product->pivot->insurance_amount ?? 0)
                * max(1, (int) ($product->pivot->quantity ?? 1))
        ), 2);

        if ($fromPivot > 0) {
            return $fromPivot;
        }

        $lines = $order->products
            ->map(fn ($product) => [
                'product_id' => $product->id,
                'quantity' => max(1, (int) ($product->pivot->quantity ?? 1)),
            ])
            ->values()
            ->all();

        if ($lines === []) {
            return 0.0;
        }

        return OrderInsuranceCalculator::fromLines($lines)['total'];
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
            'pickup_photo_url' => $workerOrder->pickup_photo_url,
            'pickup_at' => $workerOrder->pickup_at?->toIso8601String(),
            'pickup_by_user' => $workerOrder->pickupByUser ? [
                'id' => $workerOrder->pickupByUser->id,
                'name' => $workerOrder->pickupByUser->name,
            ] : null,
            'pickup_condition' => $workerOrder->pickup_condition,
        ];
    }
}
