<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use App\Models\WorkerOrderNote;
use App\Support\WorkOrderPresenter;
use App\Support\WorkerPresenceBoard;
use App\Services\WorkerOrderSyncService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'pending';
        if (! in_array($status, ['pending', 'returned', 'all'], true)) {
            $status = 'pending';
        }

        $search = trim($request->string('search')->toString());
        $assignment = $request->string('assignment')->toString() ?: 'all';
        if (! in_array($assignment, ['all', 'assigned', 'unassigned'], true)) {
            $assignment = 'all';
        }

        $urgency = $request->string('urgency')->toString() ?: 'all';
        if (! in_array($urgency, ['all', 'overdue', 'due_today', 'upcoming', 'no_date'], true)) {
            $urgency = 'all';
        }

        $dismantlingFrom = $request->string('dismantling_from')->toString();
        $dismantlingTo = $request->string('dismantling_to')->toString();
        $activityFrom = $request->string('activity_from')->toString();
        $activityTo = $request->string('activity_to')->toString();
        $returnedFrom = $request->string('returned_from')->toString();
        $returnedTo = $request->string('returned_to')->toString();
        $workerId = $request->integer('worker_id') ?: null;

        $query = $this->eligibleReturnsQuery()
            ->with([
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
                'workerAssemblers' => fn ($q) => $q->dismantling()->select('id', 'order_id', 'worker_name', 'user_id', 'task_type'),
                'warehouseReturnedBy:id,customer_name',
                'workerNotes' => fn ($q) => $q->latest(),
                'workerNotes.user:id,customer_name,role',
            ])
            ->orderByRaw('activity_date IS NULL')
            ->orderByDesc('activity_date')
            ->orderByDesc('activity_time')
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

        if ($assignment === 'assigned') {
            $query->whereHas('workerAssemblers', fn (Builder $q) => $q->dismantling());
        } elseif ($assignment === 'unassigned') {
            $query->whereDoesntHave('workerAssemblers', fn (Builder $q) => $q->dismantling());
        }

        if ($urgency === 'no_date') {
            $query->whereNull('dismantling_at');
        } elseif ($urgency === 'overdue') {
            $query->whereNull('warehouse_returned_at')
                ->whereNotNull('dismantling_at')
                ->whereDate('dismantling_at', '<', now()->toDateString());
        } elseif ($urgency === 'due_today') {
            $query->whereNull('warehouse_returned_at')
                ->whereDate('dismantling_at', now()->toDateString());
        } elseif ($urgency === 'upcoming') {
            $query->whereNull('warehouse_returned_at')
                ->whereDate('dismantling_at', '>', now()->toDateString());
        }

        if ($dismantlingFrom !== '') {
            $query->whereDate('dismantling_at', '>=', $dismantlingFrom);
        }
        if ($dismantlingTo !== '') {
            $query->whereDate('dismantling_at', '<=', $dismantlingTo);
        }
        if ($activityFrom !== '') {
            $query->whereDate('activity_date', '>=', $activityFrom);
        }
        if ($activityTo !== '') {
            $query->whereDate('activity_date', '<=', $activityTo);
        }
        if ($returnedFrom !== '') {
            $query->whereDate('warehouse_returned_at', '>=', $returnedFrom);
        }
        if ($returnedTo !== '') {
            $query->whereDate('warehouse_returned_at', '<=', $returnedTo);
        }

        if ($workerId) {
            $query->whereHas('workerAssemblers', function (Builder $q) use ($workerId) {
                $q->dismantling()->where('user_id', $workerId);
            });
        }

        $user = $request->user();
        $canDecide = $this->canDecideReturn($user);

        $returns = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => $this->formatReturn($order, $canDecide));

        $base = $this->eligibleReturnsQuery();

        $activeFiltersCount = collect([
            $assignment !== 'all',
            $urgency !== 'all',
            $dismantlingFrom !== '',
            $dismantlingTo !== '',
            $activityFrom !== '',
            $activityTo !== '',
            $returnedFrom !== '',
            $returnedTo !== '',
            (bool) $workerId,
        ])->filter()->count();

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
            'workersBoard' => WorkerPresenceBoard::forReturns(),
            'availableWorkers' => WorkOrderPresenter::availableWorkers(),
            'stats' => [
                'pending' => (clone $base)->whereNull('warehouse_returned_at')->count(),
                'returned' => (clone $base)->whereNotNull('warehouse_returned_at')->count(),
            ],
            'filters' => [
                'status' => $status,
                'search' => $search,
                'assignment' => $assignment,
                'urgency' => $urgency,
                'dismantling_from' => $dismantlingFrom,
                'dismantling_to' => $dismantlingTo,
                'activity_from' => $activityFrom,
                'activity_to' => $activityTo,
                'returned_from' => $returnedFrom,
                'returned_to' => $returnedTo,
                'worker_id' => $workerId,
            ],
            'activeFiltersCount' => $activeFiltersCount,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($this->isEligibleReturn($order), 404);

        $order->load([
            'workerOrders' => fn ($q) => $q->orderBy('line_index'),
            'workerOrders.completedByUser:id,customer_name',
            'workerOrders.pickupByUser:id,customer_name',
            'warehouseReturnedBy:id,customer_name',
            'workerAssemblers' => fn ($q) => $q->latest(),
            'workerNotes' => fn ($q) => $q->latest(),
            'workerNotes.user:id,customer_name,role',
        ]);

        $user = $request->user();
        $canDecide = $this->canDecideReturn($user);

        return Inertia::render('Returns/Show', [
            'returnOrder' => $this->formatReturnDetail($order, $canDecide),
            'availableWorkers' => WorkOrderPresenter::availableWorkers(),
            'canAssignWorkers' => $this->canAssignWorkers($user),
            'canConfirm' => $canDecide && blank($order->warehouse_returned_at),
        ]);
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->isEligibleReturn($order), 404);
        abort_unless($this->canDecideReturn($request->user()), 403, 'غير مصرح لك بتعميد الاسترجاع.');

        if ($order->warehouse_returned_at) {
            return back()->with('error', 'تم تعميد استرجاع هذا الطلب للمستودع مسبقاً.');
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ], [
            'note.required' => 'يجب كتابة ملاحظة التعميد.',
            'note.max' => 'الملاحظة يجب ألا تتجاوز 2000 حرف.',
        ]);

        $note = trim($validated['note']);

        $order->forceFill([
            'warehouse_returned_at' => now(),
            'warehouse_returned_by' => $request->user()?->id,
            'warehouse_rejection_reason' => null,
            'warehouse_rejected_at' => null,
            'warehouse_rejected_by' => null,
        ])->save();

        WorkerOrderNote::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'body' => 'تعميد الاسترجاع: '.$note,
        ]);

        return back()->with('success', 'تم تعميد استرجاع منتجات الطلب '.$order->order_number.' للمستودع مع تسجيل الملاحظة. أصبح التأمين ظاهرًا الآن في صفحة استرداد التأمين.');
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

    public function storeAssembler(Request $request, Order $order, WorkerOrderSyncService $syncService): RedirectResponse
    {
        abort_unless($this->isEligibleReturn($order), 404);
        abort_unless($this->canAssignWorkers($request->user()), 403, 'غير مصرح لك بتعيين العمال.');

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
            ->dismantling()
            ->where(function ($query) use ($worker) {
                $query->where('user_id', $worker->id)
                    ->orWhere('worker_name', $worker->name);
            })
            ->exists();

        if ($alreadyAssigned) {
            return back()->withErrors([
                'user_id' => 'هذا العامل معيّن مسبقاً لفك هذا الطلب.',
            ]);
        }

        WorkerOrderAssembler::create([
            'order_id' => $order->id,
            'worker_order_id' => null,
            'worker_name' => $worker->name,
            'task_type' => WorkerOrderAssembler::TYPE_DISMANTLING,
            'user_id' => $worker->id,
            'created_by' => $request->user()->id,
        ]);

        // طلبات الاسترجاع قد لا تكون أُفرج عنها كأوامر عمل من قبل، لذلك ننشئ السطور
        // هنا بالقوة حتى تظهر للعامل المعيَّن في تطبيق العمال.
        if (! $order->workerOrders()->exists()) {
            $syncService->syncFromOrder($order, true);
        }

        return back()->with('success', 'تم تعيين العامل للفك بنجاح.');
    }

    public function destroyAssembler(Request $request, Order $order, WorkerOrderAssembler $assembler): RedirectResponse
    {
        abort_unless($this->isEligibleReturn($order), 404);
        abort_unless($this->canAssignWorkers($request->user()), 403, 'غير مصرح لك بحذف تعيين العمال.');
        abort_unless(
            $assembler->order_id === $order->id && $assembler->isDismantling(),
            404,
        );

        $assembler->delete();

        return back()->with('success', 'تم حذف عامل الفك.');
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

    private function canAssignWorkers(?User $user): bool
    {
        return (bool) $user?->hasAnyRole(
            User::ROLE_ADMIN,
            User::ROLE_GENERAL_MANAGER,
            User::ROLE_MANAGER,
            User::ROLE_WORKERS_MANAGER,
            User::ROLE_WAREHOUSE_KEEPER,
        );
    }

    private function canDecideReturn(?User $user): bool
    {
        return (bool) $user?->hasAnyRole(
            User::ROLE_ADMIN,
            User::ROLE_GENERAL_MANAGER,
            User::ROLE_MANAGER,
            User::ROLE_WORKERS_MANAGER,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReturn(Order $order, ?bool $canDecide = null): array
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

        $isReturned = filled($order->warehouse_returned_at);
        $dismantlingMeta = $this->dismantlingMeta($order->dismantling_at, $isReturned);

        $assemblers = $order->relationLoaded('workerAssemblers')
            ? $order->workerAssemblers->filter(fn (WorkerOrderAssembler $assembler) => $assembler->isDismantling())
            : collect();

        $assignedWorkers = $assemblers->pluck('worker_name')->filter()->unique()->values()->all();

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
            'is_returned' => $isReturned,
            'is_assigned' => count($assignedWorkers) > 0,
            'assigned_workers' => $assignedWorkers,
            'can_confirm' => ($canDecide ?? false) && ! $isReturned,
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
     * @return array<string, mixed>
     */
    private function formatReturnDetail(Order $order, ?bool $canDecide = null): array
    {
        $base = $this->formatReturn($order, $canDecide);
        $lines = $order->workerOrders;
        $assemblers = $order->workerAssemblers
            ->filter(fn (WorkerOrderAssembler $assembler) => $assembler->isDismantling())
            ->values();
        $installationWorkerNames = $order->workerAssemblers
            ->filter(fn (WorkerOrderAssembler $assembler) => $assembler->isInstallation())
            ->pluck('worker_name')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $installationWorkerFallback = $installationWorkerNames[0] ?? null;

        $products = $lines->isNotEmpty()
            ? $lines->map(fn (WorkerOrder $line) => [
                'id' => $line->id,
                'product_name' => $line->product_name,
                'product_image_url' => $line->product_image_url,
                'status' => $line->status,
                'installation_photo_url' => $line->installation_photo_url,
                'pickup_photo_url' => $line->pickup_photo_url,
                'pickup_at' => $line->pickup_at?->toIso8601String(),
                'pickup_by_name' => $line->pickupByUser?->name,
                'pickup_condition' => $line->pickup_condition,
                'completed_at' => $line->completed_at?->toIso8601String(),
                'completed_by_name' => $line->completedByUser?->name ?: $installationWorkerFallback,
            ])->values()->all()
            : collect($order->items ?? [])->values()->map(function ($item, int $index) {
                $row = is_array($item) ? $item : [];

                return [
                    'id' => $index + 1,
                    'product_name' => (string) ($row['name'] ?? $row['product_name'] ?? 'صنف'),
                    'product_image_url' => null,
                    'status' => null,
                    'installation_photo_url' => null,
                    'pickup_photo_url' => null,
                    'pickup_at' => null,
                    'pickup_by_name' => null,
                    'pickup_condition' => null,
                    'completed_at' => null,
                    'completed_by_name' => null,
                ];
            })->all();

        $pickupPhotosReady = $lines->isNotEmpty()
            && $lines->every(fn (WorkerOrder $line) => filled($line->pickup_photo));

        return array_merge($base, [
            'address' => $order->address,
            'activity_date' => $order->activity_date?->format('Y-m-d'),
            'activity_time' => ($order->getAttributes()['activity_time'] ?? null)
                ? \Carbon\Carbon::parse($order->getAttributes()['activity_time'])->format('H:i')
                : null,
            'products' => $products,
            'pickup_photos_ready' => $pickupPhotosReady,
            'pickup_photos_count' => $lines->filter(fn (WorkerOrder $line) => filled($line->pickup_photo))->count(),
            'assemblers' => $assemblers->map(fn (WorkerOrderAssembler $assembler) => [
                'id' => $assembler->id,
                'worker_name' => $assembler->worker_name,
                'user_id' => $assembler->user_id,
                'task_type' => WorkerOrderAssembler::TYPE_DISMANTLING,
                'created_at' => $assembler->created_at?->toIso8601String(),
            ])->values()->all(),
            'assigned_workers' => $assemblers->pluck('worker_name')->unique()->values()->all(),
        ]);
    }

    /**
     * @return array{days: int|null, label: string, tone: string}
     */
    private function dismantlingMeta(
        ?CarbonInterface $dismantlingAt,
        bool $isReturned = false,
    ): array {
        if ($isReturned) {
            return [
                'days' => null,
                'label' => 'تم الاسترجاع',
                'tone' => 'ok',
            ];
        }

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
