<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderInvoiceService
{
    /**
     * Create or finalize the tax invoice only after the order is fully paid.
     */
    public function ensureFinalInvoice(Order $order): ?Invoice
    {
        return DB::transaction(function () use ($order) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $total = round((float) $locked->total_amount, 2);
            $paid = round((float) ($locked->amount_paid ?? 0), 2);

            if (
                $total <= 0
                || ($total - $paid) > 0.009
                || in_array($locked->status, ['cancelled', 'refunded'], true)
            ) {
                return null;
            }

            $invoice = $locked->invoice;

            if (! $invoice) {
                $invoice = Invoice::create([
                    'brand_id' => $locked->resolveBrandId(),
                    'user_id' => $locked->user_id,
                    'rental_id' => null,
                    'invoice_number' => Invoice::generateInvoiceNumber(),
                    'amount' => $total,
                    'status' => 'paid',
                    'payment_method' => $locked->payment_method,
                    'issued_at' => now(),
                    'due_date' => null,
                ]);

                $locked->forceFill(['invoice_id' => $invoice->id])->save();
                $order->setAttribute('invoice_id', $invoice->id);

                return $invoice;
            }

            $invoice->forceFill([
                'amount' => $total,
                'status' => 'paid',
                'payment_method' => $locked->payment_method,
                'issued_at' => $invoice->issued_at ?: now(),
                'due_date' => null,
            ])->save();

            return $invoice;
        });
    }
}
