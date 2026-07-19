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
        $order = new Order([
            'customer_name' => 'أحمد محمد',
            'customer_email' => 'ahmed@example.com',
            'customer_phone' => '0512345678',
            'activity_date' => '2026-07-10',
            'address' => '24.7136,46.6753',
            'insurance_amount' => 200,
            'items' => [
                [
                    'name' => 'لعبة مسار البولينج',
                    'quantity' => 1,
                    'duration' => 1,
                    'price' => 1000,
                    'amount' => 1000,
                    'insurance_amount' => 200,
                ],
            ],
            'notes' => 'طلب من المتجر',
        ]);

        $invoice = new Invoice([
            'invoice_number' => 'ORD-202607-0002',
            'amount' => 1350.00, // 1000 + 150 VAT + 200 insurance
            'status' => 'paid',
            'payment_method' => 'noon',
            'created_at' => now(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7),
        ]);

        $data = new InvoicePdfData($invoice, $order);

        $this->assertSame('مدفوعة', $data->statusLabel());
        $this->assertSame('Paid', $data->statusLabelEn());
        $this->assertSame('نون باي', $data->paymentMethodLabel());
        $this->assertSame('Noon Pay', $data->paymentMethodLabelEn());
        $this->assertSame('أحمد محمد', $data->customerName());
        $this->assertCount(1, $data->lineItems());
        $this->assertSame('لعبة مسار البولينج', $data->lineItems()[0]['name']);
        $this->assertSame(1000.0, $data->subtotal());
        $this->assertSame(150.0, $data->vatAmount());
        $this->assertTrue($data->hasInsurance());
        $this->assertSame(200.0, $data->insuranceAmount());
        $this->assertSame(1350.0, $data->total());
        $this->assertCount(1, $data->lineItemRows());
        $this->assertSame(150.0, $data->lineItemRows()[0]['vat_amount']);
        $this->assertSame(1150.0, $data->lineItemRows()[0]['total']);
        $this->assertSame('SAR 1,350.00', $data->formatSar($data->total()));
        $this->assertStringContainsString('refundable', strtolower($data->insuranceNoteEn()));
    }
}
