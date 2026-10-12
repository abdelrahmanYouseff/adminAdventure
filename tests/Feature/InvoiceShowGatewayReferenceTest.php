<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceShowGatewayReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_show_includes_gateway_payment_id(): void
    {
        $this->withoutVite();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $invoice = Invoice::query()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'S-202610100',
            'amount' => 500,
            'status' => 'paid',
            'payment_method' => 'noon',
            'issued_at' => now(),
        ]);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '0500000000',
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => 500,
            'amount_paid' => 500,
            'currency' => 'SAR',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'noon',
            'payment_id' => 'noon-ref-abc-123',
        ]);

        OrderPaymentReceipt::query()->create([
            'order_id' => $order->id,
            'recorded_by' => $admin->id,
            'receipt_number' => OrderPaymentReceipt::generateReceiptNumber(),
            'amount' => 500,
            'total_amount' => 500,
            'amount_paid_before' => 0,
            'amount_paid_after' => 500,
            'remaining_after' => 0,
            'payment_method' => 'noon',
            'approval_status' => OrderPaymentReceipt::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invoices/Show')
                ->where('invoice.status', 'paid')
                ->where('invoice.order.payment_id', 'noon-ref-abc-123')
                ->where('invoice.order.id', $order->id)
            );
    }
}
