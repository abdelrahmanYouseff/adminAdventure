<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrderNote;
use App\Support\InsuranceApprovalChain;
use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceDepositController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $user = $request->user();

        $query = $this->eligibleDepositsQuery()
            ->with(array_merge($this->approvalRelations(), [
                'invoice:id,invoice_number',
                'workOrderApprovedBy:id,customer_name',
                'warehouseReturnedBy:id,customer_name',
                'workerNotes' => fn ($q) => $q->latest(),
                'workerNotes.user:id,customer_name,role',
            ]))
            ->orderByDesc('insurance_refund_requested_at');

        if (in_array($status, ['pending', 'refunded', 'withheld'], true)) {
            $query->where('insurance_status', $status);
        }

        $deposits = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => $this->formatDeposit($order, $user));

        $stats = [
            'pending' => $this->eligibleDepositsQuery()->where('insurance_status', 'pending')->count(),
            'refunded' => $this->eligibleDepositsQuery()->where('insurance_status', 'refunded')->count(),
            'withheld' => $this->eligibleDepositsQuery()->where('insurance_status', 'withheld')->count(),
            'pending_amount' => (float) $this->eligibleDepositsQuery()->where('insurance_status', 'pending')->sum('insurance_amount'),
        ];

        return Inertia::render('InsuranceDeposits/Index', [
            'deposits' => $deposits,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $query = Invoice::query()
            ->where('status', 'paid')
            ->whereHas('order', fn (Builder $order) => $order
                ->whereNotIn('status', ['cancelled', 'refunded']))
            ->with([
                'order:id,invoice_id,customer_name,customer_phone,insurance_amount',
                'user:id,customer_name,phone',
            ])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $user) use ($search) {
                        $user->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('order', function (Builder $order) use ($search) {
                        $order->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query
            ->limit($search === '' ? 80 : 150)
            ->get()
            ->map(function (Invoice $invoice) {
                $order = $invoice->order;
                $customerName = $order?->customer_name ?: ($invoice->user?->name ?: 'عميل');
                $amount = round((float) $invoice->amount, 2);
                $formattedAmount = number_format($amount, 2);

                return [
                    'id' => $invoice->id,
                    'order_id' => $order?->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $customerName,
                    'customer_phone' => $order?->customer_phone ?: $invoice->user?->phone,
                    'invoice_amount' => $amount,
                    'insurance_amount' => round((float) ($order?->insurance_amount ?? 0), 2),
                    'label' => $customerName.' — '.$invoice->invoice_number.' — '.$formattedAmount,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('InsuranceDeposits/Create', [
            'invoices' => $invoices,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'insurance_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_proof' => ['required', 'array', 'min:1', 'max:10'],
            'payment_proof.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'invoice_id.required' => 'اختر الفاتورة.',
            'invoice_id.exists' => 'الفاتورة المحددة غير موجودة.',
            'insurance_amount.required' => 'مبلغ التأمين مطلوب.',
            'insurance_amount.numeric' => 'مبلغ التأمين غير صالح.',
            'insurance_amount.min' => 'مبلغ التأمين يجب أن يكون أكبر من صفر.',
            'payment_proof.required' => 'يجب إرفاق إيصال الدفع.',
            'payment_proof.min' => 'يجب إرفاق إيصال الدفع.',
            'payment_proof.max' => 'يمكن رفع 10 ملفات كحد أقصى.',
            'payment_proof.*.required' => 'يجب إرفاق إيصال الدفع.',
            'payment_proof.*.file' => 'مرفق الإيصال غير صالح.',
            'payment_proof.*.mimes' => 'الصيغ المسموحة: jpg, jpeg, png, webp, pdf.',
            'payment_proof.*.max' => 'حجم المرفق يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        $invoice = Invoice::query()
            ->with('order')
            ->whereKey((int) $validated['invoice_id'])
            ->first();

        $order = $invoice?->order;

        if (
            ! $order
            || in_array($order->status, ['cancelled', 'refunded'], true)
        ) {
            return back()
                ->withInput()
                ->with('error', 'لا يمكن تسجيل طلب استرداد تأمين على هذه الفاتورة.');
        }

        $amount = round((float) $validated['insurance_amount'], 2);

        if (filled($order->insurance_refund_requested_at) && $order->insurance_status === 'pending') {
            return back()
                ->withInput()
                ->with('error', 'هذا الطلب موجود بالفعل في استرداد التأمين وبانتظار الاعتماد.');
        }

        $proofPaths = [];
        $files = $request->file('payment_proof');
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        foreach ($files as $file) {
            if ($file) {
                $proofPaths[] = MediaStorage::store($file, 'insurance-payment-proofs');
            }
        }

        if ($proofPaths === []) {
            return back()
                ->withInput()
                ->withErrors(['payment_proof' => 'يجب إرفاق إيصال الدفع.']);
        }

        $order->update([
            'insurance_amount' => $amount,
            'insurance_original_amount' => $order->insurance_original_amount ?: $amount,
            'insurance_status' => 'pending',
            'insurance_refunded_at' => null,
            'insurance_refund_requested_at' => now(),
            'insurance_refund_requested_by' => $request->user()?->id,
            'insurance_payment_proof' => $proofPaths,
            'insurance_workers_manager_approved_at' => null,
            'insurance_workers_manager_approved_by' => null,
            'insurance_accounts_received_at' => null,
            'insurance_accounts_received_by' => null,
            'insurance_admin_approved_at' => null,
            'insurance_admin_approved_by' => null,
            'insurance_accounts_approved_at' => null,
            'insurance_accounts_approved_by' => null,
        ]);

        return redirect()
            ->route('insurance-deposits.index', ['status' => 'pending'])
            ->with(
                'success',
                'تم رفع طلب استرداد التأمين للفاتورة '.$invoice->invoice_number.' وهو بانتظار اعتماد مدير العمال.',
            );
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($this->isEligibleDeposit($order), 404);

        $order->load(array_merge($this->approvalRelations(), [
            'invoice:id,invoice_number',
            'workOrderApprovedBy:id,customer_name',
            'warehouseReturnedBy:id,customer_name',
            'workerNotes' => fn ($q) => $q->latest(),
            'workerNotes.user:id,customer_name,role',
            'workerOrders' => fn ($query) => $query->orderBy('line_index'),
            'workerOrders.completedByUser:id,customer_name',
        ]));

        $deposit = $this->formatDeposit($order, $request->user());
        $deposit['address'] = $order->address;
        $deposit['worker_lines'] = $order->workerOrders->map(fn ($line) => [
            'id' => $line->id,
            'product_name' => $line->product_name,
            'product_image_url' => $line->product_image_url,
            'status' => $line->status,
            'installation_photo_url' => $line->installation_photo_url,
            'completed_at' => $line->completed_at?->toIso8601String(),
            'completed_by_name' => $line->completedByUser?->name,
        ])->values()->all();

        return Inertia::render('InsuranceDeposits/Show', [
            'deposit' => $deposit,
        ]);
    }

    public function storeNote(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->isEligibleDeposit($order), 404);

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

        return back()->with('success', 'تم حفظ الملاحظة.');
    }

    public function approve(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->isEligibleDeposit($order), 404);

        $user = $request->user();
        $next = InsuranceApprovalChain::nextPendingStep($order);

        if ($next === null) {
            return back()->with('error', 'اكتملت سلسلة التعميدات مسبقاً.');
        }

        if (! InsuranceApprovalChain::canUserApproveStep($user, $next)) {
            return back()->with('error', InsuranceApprovalChain::blockedMessage($order, $user));
        }

        $step = InsuranceApprovalChain::steps()[$next];

        $order->update([
            $step['at'] => now(),
            $step['by'] => $user->id,
        ]);

        $message = 'تم تعميد '.$step['label'].' للطلب '.$order->order_number.' بنجاح.';

        if (InsuranceApprovalChain::isFullyApproved($order->fresh())) {
            $message .= ' اكتملت سلسلة التعميدات ويمكن الآن استرداد أو حجز التأمين.';
        }

        return back()->with('success', $message);
    }

    public function updateAmount(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->isEligibleDeposit($order), 404);

        $user = $request->user();

        if (! $this->canEditRefundAmount($user, $order)) {
            return back()->with('error', 'تعديل مبلغ الاسترداد متاح للمسؤول والمدير العام فقط قبل الاسترداد أو الحجز.');
        }

        $validated = $request->validate([
            'insurance_amount' => ['required', 'numeric', 'min:0'],
        ], [
            'insurance_amount.required' => 'مبلغ الاسترداد مطلوب.',
            'insurance_amount.numeric' => 'مبلغ الاسترداد غير صالح.',
            'insurance_amount.min' => 'مبلغ الاسترداد لا يمكن أن يكون سالباً.',
        ]);

        $amount = round((float) $validated['insurance_amount'], 2);

        if (! $order->insurance_original_amount) {
            $order->insurance_original_amount = $order->insurance_amount;
        }

        $order->insurance_amount = $amount;
        $order->save();

        return back()->with('success', 'تم تحديث مبلغ الاسترداد للطلب '.$order->order_number.' إلى '.number_format($amount, 2).' ر.س');
    }

    public function markRefunded(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->isEligibleDeposit($order), 404);

        if (! InsuranceApprovalChain::isFullyApproved($order)) {
            return back()->with('error', 'لا يمكن استرداد التأمين قبل اكتمال سلسلة التعميدات ('.InsuranceApprovalChain::summary().').');
        }

        if (! $request->user()?->hasAnyRole(User::ROLE_ADMIN, User::ROLE_ACCOUNTS)) {
            return back()->with('error', 'استرداد التأمين متاح للمحاسب والادمن بعد اكتمال السلسلة.');
        }

        $order->update([
            'insurance_status' => 'refunded',
            'insurance_refunded_at' => now(),
        ]);

        return back()->with('success', 'تم تسجيل استرداد التأمين للطلب '.$order->order_number);
    }

    public function markWithheld(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->isEligibleDeposit($order), 404);

        if (! InsuranceApprovalChain::isFullyApproved($order)) {
            return back()->with('error', 'لا يمكن حجز التأمين قبل اكتمال سلسلة التعميدات ('.InsuranceApprovalChain::summary().').');
        }

        if (! $request->user()?->hasAnyRole(User::ROLE_ADMIN, User::ROLE_ACCOUNTS)) {
            return back()->with('error', 'حجز التأمين متاح للمحاسب والادمن بعد اكتمال السلسلة.');
        }

        $order->update([
            'insurance_status' => 'withheld',
            'insurance_refunded_at' => null,
        ]);

        return back()->with('success', 'تم تسجيل حجز التأمين للطلب '.$order->order_number);
    }

    /**
     * تظهر الطلبات التي رُفع لها استرداد تأمين من هذه الصفحة.
     */
    private function eligibleDepositsQuery(): Builder
    {
        return Order::query()
            ->whereNotNull('insurance_refund_requested_at')
            ->where(function ($query) {
                $query->where('insurance_amount', '>', 0)
                    ->orWhere('insurance_original_amount', '>', 0);
            });
    }

    private function isEligibleDeposit(Order $order): bool
    {
        return filled($order->insurance_refund_requested_at)
            && ((float) $order->insurance_amount > 0 || (float) $order->insurance_original_amount > 0);
    }

    private function canEditRefundAmount(?User $user, Order $order): bool
    {
        if (! $user || $order->insurance_status !== 'pending') {
            return false;
        }

        // المسؤول والمدير العام (والأدمن) يعدّلون مبلغ الاسترداد — المحاسب لا
        return $user->hasAnyRole(
            User::ROLE_MANAGER,
            User::ROLE_GENERAL_MANAGER,
            User::ROLE_ADMIN,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDeposit(Order $order, ?User $user): array
    {
        $next = InsuranceApprovalChain::nextPendingStep($order);
        $fullyApproved = $next === null;
        $canApproveNext = (bool) ($next && InsuranceApprovalChain::canUserApproveStep($user, $next));

        $original = (float) ($order->insurance_original_amount ?: $order->insurance_amount);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'invoice_number' => $order->invoice?->invoice_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'insurance_amount' => (float) $order->insurance_amount,
            'insurance_original_amount' => $original,
            'insurance_status' => $order->insurance_status,
            'insurance_refunded_at' => $order->insurance_refunded_at?->toIso8601String(),
            'payment_status' => $order->payment_status,
            'activity_date' => $order->activity_date?->format('Y-m-d'),
            'created_at' => $order->created_at?->toIso8601String(),
            'approved_at' => $order->work_order_approved_at?->toIso8601String(),
            'warehouse_returned_at' => $order->warehouse_returned_at?->toIso8601String(),
            'warehouse_returned_by_name' => $order->relationLoaded('warehouseReturnedBy')
                ? $order->warehouseReturnedBy?->name
                : null,
            'approval_progress' => InsuranceApprovalChain::progress($order),
            'next_approval_step' => $next,
            'next_approval_label' => $next ? InsuranceApprovalChain::steps()[$next]['label'] : null,
            'waiting_on_label' => $next ? InsuranceApprovalChain::steps()[$next]['label'] : null,
            'approval_chain_summary' => InsuranceApprovalChain::summary(),
            'can_approve_next' => $canApproveNext,
            'is_fully_approved' => $fullyApproved,
            'can_refund_or_withhold' => $fullyApproved
                && $order->insurance_status === 'pending'
                && (bool) $user?->hasAnyRole(User::ROLE_ADMIN, User::ROLE_ACCOUNTS),
            'can_edit_amount' => $this->canEditRefundAmount($user, $order),
            'payment_proof_urls' => $order->insurance_payment_proof_urls,
            'notes' => $order->relationLoaded('workerNotes')
                ? $order->workerNotes->map(fn ($note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'user_name' => $note->user?->name ?: 'مستخدم',
                    'user_role' => $note->user?->roleLabel() ?? 'مستخدم',
                    'created_at' => $note->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'notes_count' => $order->relationLoaded('workerNotes')
                ? $order->workerNotes->count()
                : 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function approvalRelations(): array
    {
        return [
            'insuranceWorkersManagerApprovedBy:id,customer_name',
            'insuranceAccountsReceivedBy:id,customer_name',
            'insuranceAdminApprovedBy:id,customer_name',
            'insuranceAccountsApprovedBy:id,customer_name',
        ];
    }
}
