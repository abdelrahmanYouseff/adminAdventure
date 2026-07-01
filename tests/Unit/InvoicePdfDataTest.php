<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Order;
use App\Support\InvoicePdfData;
use Tests\TestCase;

class InvoicePdfDataTest extends TestCase
{
    public function test_builds_arabic_labels_and_order_line_items(): void
    {
        $invoice = new Invoice([
            'invoice_number' => 'ORD-202607-0002',
            'amount' => 1150.00,
            'status' => 'paid',
            'payment_method' => 'noon',
            'created_at' => now(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7),
        ]);

        $order = new Order([
            'customer_name' => 'أحمد محمد',
            'customer_email' => 'ahmed@example.com',
            'customer_phone' => '0512345678',
            'activity_date' => '2026-07-10',
            'address' => '24.7136,46.6753',
            'items' => [
                [
                    'name' => 'لعبة مسار البولينج',
                    'quantity' => 1,
                    'duration' => 1,
                    'price' => 1000,
                    'amount' => 1000,
                ],
            ],
            'notes' => 'طلب من المتجر',
        ]);

        $data = new InvoicePdfData($invoice, $order);

        $this->assertSame('مدفوعة', $data->statusLabel());
        $this->assertSame('نون باي', $data->paymentMethodLabel());
        $this->assertSame('أحمد محمد', $data->customerName());
        $this->assertCount(1, $data->lineItems());
        $this->assertSame('لعبة مسار البولينج', $data->lineItems()[0]['name']);
        $this->assertSame(1000.0, $data->subtotal());
        $this->assertSame(150.0, $data->vatAmount());
        $this->assertSame(1150.0, $data->total());
    }
}
