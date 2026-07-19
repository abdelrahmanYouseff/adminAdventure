<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Support\InsuranceApprovalChain;
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
            ->with([
                'invoice:id,invoice_number',
                'workOrderApprovedBy:id,customer_name',
                'insuranceManagerApprovedBy:id,customer_name',
                'insuranceGmApprovedBy:id,customer_name',
                'insuranceAccountsApprovedBy:id,customer_name',
                'workerNotes' => fn ($q) => $q->latest(),
                'workerNotes.user:id,customer_name,role',
            ])
            ->orderByDesc('work_order_approved_at');

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

    public function show(Request $request, Order $order): Response
    {
        abort_unless(
            $order->work_order_approved_at
            && ((float) $order->insurance_amount > 0 || (float) $order->insurance_original_amount > 0),
            404
        );

        $order->load([
            'invoice:id,invoice_number',
            'workOrderApprovedBy:id,customer_name',
            'insuranceManagerApprovedBy:id,customer_name',
            'insuranceGmApprovedBy:id,customer_name',
            'insuranceAccountsApprovedBy:id,customer_name',
            'workerOrders' => fn ($query) => $query->orderBy('line_index'),
            'workerOrders.completedByUser:id,customer_name',
            'workerOrders.pickupByUser:id,customer_name',
        ]);

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
            'pickup_photo_url' => $line->pickup_photo_url,
            'pickup_at' => $line->pickup_at?->toIso8601String(),
            'pickup_by_name' => $line->pickupByUser?->name,
            'pickup_condition' => $line->pickup_condition,
        ])->values()->all();

        return Inertia::render('InsuranceDeposits/Show', [
            'deposit' => $deposit,
        ]);
    }

    public function approve(Request $request, Order $order): RedirectResponse
    {
        abort_unless(
            $order->work_order_approved_at
            && ((float) $order->insurance_amount > 0 || (float) $order->insurance_original_amount > 0),
            404
        );

        $user = $request->user();
        $next = InsuranceApprovalChain::nextPendingStep($order);

        if ($next === null) {
            return back()->with('error', 'اكتملت سلسلة التعميدات مسبقاً.');
        }

        if ($next === InsuranceApprovalChain::STEP_WORKERS_MANAGER) {
            return back()->with('error', 'يجب تعميد أمر العمل من مدير العمال أولاً من صفحة أوامر العمل.');
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
        abort_unless(
            $order->work_order_approved_at
            && ((float) $order->insurance_amount > 0 || (float) $order->insurance_original_amount > 0),
            404
        );

        $user = $request->user();

        if (! $this->canEditRefundAmount($user, $order)) {
            return back()->with('error', 'تعديل مبلغ الاسترداد متاح للمحاسب فقط عند مرحلة التعميد أو قبل الاسترداد.');
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
        abort_unless(
            $order->work_order_approved_at
            && ((float) $order->insurance_amount > 0 || (float) $order->insurance_original_amount > 0),
            404
        );

        if (! InsuranceApprovalChain::isFullyApproved($order)) {
            return back()->with('error', 'لا يمكن استرداد التأمين قبل اكتمال سلسلة التعميدات (مدير العمال ← المسئول ← المدير العام ← المحاسب).');
        }

        $order->update([
            'insurance_status' => 'refunded',
            'insurance_refunded_at' => now(),
        ]);

        return back()->with('success', 'تم تسجيل استرداد التأمين للطلب '.$order->order_number);
    }

    public function markWithheld(Request $request, Order $order): RedirectResponse
    {
        abort_unless(
            $order->work_order_approved_at
            && ((float) $order->insurance_amount > 0 || (float) $order->insurance_original_amount > 0),
            404
        );

        if (! InsuranceApprovalChain::isFullyApproved($order)) {
            return back()->with('error', 'لا يمكن حجز التأمين قبل اكتمال سلسلة التعميدات (مدير العمال ← المسئول ← المدير العام ← المحاسب).');
        }

        $order->update([
            'insurance_status' => 'withheld',
            'insurance_refunded_at' => null,
        ]);

        return back()->with('success', 'تم تسجيل حجز التأمين للطلب '.$order->order_number);
    }

    /**
     * تظهر مبالغ التأمين فقط بعد تعميد مدير العمال لأمر العمل.
     */
    private function eligibleDepositsQuery(): Builder
    {
        return Order::query()
            ->whereNotNull('work_order_approved_at')
            ->where(function ($query) {
                $query->where('insurance_amount', '>', 0)
                    ->orWhere('insurance_original_amount', '>', 0);
            });
    }

    private function canEditRefundAmount(?User $user, Order $order): bool
    {
        if (! $user || $order->insurance_status !== 'pending') {
            return false;
        }

        if (! $user->hasAnyRole(User::ROLE_ACCOUNTS, User::ROLE_ADMIN)) {
            return false;
        }

        $next = InsuranceApprovalChain::nextPendingStep($order);

        // المحاسب يعدّل عند دوره أو بعد اكتمال السلسلة وقبل الاسترداد
        return $next === null || $next === InsuranceApprovalChain::STEP_ACCOUNTS;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDeposit(Order $order, ?User $user): array
    {
        $next = InsuranceApprovalChain::nextPendingStep($order);
        $fullyApproved = $next === null;
        $canApproveNext = $next
            && $next !== InsuranceApprovalChain::STEP_WORKERS_MANAGER
            && InsuranceApprovalChain::canUserApproveStep($user, $next);

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
            'approval_progress' => InsuranceApprovalChain::progress($order),
            'next_approval_step' => $next,
            'next_approval_label' => $next ? InsuranceApprovalChain::steps()[$next]['label'] : null,
            'can_approve_next' => $canApproveNext,
            'is_fully_approved' => $fullyApproved,
            'can_refund_or_withhold' => $fullyApproved && $order->insurance_status === 'pending',
            'can_edit_amount' => $this->canEditRefundAmount($user, $order),
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
}
