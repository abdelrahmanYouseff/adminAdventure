<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Older online-payment paths marked the order paid without filling
        // amount_paid. Preserve those confirmed payments before applying the
        // new final-invoice invariant.
        DB::table('orders')
            ->where('status', 'paid')
            ->where('payment_status', 'paid')
            ->where(function ($query) {
                $query->whereNull('amount_paid')
                    ->orWhereColumn('amount_paid', '<', 'total_amount');
            })
            ->update([
                'amount_paid' => DB::raw('total_amount'),
                'updated_at' => now(),
            ]);

        DB::table('orders')
            ->whereNotNull('invoice_id')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereColumn('amount_paid', '>=', 'total_amount')
            ->orderBy('id')
            ->chunkById(200, function ($orders) {
                $orderIds = $orders->pluck('id')->all();
                $invoiceIds = $orders->pluck('invoice_id')->filter()->all();

                DB::table('orders')
                    ->whereIn('id', $orderIds)
                    ->update([
                        'status' => 'paid',
                        'payment_status' => 'paid',
                        'updated_at' => now(),
                    ]);

                DB::table('invoices')
                    ->whereIn('id', $invoiceIds)
                    ->update([
                        'status' => 'paid',
                        'due_date' => null,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Historical payment and invoice finalization must not be reversed.
    }
};
