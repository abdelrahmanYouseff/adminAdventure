<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Support\InvoicePdfData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaidOrderEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_edit_a_paid_closed_order_and_the_invoice_name_follows(): void
    {
        $this->withoutVite();

        $manager = User::factory()->staff(User::ROLE_MANAGER)->create();
        $order = $this->makeClosedPaidOrder('شركة الفنار');

        $this->actingAs($manager)
            ->get(route('orders.edit', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Edit')
                ->where('order.customer_name', 'شركة الفنار'));

        $this->actingAs($manager)
            ->put(route('orders.update', $order), [
                'customer_name' => 'شركة الفنار المحدودة',
                'customer_email' => '',
                'customer_phone' => '545544844',
                'address' => '',
                'currency' => 'SAR',
                'payment_method' => 'cash',
                'status' => 'paid',
                'notes' => '',
                'items' => [[
                    'product_name' => 'صنف',
                    'quantity' => 1,
                    'unit_price' => 500,
                    'discount_amount' => 0,
                ]],
            ])
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('شركة الفنار المحدودة', $order->customer_name);

        $invoice = Invoice::query()->findOrFail($order->invoice_id);
        $this->assertSame('شركة الفنار المحدودة', InvoicePdfData::fromInvoice($invoice)->customerName());
    }

    public function test_paid_closed_order_still_cannot_be_deleted(): void
    {
        $this->withoutVite();

        $manager = User::factory()->staff(User::ROLE_MANAGER)->create();
        $order = $this->makeClosedPaidOrder('شركة الفنار');

        $this->actingAs($manager)
            ->delete(route('orders.destroy', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertNotNull(Order::query()->find($order->id));
    }

    private function makeClosedPaidOrder(string $name): Order
    {
        $owner = User::factory()->create();

        $invoice = Invoice::query()->create([
            'user_id' => $owner->id,
            'invoice_number' => 'S-'.fake()->unique()->numerify('202610###'),
            'amount' => 500,
            'status' => 'paid',
            'payment_method' => 'cash',
            'issued_at' => now(),
        ]);

        return Order::query()->create([
            'user_id' => $owner->id,
            'invoice_id' => $invoice->id,
            'customer_name' => $name,
            'customer_phone' => '545544844',
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => 500,
            'amount_paid' => 500,
            'currency' => 'SAR',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'warehouse_returned_at' => now(),
            'warehouse_keeper_approved_at' => now(),
        ]);
    }
}
