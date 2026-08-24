<?php

namespace App\Http\Controllers;

use App\Jobs\SendInstallationPhotosEmail;
use App\Models\Order;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use App\Models\WorkerOrderNote;
use App\Services\DeliveryNotePdfService;
use App\Services\ShortLinkService;
use App\Services\WhatsAppCloudService;
use App\Services\WorkerOrderSyncService;
use App\Support\DeliveryNotePdfData;
use App\Support\OrderInsuranceCalculator;
use App\Support\WorkOrderPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WorkerOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $view = $request->string('view')->toString();

        if ($user?->isWarehouseKeeper() && $view !== 'warehouse') {
            return redirect()->route('worker-orders.index', ['view' => 'warehouse']);
        }

        $isWarehouseView = $view === 'warehouse';
        $status = $request->string('status')->toString() ?: ($isWarehouseView ? 'all' : 'pending');
        $search = trim($request->string('search')->toString());
        $dateRange = $request->string('date_range')->toString() ?: 'all';

        return Inertia::render('WorkerOrders/Index', [
            'workOrders' => Inertia::defer(fn () => $this->paginatedWorkOrders($request, $status, $search, $dateRange, $isWarehouseView)),
            'stats' => Inertia::defer(fn () => $this->workOrderStats()),
            'filters' => [
                'status' => $status,
                'search' => $search,
                'date_range' => in_array($dateRange, ['all', '7', '30'], true) ? $dateRange : 'all',
                'view' => $isWarehouseView ? 'warehouse' : null,
            ],
        ]);
    }

    public function show(Request $request, string $workOrderKey, WorkerOrderSyncService $syncService)
    {
        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);
        WorkOrderPresenter::loadDetailRelations($order);

        $isWarehouseView = $request->string('view')->toString() === 'warehouse';
        $user = $request->user();

        if ($user?->isWarehouseKeeper()) {
            $isWarehouseView = true;
            abort_unless($this->isWarehouseQueueOrder($order), 404);
        }

        return Inertia::render('WorkerOrders/Show', [
            'workOrder' => WorkOrderPresenter::detail($order),
            'availableWorkers' => $isWarehouseView ? [] : WorkOrderPresenter::availableWorkers(),
            'filters' => [
                'view' => $isWarehouseView ? 'warehouse' : null,
            ],
        ]);
    }

    public function deliveryNote(Request $request, string $workOrderKey, DeliveryNotePdfService $pdfService, WorkerOrderSyncService $syncService): Response
    {
        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);
        $this->assertCanViewDeliveryNote($request, $order);

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

    public function testDeliveryNoteWhatsApp(
        Request $request,
        string $workOrderKey,
        DeliveryNotePdfService $pdfService,
        WorkerOrderSyncService $syncService,
        WhatsAppCloudService $whatsApp,
        ShortLinkService $shortLinks,
    ): RedirectResponse {
        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);

        $requestedPhone = trim((string) $request->input('phone', ''));
        $to = WhatsAppCloudService::normalizePhone(
            $requestedPhone !== '' ? $requestedPhone : (string) ($order->customer_phone ?? ''),
        );

        if (! preg_match('/^9665\d{8}$/', $to)) {
            return back()->with('error', 'أدخل رقم جوال سعودي صحيح يبدأ بـ 5.');
        }

        $shortLink = $shortLinks->createDeliveryNoteLink($order);
        $deliveryNoteUrl = $shortLinks->publicUrl($shortLink);

        $data = DeliveryNotePdfData::fromOrder($order, $deliveryNoteUrl);
        $pdf = $pdfService->render($data);
        $filename = 'delivery-note-'.$data->referenceNumber().'.pdf';

        $upload = $whatsApp->uploadMedia($pdf, 'application/pdf', $filename);
        if (! $upload['success'] || ! $upload['media_id']) {
            return back()->with('error', 'فشل رفع إذن التسليم إلى واتساب: '.($upload['error'] ?? 'خطأ غير معروف'));
        }

        $send = $whatsApp->sendDeliveryNoteToCustomer(
            $to,
            $upload['media_id'],
            $filename,
            $deliveryNoteUrl,
            $shortLinks->whatsappButtonSuffix($shortLink),
        );

        if (! $send['success']) {
            return back()->with('error', 'فشل إرسال إذن التسليم عبر واتساب: '.($send['error'] ?? 'خطأ غير معروف'));
        }

        $from = $send['from'] ?? $whatsApp->cloudSendingDisplayPhone();
        $message = 'تم إرسال إذن التسليم عبر واتساب إلى +'.$to
            .' من رقم '.$from
            .' — رابط النظام: '.$deliveryNoteUrl;

        return back()->with('success', $message);
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

        $this->notifyInstallationPhotosIfComplete($workerOrder->order, (int) $request->user()->id);

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

    public function destroyPhoto(Request $request, WorkerOrder $workerOrder): RedirectResponse
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
            'رفض صور التركيب مخصص لمدير العمال والمسئول فقط.',
        );

        $workerOrder->loadMissing('order.invoice');
        $order = $workerOrder->order;
        abort_unless($order, 404);

        if (blank($workerOrder->installation_photo) && $workerOrder->status !== 'completed') {
            return back()->with('error', 'لا توجد صورة تركيب لرفضها.');
        }

        $productName = (string) ($workerOrder->product_name ?: 'منتج');
        $wasApproved = filled($order->work_order_approved_at);

        if ($workerOrder->installation_photo) {
            Storage::disk('public')->delete($workerOrder->installation_photo);
        }

        $workerOrder->update([
            'installation_photo' => null,
            'status' => 'pending',
            'completed_at' => null,
            'completed_by' => null,
        ]);

        // رفض صورة بعد التعميد يلغي التعميد حتى يرفع العامل صورة جديدة ويُراجع الطلب من جديد.
        if ($wasApproved) {
            $order->forceFill([
                'work_order_approved_at' => null,
                'work_order_approved_by' => null,
            ])->save();
        }

        $order->forceFill([
            'installation_photos_notified_at' => null,
        ])->save();

        WorkerOrderNote::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'body' => 'رفض صورة التركيب وإعادة للعامل: '.$productName
                .($wasApproved ? ' (تم إلغاء تعميد أمر العمل)' : ''),
        ]);

        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        return redirect()
            ->route('worker-orders.show', $reference)
            ->with(
                'success',
                'تم رفض صورة التركيب للمنتج «'.$productName.'». يمكن للعامل رفع صورة جديدة.'
                .($wasApproved ? ' تم إلغاء التعميد حتى اكتمال المراجعة.' : ''),
            );
    }

    public function approve(Request $request, string $workOrderKey, WorkerOrderSyncService $syncService)
    {
        $user = $request->user();
        abort_unless(
            $user?->hasAnyRole(User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_WORKERS_MANAGER),
            403,
            'تعميد أمر العمل مخصص لمدير العمال والمسئول فقط.',
        );

        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);
        $order->load(['workerOrders', 'products', 'invoice:id,invoice_number']);

        if ($order->work_order_approved_at) {
            return back()->with('error', 'تم تعميد أمر العمل مسبقاً من مدير العمال.');
        }

        $isWorkersManager = (bool) $user?->isWorkersManager();
        $hadAllPhotos = $order->hasAllWorkerPhotos();

        if (! $hadAllPhotos && ! $isWorkersManager) {
            return back()->with('error', 'لا يمكن التعميد قبل رفع العامل لصور التركيب لجميع المنتجات.');
        }

        // مدير العمال يقدر يعتمد اكتمال التركيب بدون صور — نحدّث حالة البنود غير المكتملة
        if ($isWorkersManager) {
            foreach ($order->workerOrders as $line) {
                if ($line->status === 'completed') {
                    continue;
                }

                $line->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completed_by' => $user->id,
                ]);
            }

            $order->unsetRelation('workerOrders');
            $order->load('workerOrders');
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

        $message = $isWorkersManager && ! $hadAllPhotos
            ? 'تم تأكيد اكتمال التركيب من مدير العمال.'
            : 'تم تعميد أمر العمل من مدير العمال بنجاح.';
        if ($insuranceAmount > 0) {
            $message .= ' ظهر مبلغ التأمين في صفحة استرداد التأمين وبانتظار تعميد المسئول ثم المدير العام ثم المحاسب.';
        }

        return $this->redirectAfterMutation($request, $reference, $message);
    }

    public function approveWarehouse(Request $request, string $workOrderKey)
    {
        $user = $request->user();
        abort_unless(
            WorkOrderPresenter::canUserApproveWarehouse($user),
            403,
            'تعميد المستودع مخصص لأمين المستودع ومدير العمال والمسؤول.',
        );

        $order = Order::query()
            ->where(function ($query) use ($workOrderKey) {
                $query->whereKey($workOrderKey)
                    ->orWhere('order_number', $workOrderKey)
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', $workOrderKey));
            })
            ->first();

        abort_unless($order, 404);
        $order->load(['invoice:id,invoice_number']);

        if ($order->warehouse_keeper_approved_at) {
            return back()->with('error', 'تم تعميد المستودع وإغلاق هذا الطلب مسبقاً.');
        }

        if (! filled($order->warehouse_returned_at)) {
            return back()->with('error', 'لا يمكن تعميد المستودع قبل تعميد الاسترجاع من صفحة الاسترجاع.');
        }

        $order->update([
            'warehouse_keeper_approved_at' => now(),
            'warehouse_keeper_approved_by' => $user->id,
        ]);

        return redirect()
            ->route('worker-orders.index', ['view' => 'warehouse'])
            ->with('success', 'تم تعميد المستودع وإغلاق الطلب '.$order->order_number.' بنجاح.');
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

        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);

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
            ->installation()
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
            'task_type' => WorkerOrderAssembler::TYPE_INSTALLATION,
            'user_id' => $worker->id,
            'created_by' => $user->id,
        ]);

        $order->loadMissing('invoice:id,invoice_number');
        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        return $this->redirectAfterMutation($request, $reference, 'تم تعيين العامل للتركيب بنجاح.');
    }

    public function destroyAssembler(Request $request, string $workOrderKey, WorkerOrderAssembler $assembler, WorkerOrderSyncService $syncService)
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
            'غير مصرح لك بحذف تعيين العمال.',
        );

        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);

        abort_unless($assembler->order_id === $order->id, 404);
        abort_unless($assembler->isInstallation(), 404);

        $assembler->delete();

        $order->loadMissing('invoice:id,invoice_number');
        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        return $this->redirectAfterMutation($request, $reference, 'تم حذف العامل.');
    }

    public function storeNote(Request $request, string $workOrderKey, WorkerOrderSyncService $syncService)
    {
        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);

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

        return $this->redirectAfterMutation($request, $reference, 'تم إضافة الملاحظة.');
    }

    public function destroyNote(Request $request, string $workOrderKey, WorkerOrderNote $note, WorkerOrderSyncService $syncService)
    {
        $user = $request->user();
        abort_unless(
            $user?->hasAnyRole(User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER),
            403,
            'غير مصرح لك بحذف الملاحظات.',
        );

        $order = WorkOrderPresenter::resolve($workOrderKey, $syncService);

        abort_unless($note->order_id === $order->id, 404);

        $note->delete();

        $order->loadMissing('invoice:id,invoice_number');
        $reference = $order->invoice?->invoice_number ?? $order->order_number;

        return $this->redirectAfterMutation($request, $reference, 'تم حذف الملاحظة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedWorkOrders(
        Request $request,
        string $status,
        string $search = '',
        string $dateRange = 'all',
        bool $warehouseView = false,
    ): array
    {
        $query = Order::query()
            ->releasedToOperations()
            ->when(! $warehouseView, fn ($q) => $q->whereHas('workerOrders'))
            ->with([
                'invoice:id,invoice_number',
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
                'warehouseReturnedBy:id,customer_name',
                'warehouseKeeperApprovedBy:id,customer_name',
            ])
            ->withCount([
                'workerOrders as total_lines',
                'workerOrders as pending_lines' => fn ($q) => $q->where('status', 'pending'),
                'workerOrders as completed_lines' => fn ($q) => $q->where('status', 'completed'),
                'workerAssemblers as assigned_workers_count' => fn ($q) => $q->installation(),
            ]);

        if ($warehouseView) {
            $query = $this->warehouseOrdersQuery()
                ->with([
                    'invoice:id,invoice_number',
                    'workerOrders' => fn ($q) => $q->orderBy('line_index'),
                    'warehouseReturnedBy:id,customer_name',
                    'warehouseKeeperApprovedBy:id,customer_name',
                ])
                ->withCount([
                    'workerOrders as total_lines',
                    'workerOrders as pending_lines' => fn ($q) => $q->where('status', 'pending'),
                    'workerOrders as completed_lines' => fn ($q) => $q->where('status', 'completed'),
                ]);
        } elseif ($status === 'pending') {
            $query->whereHas('workerOrders', fn ($q) => $q->where('status', 'pending'));
        } elseif ($status === 'completed') {
            $query->whereDoesntHave('workerOrders', fn ($q) => $q->where('status', 'pending'))
                ->whereHas('workerOrders', fn ($q) => $q->where('status', 'completed'));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('workerOrders', function ($workerOrder) use ($search) {
                        $workerOrder->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%");
                    });
            });
        }

        if (! $warehouseView && in_array($dateRange, ['7', '30'], true)) {
            $from = now()->subDays((int) $dateRange)->startOfDay();
            $query->where(function ($q) use ($from) {
                $q->whereDate('activity_date', '>=', $from)
                    ->orWhereHas('workerOrders', fn ($workerOrder) => $workerOrder->whereDate('installation_date', '>=', $from));
            });
        }

        if ($warehouseView) {
            $query->orderByDesc('warehouse_returned_at')->orderByDesc('id');
        } elseif ($status === 'completed') {
            $query->orderByDesc(
                WorkerOrder::query()
                    ->select('completed_at')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderByDesc('completed_at')
                    ->limit(1)
            );
        } else {
            // Newest released work orders first so accountant approvals surface immediately.
            $query->orderByDesc(
                WorkerOrder::query()
                    ->select('created_at')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderByDesc('created_at')
                    ->limit(1)
            )->orderByDesc('orders.created_at');
        }

        return $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Order $order) => WorkOrderPresenter::summary($order))
            ->toArray();
    }

    /**
     * @return array{pending: int, completed: int, warehouse: int, total: int}
     */
    private function workOrderStats(): array
    {
        return [
            'pending' => Order::query()->releasedToOperations()->whereHas('workerOrders', fn ($q) => $q->where('status', 'pending'))->count(),
            'completed' => Order::query()->releasedToOperations()->whereHas('workerOrders')
                ->whereDoesntHave('workerOrders', fn ($q) => $q->where('status', 'pending'))
                ->count(),
            'warehouse' => $this->warehouseOrdersQuery()->count(),
            'total' => Order::query()->releasedToOperations()->whereHas('workerOrders')->count(),
        ];
    }

    private function warehouseOrdersQuery()
    {
        return Order::query()
            ->whereNotNull('work_order_approved_at')
            ->whereNotNull('warehouse_returned_at')
            ->whereNotNull('warehouse_returned_by')
            ->whereNull('warehouse_keeper_approved_at')
            ->whereNotIn('status', ['cancelled', 'refunded']);
    }

    private function isWarehouseQueueOrder(Order $order): bool
    {
        return $order->canEnterWarehouseQueue();
    }

    private function assertCanViewDeliveryNote(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user?->hasAnyRole(
            User::ROLE_ADMIN,
            User::ROLE_GENERAL_MANAGER,
            User::ROLE_MANAGER,
            User::ROLE_WORKERS_MANAGER,
        )) {
            return;
        }

        $hasPublicLink = ShortLink::query()
            ->where('type', ShortLink::TYPE_DELIVERY_NOTE)
            ->where('order_id', $order->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasPublicLink) {
            return;
        }

        if (! $user) {
            abort(redirect()->guest(route('login')));
        }

        abort(403, 'غير مصرح بعرض إذن التسليم.');
    }

    private function assertCanUploadWorkerPhotos(Request $request): void
    {
        $user = $request->user();

        // مدير العمال يعتمد فقط بعد رفع العامل للصور — لا يرفعها بنفسه.
        abort_if(
            $user?->isWorkersManager(),
            403,
            'لا يمكن لمدير العمال رفع الصور. يجب على العامل رفع صور التركيب أولاً ثم التعميد.',
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

    private function redirectAfterMutation(Request $request, string $reference, string $message): RedirectResponse
    {
        $returnTo = $request->input('return_to') ?? $request->query('return_to');

        if (is_string($returnTo) && preg_match('#^/main-app/work-orders/[A-Za-z0-9\-_.%]+$#', $returnTo)) {
            return redirect()->to($returnTo)->with('success', $message);
        }

        return redirect()
            ->route('worker-orders.show', $reference)
            ->with('success', $message);
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
}
