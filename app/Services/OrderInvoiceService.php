<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;

class OrderInvoiceService
{
    /**
     * Issue (or mark) a paid invoice only when the order balance is fully settled.
     */
    public function issueIfFullyPaid(Order $order): ?Invoice
    {
        $total = round((float) $order->total_amount, 2);
        $paid = round((float) ($order->amount_paid ?? 0), 2);

        if ($total <= 0 || $paid + 0.009 < $total) {
            return null;
        }

        if ($order->invoice_id) {
            $invoice = Invoice::query()->find($order->invoice_id);
            if ($invoice) {
                $invoice->fill([
                    'amount' => $total,
                    'status' => 'paid',
                    'payment_method' => $order->payment_method,
                    'issued_at' => $invoice->issued_at ?? now(),
                    'due_date' => null,
                ]);
                $invoice->save();

                return $invoice;
            }
        }

        $invoice = Invoice::create([
            'brand_id' => $order->resolveBrandId() ?: (int) Product::resolveBrandIdForIds([]),
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'amount' => $total,
            'status' => 'paid',
            'payment_method' => $order->payment_method,
            'issued_at' => now(),
            'due_date' => null,
            'user_id' => $order->user_id,
        ]);

        $order->invoice_id = $invoice->id;
        $order->save();

        return $invoice;
    }
}
