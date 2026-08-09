<?php

namespace App\Http\Controllers;

use App\Models\CompanyClient;
use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\User;
use App\Services\OrderPaymentReceiptService;
use App\Services\WorkerOrderSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderPaymentReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $user = $request->user();
        $canApprove = $this->canApprove($user);

        $receipts = OrderPaymentReceipt::query()
            ->with([
                'order:id,user_id,order_number,customer_name,customer_phone,customer_email,address,total_amount,amount_paid,currency',
                'order.user:id,customer_name,phone,phone_secondary,email,iban,iban_image',
                'recordedBy:id,customer_name',
                'approvedBy:id,customer_name',
                'rejectedBy:id,customer_name',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('receipt_number', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($orderQuery) use ($search) {
                            $orderQuery->where('order_number', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%")
                                ->orWhere('customer_phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when(in_array($status, [
                OrderPaymentReceipt::STATUS_PENDING,
                OrderPaymentReceipt::STATUS_APPROVED,
                OrderPaymentReceipt::STATUS_REJECTED,
            ], true), function ($query) use ($status) {
                $query->where('approval_status', $status);
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (OrderPaymentReceipt $receipt) use ($canApprove) {
                $order = $receipt->order;
                $total = round((float) ($receipt->total_amount ?: $order?->total_amount ?: 0), 2);
                $isApproved = $receipt->isApproved();
                $isPending = $receipt->isPending();
                $isRejected = $receipt->isRejected();
                $remaining = round((float) ($receipt->remaining_after ?? $order?->remaining_amount ?? 0), 2);
                if ($isRejected && $order) {
                    $remaining = round((float) ($order->remaining_amount ?? 0), 2);
                }
                $customer = $order ? $this->resolveCustomerProfile($order) : null;

                return [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'amount' => (float) $receipt->amount,
                    'total_amount' => $total,
                    'remaining_amount' => $remaining,
                    'payment_method' => $receipt->payment_method,
                    'type' => $receipt->type,
                    'approval_status' => $receipt->approval_status,
                    'is_approved' => $isApproved,
                    'is_rejected' => $isRejected,
                    'can_approve' => $canApprove && $isPending,
                    'can_reject' => $canApprove && $isPending,
                    'approved_at' => $receipt->approved_at?->toIso8601String(),
                    'approved_by_name' => $receipt->approvedBy?->customer_name,
                    'rejection_reason' => $receipt->rejection_reason,
                    'rejected_at' => $receipt->rejected_at?->toIso8601String(),
                    'rejected_by_name' => $receipt->rejectedBy?->customer_name,
                    'created_at' => optional($receipt->created_at)?->toIso8601String(),
                    'order' => $order ? [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'currency' => $order->currency ?: 'SAR',
                    ] : null,
                    'customer' => $customer,
                    'recorded_by_name' => $receipt->recordedBy?->customer_name,
                    'proof_image_url' => $receipt->proof_image_url,
                    'account_number' => $receipt->account_number,
                ];
            });

        $statusCounts = [
            'all' => OrderPaymentReceipt::query()->count(),
            'pending' => OrderPaymentReceipt::query()->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)->count(),
            'approved' => OrderPaymentReceipt::query()->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)->count(),
            'rejected' => OrderPaymentReceipt::query()->where('approval_status', OrderPaymentReceipt::STATUS_REJECTED)->count(),
        ];

        $allowedStatuses = [
            'all',
            OrderPaymentReceipt::STATUS_PENDING,
            OrderPaymentReceipt::STATUS_APPROVED,
            OrderPaymentReceipt::STATUS_REJECTED,
        ];

        return Inertia::render('PaymentReceipts/Index', [
            'receipts' => $receipts,
            'stats' => [
                'pending' => $statusCounts['pending'],
                'approved' => $statusCounts['approved'],
                'rejected' => $statusCounts['rejected'],
            ],
            'statusCounts' => $statusCounts,
            'canApprove' => $canApprove,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, $allowedStatuses, true) ? $status : 'all',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function approve(Request $request, OrderPaymentReceipt $receipt): RedirectResponse
    {
        if (! $this->canApprove($request->user())) {
            return back()->with('error', 'اعتماد سندات القبض متاح للمحاسب والمسؤول فقط.');
        }

        if ($receipt->isApproved()) {
            return back()->with('error', 'تم اعتماد هذا السند مسبقاً.');
        }

        if ($receipt->isRejected()) {
            return back()->with('error', 'لا يمكن اعتماد سند مرفوض.');
        }

        try {
            $result = app(OrderPaymentReceiptService::class)->approveReceipt($receipt, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Always attempt sync after a successful approval. The sync service is
        // idempotent and gated on approved receipts, so this also recovers from
        // cases where the first-approval flag was missed or status did not change.
        $order = Order::query()->with('products')->find($result['receipt']->order_id);
        $workOrderReady = false;

        if ($order) {
            app(WorkerOrderSyncService::class)->syncFromOrder($order);
            $workOrderReady = $order->workerOrders()->exists();
            app(\App\Services\QuotationToOrderService::class)->markQuotationAcceptedFromOrder($order);
        }

        $message = 'تم اعتماد سند القبض '.$result['receipt']->receipt_number.' بنجاح.';
        if ($workOrderReady) {
            $message .= $result['released_work_order']
                ? ' وتم تحويل العرض إلى طلب وإصدار أمر العمل '.$order->order_number.'.'
                : ' وأمر العمل '.$order->order_number.' متاح في صفحة أوامر العمل.';
        }

        return back()->with('success', $message);
    }

    public function reject(Request $request, OrderPaymentReceipt $receipt): RedirectResponse
    {
        if (! $this->canApprove($request->user())) {
            return back()->with('error', 'رفض سندات القبض متاح للمحاسب والمسؤول فقط.');
        }

        if ($receipt->isApproved()) {
            return back()->with('error', 'لا يمكن رفض سند معتمد مسبقاً.');
        }

        if ($receipt->isRejected()) {
            return back()->with('error', 'تم رفض هذا السند مسبقاً.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'rejection_reason.required' => 'يجب كتابة سبب الرفض.',
            'rejection_reason.min' => 'سبب الرفض قصير جداً.',
        ]);

        try {
            $rejected = app(OrderPaymentReceiptService::class)->rejectReceipt(
                $receipt,
                $validated['rejection_reason'],
                $request->user(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'تم رفض سند القبض '.$rejected->receipt_number.' دون إصدار أمر عمل. الطلب بقي كما كان.',
        );
    }

    private function canApprove(?User $user): bool
    {
        return $user !== null && ($user->isAccounts() || $user->hasAdminAccess());
    }

    /**
     * Resolve the richest customer profile available for an order:
     * company client by phone → linked user → order fields.
     *
     * @return array{
     *     name: string|null,
     *     phone: string|null,
     *     phone_secondary: string|null,
     *     email: string|null,
     *     address: string|null,
     *     iban: string|null,
     *     iban_image_url: string|null,
     *     source: string,
     *     type: string|null
     * }
     */
    private function resolveCustomerProfile(Order $order): array
    {
        $base = [
            'name' => $order->customer_name,
            'phone' => $order->customer_phone,
            'phone_secondary' => null,
            'email' => $order->customer_email,
            'address' => $order->address,
            'iban' => null,
            'iban_image_url' => null,
            'source' => 'order',
            'type' => null,
        ];

        $variants = $this->phoneLookupVariants((string) ($order->customer_phone ?? ''));

        if ($variants !== []) {
            $companyClient = CompanyClient::query()
                ->where(function ($query) use ($variants) {
                    foreach ($variants as $variant) {
                        $query->orWhere('phone', $variant);
                    }
                })
                ->orderByDesc('updated_at')
                ->first();

            if ($companyClient) {
                $displayName = $companyClient->company_name;
                if ($companyClient->contact_name) {
                    $displayName = $companyClient->company_name.' — '.$companyClient->contact_name;
                }

                return [
                    'name' => $displayName ?: $order->customer_name,
                    'phone' => $companyClient->phone ?: $order->customer_phone,
                    'phone_secondary' => $companyClient->phone_secondary,
                    'email' => $companyClient->email ?: $order->customer_email,
                    'address' => $companyClient->address ?: $order->address,
                    'iban' => $companyClient->iban,
                    'iban_image_url' => $companyClient->iban_image_url,
                    'source' => 'company_client',
                    'type' => 'شركة',
                ];
            }
        }

        $linkedUser = $order->user;
        if (! $linkedUser && $variants !== []) {
            $linkedUser = User::query()
                ->where(function ($query) use ($variants) {
                    foreach ($variants as $variant) {
                        $query->orWhere('phone', $variant);
                    }
                })
                ->orderByDesc('updated_at')
                ->first();
        }

        if ($linkedUser) {
            return [
                'name' => $linkedUser->customer_name ?: $order->customer_name,
                'phone' => $linkedUser->phone ?: $order->customer_phone,
                'phone_secondary' => $linkedUser->phone_secondary,
                'email' => $linkedUser->email ?: $order->customer_email,
                'address' => $order->address,
                'iban' => $linkedUser->iban,
                'iban_image_url' => $linkedUser->iban_image_url,
                'source' => 'user',
                'type' => 'فرد',
            ];
        }

        return $base;
    }

    /**
     * @return list<string>
     */
    private function phoneLookupVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return [];
        }

        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $local = substr($digits, 1);
        } else {
            $local = $digits;
        }

        if ($local === '') {
            return [];
        }

        return array_values(array_unique(array_filter([
            $local,
            '0'.$local,
            '966'.$local,
            '+966'.$local,
        ])));
    }
}
