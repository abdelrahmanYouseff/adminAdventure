<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderWhatsAppMessage;
use Tests\TestCase;

class OrderWhatsAppMessageTest extends TestCase
{
    public function test_message_builder_includes_order_details_without_prices(): void
    {
        $order = new Order([
            'order_number' => 'ORD-202606-0001',
            'customer_name' => 'أحمد محمد',
            'customer_phone' => '0512345678',
            'address' => '24.7136,46.6753',
            'activity_date' => '2026-06-15',
            'total_amount' => 1699.70,
            'currency' => 'SAR',
            'items' => [
                [
                    'name' => 'ترامبولين',
                    'quantity' => 1,
                    'duration' => 3,
                    'amount' => 1478.00,
                ],
            ],
        ]);

        $message = OrderWhatsAppMessage::build($order);

        $this->assertStringContainsString('ORD-202606-0001', $message);
        $this->assertStringContainsString('أحمد محمد', $message);
        $this->assertStringContainsString('0512345678', $message);
        $this->assertStringContainsString('2026-06-15', $message);
        $this->assertStringContainsString('/order/ORD-202606-0001/location', $message);
        $this->assertStringContainsString('ترامبولين', $message);
        $this->assertStringNotContainsString('ريال', $message);
        $this->assertStringNotContainsString('1,699', $message);
        $this->assertStringNotContainsString('google.com/maps', $message);
    }

    public function test_location_maps_url_for_text_address(): void
    {
        $url = OrderWhatsAppMessage::locationMapsUrl('الرياض، السعودية');

        $this->assertStringContainsString('google.com/maps/search', $url);
        $this->assertStringContainsString('query=', $url);
    }
}
