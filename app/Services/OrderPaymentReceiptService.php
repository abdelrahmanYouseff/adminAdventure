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
     * Record a payment as a PENDING receipt. It does not touch the order's
     * approved balance or status until an accountant approves it.
     */
    public function recordPayment(
        Order $order,
        float $amount,
        ?User $user = null,
        ?string $paymentMethod = null,
        string $type = 'payment',
        ?string $notes = null,
        ?string $proofImage = null,
        ?string $accountNumber = null,
    ): OrderPaymentReceipt {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            throw new RuntimeException('مبلغ السداد يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($order, $amount, $user, $paymentMethod, $type, $notes, $proofImage, $accountNumber) {
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
                throw new RuntimeException('مبلغ السداد أكبر من المتبقي غير المخصّص على العميل.');
            }

            // Provisional snapshot relative to the approved balance; recomputed on approval.
            $projectedRemaining = round(max(0, $total - ($committed + $amount)), 2);

            if ($paymentMethod) {
                $locked->payment_method = $paymentMethod;
                $locked->save();
            }

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
                'proof_image' => $proofImage,
                'account_number' => $accountNumber,
            ]);
        });
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

            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($lockedReceipt->order_id);

            $hadApprovedBefore = $locked->paymentReceipts()
                ->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)
                ->exists();

            $total = round((float) $locked->total_amount, 2);
            $paidBefore = round((float) ($locked->amount_paid ?? 0), 2);
            $amount = round((float) $lockedReceipt->amount, 2);
            $paidAfter = round(min($total, $paidBefore + $amount), 2);
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
}
