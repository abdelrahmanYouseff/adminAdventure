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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
        $page = max(1, (int) $request->query('page', 1));
        $user = $request->user();
        $canApprove = $this->canApprove($user);

        $receipts = OrderPaymentReceipt::query()
            ->with([
                'order:id,user_id,order_number,customer_name,customer_phone,customer_email,address,total_amount,amount_paid,currency,notes',
                'order.user:id,customer_name,phone,phone_secondary,email,iban,iban_image',
                'recordedBy:id,customer_name',
                'approvedBy:id,customer_name',
                'rejectedBy:id,customer_name',
            ])
            ->latest('id')
            ->get();

        $profilesByOrderId = [];
        foreach ($receipts as $receipt) {
            $order = $receipt->order;
            if (! $order || isset($profilesByOrderId[$order->id])) {
                continue;
            }
            $profilesByOrderId[$order->id] = $this->resolveCustomerProfile($order);
        }

        $groups = $receipts
            ->groupBy(fn (OrderPaymentReceipt $receipt) => $this->orderGroupKey($receipt))
            ->map(fn (Collection $items) => $this->serializeCustomerGroup($items, $profilesByOrderId, $canApprove))
            ->values();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $groups = $groups->filter(function (array $group) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $group['customer']['name'] ?? '',
                    $group['customer']['phone'] ?? '',
                    $group['customer']['phone_secondary'] ?? '',
                    $group['customer']['email'] ?? '',
                    ...collect($group['orders'])->pluck('order_number')->all(),
                    ...collect($group['orders'])->pluck('customer_name')->all(),
                    ...collect($group['orders'])->flatMap(
                        fn (array $order) => collect($order['receipts'])->pluck('receipt_number')
                    )->all(),
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        $allowedStatuses = [
            'all',
            OrderPaymentReceipt::STATUS_PENDING,
            OrderPaymentReceipt::STATUS_APPROVED,
            OrderPaymentReceipt::STATUS_REJECTED,
        ];

        if (in_array($status, [
            OrderPaymentReceipt::STATUS_PENDING,
            OrderPaymentReceipt::STATUS_APPROVED,
            OrderPaymentReceipt::STATUS_REJECTED,
        ], true)) {
            $groups = $groups->filter(function (array $group) use ($status) {
                return collect($group['orders'])->contains(function (array $order) use ($status) {
                    return collect($order['receipts'])->contains('approval_status', $status);
                });
            })->values();
        }

        $groups = $groups->sortByDesc('latest_receipt_id')->values();
        $total = $groups->count();
        $pageItems = $groups->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        $statusCounts = [
            'all' => OrderPaymentReceipt::query()->count(),
            'pending' => OrderPaymentReceipt::query()->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)->count(),
            'approved' => OrderPaymentReceipt::query()->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)->count(),
            'rejected' => OrderPaymentReceipt::query()->where('approval_status', OrderPaymentReceipt::STATUS_REJECTED)->count(),
        ];

        return Inertia::render('PaymentReceipts/Index', [
            'groups' => $paginator,
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
     * One table row per order so a customer's second order never merges
     * into the first order's receipt.
     */
    private function orderGroupKey(OrderPaymentReceipt $receipt): string
    {
        return 'order:'.($receipt->order_id ?: 0);
    }

    /**
     * @param  Collection<int, OrderPaymentReceipt>  $receipts
     * @param  array<int, array<string, mixed>>  $profilesByOrderId
     * @return array<string, mixed>
     */
    private function serializeCustomerGroup(Collection $receipts, array $profilesByOrderId, bool $canApprove): array
    {
        $orders = $receipts
            ->groupBy(fn (OrderPaymentReceipt $receipt) => $receipt->order_id ?: 0)
            ->map(function (Collection $orderReceipts) use ($canApprove) {
                /** @var OrderPaymentReceipt $first */
                $first = $orderReceipts->first();
                $order = $first->order;
                $sorted = $orderReceipts->sortBy('id')->values();

                return [
                    'id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'customer_name' => $order?->customer_name,
                    'currency' => $order?->currency ?: 'SAR',
                    'notes' => $order?->notes,
                    'total_amount' => round((float) ($order?->total_amount ?? $first->total_amount ?? 0), 2),
                    'amount_paid' => round((float) ($order?->amount_paid ?? 0), 2),
                    'remaining_amount' => round((float) ($order?->remaining_amount ?? 0), 2),
                    'receipts' => $sorted
                        ->map(fn (OrderPaymentReceipt $receipt) => $this->serializeReceipt($receipt, $canApprove))
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $order) => collect($order['receipts'])->max('id'))
            ->values();

        $profiles = $receipts
            ->map(fn (OrderPaymentReceipt $receipt) => $profilesByOrderId[$receipt->order_id] ?? null)
            ->filter()
            ->values();

        $customer = $this->mergeCustomerProfiles($profiles);
        $customerName = $orders
            ->pluck('customer_name')
            ->filter(fn ($name) => filled(trim((string) $name)))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
        $customer['name'] = $customerName ?: ($customer['name'] ?? null);

        $allReceipts = $orders->flatMap(fn (array $order) => $order['receipts']);
        $pendingReceipts = $allReceipts->where('approval_status', OrderPaymentReceipt::STATUS_PENDING);
        $recordedReceipts = $allReceipts->where('approval_status', '!=', OrderPaymentReceipt::STATUS_REJECTED);
        $currency = $orders->pluck('currency')->filter()->first() ?: 'SAR';
        $totalAmount = round((float) $orders->sum('total_amount'), 2);
        $recordedPaid = round((float) $recordedReceipts->sum('amount'), 2);

        return [
            'key' => $receipts->first()
                ? $this->orderGroupKey($receipts->first())
                : 'order:0',
            'customer' => $customer,
            'customer_name' => $customer['name'],
            'orders_count' => $orders->count(),
            'receipts_count' => $allReceipts->count(),
            'pending_count' => $pendingReceipts->count(),
            'approved_count' => $allReceipts->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)->count(),
            'rejected_count' => $allReceipts->where('approval_status', OrderPaymentReceipt::STATUS_REJECTED)->count(),
            'pending_amount' => round((float) $pendingReceipts->sum('amount'), 2),
            'amount_paid' => $recordedPaid,
            'total_amount' => $totalAmount,
            'remaining_amount' => round(max(0, $totalAmount - $recordedPaid), 2),
            'currency' => $currency,
            'latest_receipt_id' => (int) $receipts->max('id'),
            'latest_at' => optional($receipts->sortByDesc('id')->first()?->created_at)?->toIso8601String(),
            'has_notes' => $orders->contains(fn (array $order) => filled(trim((string) ($order['notes'] ?? '')))),
            'orders' => $orders->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReceipt(OrderPaymentReceipt $receipt, bool $canApprove): array
    {
        $order = $receipt->order;
        $total = round((float) ($receipt->total_amount ?: $order?->total_amount ?: 0), 2);
        $isApproved = $receipt->isApproved();
        $isPending = $receipt->isPending();
        $isRejected = $receipt->isRejected();
        $remaining = round((float) ($receipt->remaining_after ?? $order?->remaining_amount ?? 0), 2);
        if ($isRejected && $order) {
            $remaining = round((float) ($order->remaining_amount ?? 0), 2);
        }

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
                'notes' => $order->notes,
            ] : null,
            'recorded_by_name' => $receipt->recordedBy?->customer_name,
            'notes' => $receipt->notes,
            'proof_image_url' => $receipt->proof_image_url,
            'proof_image_urls' => $receipt->proof_image_urls,
            'account_number' => $receipt->account_number,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $profiles
     * @return array<string, mixed>
     */
    private function mergeCustomerProfiles(Collection $profiles): array
    {
        $preferred = $profiles->firstWhere('source', 'company_client')
            ?? $profiles->firstWhere('source', 'order')
            ?? $profiles->firstWhere('source', 'user')
            ?? $profiles->first()
            ?? [
        'name' => null,
        'phone' => null,
        'phone_secondary' => null,
        'email' => null,
        'address' => null,
        'iban' => null,
        'iban_image_url' => null,
        'tax_number' => null,
        'source' => 'order',
        'type' => null,
            ];

        foreach ($profiles as $profile) {
            foreach (['name', 'phone', 'phone_secondary', 'email', 'address', 'iban', 'iban_image_url', 'tax_number', 'type'] as $field) {
                if (empty($preferred[$field]) && ! empty($profile[$field])) {
                    $preferred[$field] = $profile[$field];
                }
            }
        }

        return $preferred;
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
     *     tax_number: string|null,
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
            'tax_number' => null,
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
                    'tax_number' => $companyClient->tax_number,
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
                'tax_number' => null,
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
