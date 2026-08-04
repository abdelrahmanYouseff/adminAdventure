<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Support\OrderInsuranceCalculator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuotationToOrderService
{
    /**
     * Ensure a linked order exists for this quotation (without recording a receipt).
     */
    public function ensureOrder(Quotation $quotation, ?User $actor = null): Order
    {
        $quotation->loadMissing('items');

        if ($quotation->items->isEmpty()) {
            throw new RuntimeException('لا يمكن إنشاء طلب من عرض سعر بدون بنود.');
        }

        return DB::transaction(function () use ($quotation, $actor) {
            $order = Order::query()
                ->where('quotation_id', $quotation->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return $this->createOrderFromQuotation($quotation, $actor);
            }

            $this->refreshOrderTotalsFromQuotation($order, $quotation);

            return $order->fresh();
        });
    }

    /**
     * Apply a successful Noon online payment to a quotation-linked order:
     * approved receipt, balances, work-order sync, and quotation status.
     */
    public function applyNoonPayment(Order $order, float $amount, ?string $noonOrderId = null): void
    {
        if (! $order->quotation_id) {
            return;
        }

        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $amount, $noonOrderId) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($noonOrderId) {
                $already = $locked->paymentReceipts()
                    ->where('notes', 'like', '%'.$noonOrderId.'%')
                    ->exists();
                if ($already) {
                    return;
                }
            }

            $service = app(OrderPaymentReceiptService::class);

            try {
                $receipt = $service->recordPayment(
                    $locked,
                    $amount,
                    null,
                    'noon',
                    'payment',
                    'دفع إلكتروني عبر Noon'.($noonOrderId ? ' ('.$noonOrderId.')' : ''),
                );
            } catch (RuntimeException $e) {
                // Nothing left to allocate (e.g. already covered) — still refresh statuses.
                $locked = $locked->fresh();
                $this->refreshOrderPaymentStatus($locked);
                $this->syncQuotationPaidFromOrder($locked);

                return;
            }

            $service->approveReceipt($receipt, null);

            $locked = $locked->fresh();
            $locked->payment_method = 'noon';
            if ($noonOrderId) {
                $locked->payment_id = $noonOrderId;
            }
            $this->refreshOrderPaymentStatus($locked);
            $locked->save();

            $this->syncQuotationPaidFromOrder($locked);
            $this->markQuotationAcceptedFromOrder($locked);

            app(WorkerOrderSyncService::class)->syncFromOrder($locked->fresh());
        });
    }

    private function refreshOrderPaymentStatus(Order $order): void
    {
        $total = round((float) $order->total_amount, 2);
        $paid = round((float) ($order->amount_paid ?? 0), 2);
        $remaining = round(max(0, $total - $paid), 2);

        if ($remaining <= 0.009) {
            $order->status = 'paid';
            $order->payment_status = 'paid';
        } elseif ($paid > 0) {
            if ($order->status === 'pending') {
                $order->status = 'processing';
            }
            if ($order->payment_status !== 'paid') {
                $order->payment_status = 'pending';
            }
        }
    }

    private function syncQuotationPaidFromOrder(Order $order): void
    {
        if (! $order->quotation_id) {
            return;
        }

        $quotation = Quotation::query()->lockForUpdate()->find($order->quotation_id);
        if (! $quotation) {
            return;
        }

        $approved = round((float) ($order->amount_paid ?? 0), 2);
        $pending = round((float) $order->paymentReceipts()
            ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
            ->sum('amount'), 2);
        $committed = round($approved + $pending, 2);

        $quotation->amount_paid = min(round((float) $quotation->total_amount, 2), $committed);
        $quotation->save();
    }

    /**
     * When a quotation has amount_paid > 0, ensure a linked order exists and
     * a pending payment receipt covers the unpaid-but-recorded amount.
     * Work orders are released only after accountant approval of the receipt.
     *
     * @return array{order: Order|null, receipt: OrderPaymentReceipt|null, created_order: bool, created_receipt: bool}
     */
    public function syncPaymentFromQuotation(Quotation $quotation, ?User $actor = null): array
    {
        $quotation->loadMissing('items');

        $targetPaid = round((float) ($quotation->amount_paid ?? 0), 2);

        if ($targetPaid <= 0) {
            return [
                'order' => $quotation->order,
                'receipt' => null,
                'created_order' => false,
                'created_receipt' => false,
            ];
        }

        if ($quotation->items->isEmpty()) {
            throw new RuntimeException('لا يمكن إنشاء سند قبض لعرض سعر بدون بنود.');
        }

        return DB::transaction(function () use ($quotation, $targetPaid, $actor) {
            $createdOrder = false;
            $order = Order::query()
                ->where('quotation_id', $quotation->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                $order = $this->createOrderFromQuotation($quotation, $actor);
                $createdOrder = true;
            } else {
                $this->refreshOrderTotalsFromQuotation($order, $quotation);
            }

            $approvedPaid = round((float) ($order->amount_paid ?? 0), 2);
            $pendingSum = round((float) $order->paymentReceipts()
                ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
                ->sum('amount'), 2);
            $committed = round($approvedPaid + $pendingSum, 2);
            $delta = round($targetPaid - $committed, 2);

            $receipt = null;
            $createdReceipt = false;

            if ($delta > 0.009) {
                $receipt = app(OrderPaymentReceiptService::class)->recordPayment(
                    $order->fresh(),
                    $delta,
                    $actor,
                    $order->payment_method ?: 'bank_transfer',
                    $createdOrder ? 'initial' : 'payment',
                    'سند قبض من عرض السعر '.$quotation->quotation_number.' — بانتظار اعتماد المحاسب',
                );
                $createdReceipt = true;
            }

            return [
                'order' => $order->fresh(),
                'receipt' => $receipt,
                'created_order' => $createdOrder,
                'created_receipt' => $createdReceipt,
            ];
        });
    }

    /**
     * Mark linked quotation as accepted after the first approved payment.
     */
    public function markQuotationAcceptedFromOrder(Order $order): void
    {
        if (! $order->quotation_id) {
            return;
        }

        Quotation::query()
            ->whereKey($order->quotation_id)
            ->whereIn('status', ['draft', 'sent'])
            ->update(['status' => 'accepted']);
    }

    private function createOrderFromQuotation(Quotation $quotation, ?User $actor): Order
    {
        $built = $this->buildOrderPayload($quotation);
        $userId = $actor?->id ?? $quotation->user_id ?? auth()->id() ?? 1;

        $invoice = Invoice::create([
            'brand_id' => $quotation->brand_id ?: Product::resolveBrandIdForIds($built['product_ids']),
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'amount' => $built['total_amount'],
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'issued_at' => now(),
            'due_date' => now()->addDays(30),
            'user_id' => $userId,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'customer_name' => $quotation->customer_name,
            'customer_email' => $quotation->customer_email,
            'customer_phone' => $quotation->customer_phone,
            'address' => $quotation->customer_address,
            'activity_date' => $built['activity_date'],
            'activity_time' => $built['activity_time'],
            'invoice_id' => $invoice->id,
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => $built['total_amount'],
            'discount_total' => $built['discount_total'],
            'amount_paid' => 0,
            'insurance_amount' => $built['insurance_amount'],
            'insurance_status' => $built['insurance_amount'] > 0 ? 'pending' : 'none',
            'currency' => 'SAR',
            'payment_method' => 'bank_transfer',
            'status' => 'processing',
            'payment_status' => 'pending',
            'items' => $built['items'],
            'notes' => trim(($quotation->notes ? $quotation->notes."\n" : '').'محوّل من عرض السعر '.$quotation->quotation_number),
            'user_id' => $userId,
        ]);

        foreach ($built['attach'] as $productId => $pivot) {
            $order->products()->attach($productId, $pivot);
        }

        return $order;
    }

    private function refreshOrderTotalsFromQuotation(Order $order, Quotation $quotation): void
    {
        // Do not rewrite totals after any approval — balance is driven by receipts.
        if ($order->hasApprovedPaymentReceipt()) {
            return;
        }

        $built = $this->buildOrderPayload($quotation);

        $order->fill([
            'customer_name' => $quotation->customer_name,
            'customer_email' => $quotation->customer_email,
            'customer_phone' => $quotation->customer_phone,
            'address' => $quotation->customer_address,
            'activity_date' => $built['activity_date'],
            'activity_time' => $built['activity_time'],
            'total_amount' => $built['total_amount'],
            'discount_total' => $built['discount_total'],
            'insurance_amount' => $built['insurance_amount'],
            'insurance_status' => $built['insurance_amount'] > 0 ? ($order->insurance_status ?: 'pending') : 'none',
            'items' => $built['items'],
        ]);
        $order->save();

        if ($order->invoice_id) {
            Invoice::query()->whereKey($order->invoice_id)->update([
                'amount' => $built['total_amount'],
                'brand_id' => $quotation->brand_id ?: Product::resolveBrandIdForIds($built['product_ids']),
            ]);
        }

        $order->products()->detach();
        foreach ($built['attach'] as $productId => $pivot) {
            $order->products()->attach($productId, $pivot);
        }
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     attach: array<int, array{quantity: int, price: float, discount_amount: float, insurance_amount: float}>,
     *     product_ids: list<int>,
     *     total_amount: float,
     *     discount_total: float,
     *     insurance_amount: float,
     *     activity_date: string|null,
     *     activity_time: string|null
     * }
     */
    private function buildOrderPayload(Quotation $quotation): array
    {
        $quotation->loadMissing('items');

        $lines = [];
        foreach ($quotation->items as $item) {
            if (! $item->product_id) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) ($item->discount_amount ?? 0),
            ];
        }

        $insurance = OrderInsuranceCalculator::fromLines($lines);
        $insuranceAmount = round((float) ($quotation->insurance_amount ?? $insurance['total']), 2);

        $itemsForOrder = [];
        $attach = [];
        $productIds = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;

        foreach ($quotation->items as $item) {
            $productId = $item->product_id ? (int) $item->product_id : null;
            $qty = (int) $item->quantity;
            $price = round((float) $item->unit_price, 2);
            $discount = round((float) ($item->discount_amount ?? 0), 2);
            $lineTotal = round((float) $item->total_price, 2);
            $subtotal += $lineTotal;
            $discountTotal += round($qty * $discount, 2);
            if ($productId) {
                $productIds[] = $productId;
            }

            $itemsForOrder[] = [
                'product_id' => $productId,
                'name' => $item->product_name,
                'description' => $item->description,
                'statement' => $item->statement,
                'quantity' => $qty,
                'price' => $price,
                'discount_amount' => $discount,
                'amount' => $lineTotal,
                'insurance_amount' => $productId
                    ? (float) ($insurance['unit_by_product'][$productId] ?? 0)
                    : 0.0,
            ];

            if ($productId) {
                $attach[$productId] = [
                    'quantity' => $qty,
                    'price' => $price,
                    'discount_amount' => $discount,
                    'insurance_amount' => (float) ($insurance['unit_by_product'][$productId] ?? 0),
                ];
            }
        }

        // Keep order total aligned with quotation total (includes VAT + insurance)
        // so the amount_paid entered on the quotation matches receipt available balance.
        $totalAmount = round((float) $quotation->total_amount, 2);
        if ($totalAmount <= 0) {
            $totalAmount = round($subtotal + $insuranceAmount, 2);
        }

        $activityDate = null;
        $activityTime = null;
        if ($quotation->activity_at) {
            $activityDate = $quotation->activity_at->toDateString();
            $activityTime = $quotation->activity_at->format('H:i');
        }

        return [
            'items' => $itemsForOrder,
            'attach' => $attach,
            'product_ids' => array_values(array_unique($productIds)),
            'total_amount' => $totalAmount,
            'discount_total' => round((float) ($quotation->discount_total ?? $discountTotal), 2),
            'insurance_amount' => $insuranceAmount,
            'activity_date' => $activityDate,
            'activity_time' => $activityTime,
        ];
    }
}
