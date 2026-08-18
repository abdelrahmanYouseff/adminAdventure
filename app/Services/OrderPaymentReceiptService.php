<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

class OrderPaymentReceiptService
{
    /**
     * Record a payment as a PENDING receipt. Extra payments on the same order
     * update that order's existing voucher instead of opening a new one.
     * A new order always gets its own receipt, even for the same customer.
     */
    public function recordPayment(
        Order $order,
        float $amount,
        ?User $user = null,
        ?string $paymentMethod = null,
        string $type = 'payment',
        ?string $notes = null,
        ?array $proofImages = null,
        ?string $accountNumber = null,
    ): OrderPaymentReceipt {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            throw new RuntimeException('مبلغ السداد يجب أن يكون أكبر من صفر.');
        }

        $proofImages = array_values(array_filter(
            $proofImages ?? [],
            fn ($path) => is_string($path) && trim($path) !== '',
        ));

        return DB::transaction(function () use ($order, $amount, $user, $paymentMethod, $type, $notes, $proofImages, $accountNumber) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $total = round((float) $locked->total_amount, 2);
            $approvedPaid = round((float) ($locked->amount_paid ?? 0), 2);
            $pendingSum = round((float) $locked->paymentReceipts()
                ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
                ->sum('amount'), 2);

            $committed = round($approvedPaid + $pendingSum, 2);
            $available = round(max(0, $total - $committed), 2);

            if ($amount > $available + 0.009) {
                throw new RuntimeException('مبلغ السداد أكبر من المتبقي غير المخصّص على هذا الطلب.');
            }

            if ($paymentMethod) {
                $locked->payment_method = $paymentMethod;
                $locked->save();
            }

            $existing = $this->receiptToAccumulate($locked);

            if ($existing) {
                return $this->accumulateOnReceipt(
                    $existing,
                    $locked,
                    $amount,
                    $approvedPaid,
                    $user,
                    $paymentMethod,
                    $notes,
                    $proofImages,
                    $accountNumber,
                );
            }

            $projectedRemaining = round(max(0, $total - ($committed + $amount)), 2);

            return OrderPaymentReceipt::create([
                'order_id' => $locked->id,
                'recorded_by' => $user?->id,
                'receipt_number' => OrderPaymentReceipt::generateReceiptNumber(),
                'amount' => $amount,
                'total_amount' => $total,
                'amount_paid_before' => $approvedPaid,
                'amount_paid_after' => round($approvedPaid + $amount, 2),
                'remaining_after' => $projectedRemaining,
                'payment_method' => $paymentMethod ?: $locked->payment_method,
                'type' => $type,
                'approval_status' => OrderPaymentReceipt::STATUS_PENDING,
                'notes' => $notes,
                'proof_image' => $proofImages !== [] ? $proofImages : null,
                'account_number' => $accountNumber,
            ]);
        });
    }

    /**
     * Append transfer/receipt images to an existing pending receipt.
     *
     * @param  list<string>  $paths
     */
    public function attachProofImages(OrderPaymentReceipt $receipt, array $paths): OrderPaymentReceipt
    {
        $paths = array_values(array_filter(
            $paths,
            fn ($path) => is_string($path) && trim($path) !== '',
        ));

        if ($paths === []) {
            return $receipt;
        }

        $existing = $receipt->getAttributes()['proof_image'] ?? null;
        $current = [];

        if (is_array($existing)) {
            $current = $existing;
        } elseif (is_string($existing) && trim($existing) !== '') {
            $decoded = json_decode($existing, true);
            $current = is_array($decoded) ? $decoded : [$existing];
        }

        $merged = array_values(array_unique(array_filter(array_map(
            fn ($path) => is_string($path) ? trim($path) : '',
            array_merge($current, $paths),
        ))));

        $receipt->update([
            'proof_image' => $merged !== [] ? array_slice($merged, 0, 10) : null,
        ]);

        return $receipt->refresh();
    }

    /**
     * Approve a pending receipt: apply its amount to the order's balance and
     * status. Idempotent — approving an already-approved receipt is a no-op.
     *
     * @return array{receipt: OrderPaymentReceipt, released_work_order: bool}
     */
    public function approveReceipt(OrderPaymentReceipt $receipt, ?User $approver = null): array
    {
        return DB::transaction(function () use ($receipt, $approver) {
            /** @var OrderPaymentReceipt $lockedReceipt */
            $lockedReceipt = OrderPaymentReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($lockedReceipt->isApproved()) {
                return ['receipt' => $lockedReceipt, 'released_work_order' => false];
            }

            if ($lockedReceipt->isRejected()) {
                throw new RuntimeException('لا يمكن اعتماد سند مرفوض.');
            }

            if (! $lockedReceipt->isPending()) {
                throw new RuntimeException('يمكن اعتماد السندات قيد الانتظار فقط.');
            }

            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($lockedReceipt->order_id);

            $hadApprovedBefore = $lockedReceipt->approved_at !== null
                || $locked->paymentReceipts()
                    ->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)
                    ->where('id', '!=', $lockedReceipt->id)
                    ->exists();

            $total = round((float) $locked->total_amount, 2);
            $paidBefore = round((float) ($locked->amount_paid ?? 0), 2);
            $amount = round((float) $lockedReceipt->amount, 2);
            $previouslyApplied = $lockedReceipt->approved_at !== null
                ? round((float) $lockedReceipt->amount_paid_after, 2)
                : 0.0;
            $delta = round(max(0, $amount - $previouslyApplied), 2);
            $paidAfter = round(min($total, $paidBefore + $delta), 2);
            $remainingAfter = round(max(0, $total - $paidAfter), 2);

            $lockedReceipt->fill([
                'amount_paid_before' => $paidBefore,
                'amount_paid_after' => $paidAfter,
                'remaining_after' => $remainingAfter,
                'approval_status' => OrderPaymentReceipt::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $approver?->id,
            ]);
            $lockedReceipt->save();

            $locked->amount_paid = $paidAfter;

            if ($remainingAfter <= 0.009) {
                $locked->status = 'paid';
                $locked->payment_status = 'paid';
            } elseif ($paidAfter > 0) {
                if ($locked->status === 'pending') {
                    $locked->status = 'processing';
                }
                if ($locked->payment_status !== 'paid') {
                    $locked->payment_status = 'pending';
                }
            }

            $locked->save();

            if ($remainingAfter <= 0.009) {
                app(OrderInvoiceService::class)->ensureFinalInvoice($locked);
            }

            return [
                'receipt' => $lockedReceipt,
                'released_work_order' => ! $hadApprovedBefore,
            ];
        });
    }

    /**
     * Reject a pending receipt without applying its amount to the order or
     * releasing a work order. The order balance stays unchanged.
     */
    public function rejectReceipt(
        OrderPaymentReceipt $receipt,
        string $reason,
        ?User $rejector = null,
    ): OrderPaymentReceipt {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('يجب كتابة سبب الرفض.');
        }

        return DB::transaction(function () use ($receipt, $reason, $rejector) {
            /** @var OrderPaymentReceipt $lockedReceipt */
            $lockedReceipt = OrderPaymentReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($lockedReceipt->isApproved()) {
                throw new RuntimeException('لا يمكن رفض سند معتمد مسبقاً.');
            }

            if ($lockedReceipt->isRejected()) {
                return $lockedReceipt;
            }

            if (! $lockedReceipt->isPending()) {
                throw new RuntimeException('يمكن رفض السندات قيد الانتظار فقط.');
            }

            $lockedReceipt->fill([
                'approval_status' => OrderPaymentReceipt::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_by' => $rejector?->id,
            ]);
            $lockedReceipt->save();

            return $lockedReceipt;
        });
    }

    /**
     * Legacy fallback used when a paid order has no receipt yet (predates the
     * receipt workflow). Creates an already-approved receipt reflecting the
     * order's existing paid balance.
     */
    public function ensureInitialReceipt(Order $order, ?User $user = null): ?OrderPaymentReceipt
    {
        $paid = round((float) ($order->amount_paid ?? 0), 2);
        if ($paid <= 0) {
            return null;
        }

        if ($order->paymentReceipts()->exists()) {
            return $order->paymentReceipts()->latest('id')->first();
        }

        $total = round((float) $order->total_amount, 2);

        return OrderPaymentReceipt::create([
            'order_id' => $order->id,
            'recorded_by' => $user?->id,
            'receipt_number' => OrderPaymentReceipt::generateReceiptNumber(),
            'amount' => $paid,
            'total_amount' => $total,
            'amount_paid_before' => 0,
            'amount_paid_after' => $paid,
            'remaining_after' => round(max(0, $total - $paid), 2),
            'payment_method' => $order->payment_method,
            'type' => 'initial',
            'approval_status' => OrderPaymentReceipt::STATUS_APPROVED,
            'approved_at' => now(),
            'notes' => 'سند قبض عند إنشاء الطلب',
        ]);
    }

    public function renderPdf(OrderPaymentReceipt $receipt): string
    {
        $receipt->loadMissing([
            'order.products',
            'recordedBy:id,customer_name',
        ]);

        $order = $receipt->order;
        $items = $this->orderItems($order);

        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'default_font' => 'dejavusans',
            'directionality' => 'rtl',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
        ]);

        $mpdf->SetTitle('سند قبض '.$receipt->receipt_number);

        $html = View::make('payment-receipt-pdf', [
            'receipt' => $receipt,
            'order' => $order,
            'items' => $items,
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @return list<array{name: string, quantity: int, price: float, discount: float, total: float}>
     */
    private function orderItems(Order $order): array
    {
        $rows = [];

        if (is_array($order->items) && $order->items !== []) {
            foreach ($order->items as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $discount = (float) ($item['discount_amount'] ?? 0);
                $total = isset($item['amount'])
                    ? (float) $item['amount']
                    : round($qty * ($price - $discount), 2);

                $rows[] = [
                    'name' => (string) ($item['name'] ?? $item['product_name'] ?? 'منتج'),
                    'quantity' => $qty,
                    'price' => $price,
                    'discount' => $discount,
                    'total' => $total,
                ];
            }

            return $rows;
        }

        foreach ($order->products as $product) {
            $qty = (int) ($product->pivot->quantity ?? 0);
            $price = (float) ($product->pivot->price ?? 0);
            $discount = (float) ($product->pivot->discount_amount ?? 0);

            $rows[] = [
                'name' => $product->product_name,
                'quantity' => $qty,
                'price' => $price,
                'discount' => $discount,
                'total' => round($qty * ($price - $discount), 2),
            ];
        }

        return $rows;
    }

    /**
     * One live voucher per order: pending first, otherwise the single approved
     * receipt so later payments on this order stay on the same record.
     */
    private function receiptToAccumulate(Order $order): ?OrderPaymentReceipt
    {
        $receipts = OrderPaymentReceipt::query()
            ->where('order_id', $order->id)
            ->whereIn('approval_status', [
                OrderPaymentReceipt::STATUS_PENDING,
                OrderPaymentReceipt::STATUS_APPROVED,
            ])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $pending = $receipts->first(
            fn (OrderPaymentReceipt $receipt) => $receipt->isPending(),
        );

        if ($pending) {
            return $pending;
        }

        if ($receipts->count() === 1) {
            return $receipts->first();
        }

        return null;
    }

    /**
     * @param  list<string>  $proofImages
     */
    private function accumulateOnReceipt(
        OrderPaymentReceipt $receipt,
        Order $order,
        float $increment,
        float $approvedPaid,
        ?User $user,
        ?string $paymentMethod,
        ?string $notes,
        array $proofImages,
        ?string $accountNumber,
    ): OrderPaymentReceipt {
        $total = round((float) $order->total_amount, 2);
        $currentAmount = round((float) $receipt->amount, 2);
        $newAmount = round($currentAmount + $increment, 2);
        $previouslyApplied = $receipt->approved_at !== null
            ? round((float) $receipt->amount_paid_after, 2)
            : 0.0;
        $projectedPaid = round(min($total, $approvedPaid + ($newAmount - $previouslyApplied)), 2);
        $projectedRemaining = round(max(0, $total - $projectedPaid), 2);

        $payload = [
            'amount' => $newAmount,
            'total_amount' => $total,
            'amount_paid_before' => $approvedPaid,
            'remaining_after' => $projectedRemaining,
            'approval_status' => OrderPaymentReceipt::STATUS_PENDING,
            'notes' => $this->mergeNotes($receipt->notes, $notes),
        ];

        if ($receipt->approved_at === null) {
            $payload['amount_paid_after'] = $projectedPaid;
        }

        if ($paymentMethod) {
            $payload['payment_method'] = $paymentMethod;
        }

        if ($accountNumber !== null && trim($accountNumber) !== '') {
            $payload['account_number'] = trim($accountNumber);
        }

        if ($user && ! $receipt->recorded_by) {
            $payload['recorded_by'] = $user->id;
        }

        $receipt->fill($payload);
        $receipt->save();

        if ($proofImages !== []) {
            return $this->attachProofImages($receipt, $proofImages);
        }

        return $receipt->refresh();
    }

    private function mergeNotes(?string $existing, ?string $incoming): ?string
    {
        $existing = trim((string) $existing);
        $incoming = trim((string) $incoming);

        if ($incoming === '') {
            return $existing !== '' ? $existing : null;
        }

        if ($existing === '' || $existing === $incoming) {
            return $incoming;
        }

        if (str_contains($existing, $incoming)) {
            return $existing;
        }

        return $existing."\n".$incoming;
    }
}
