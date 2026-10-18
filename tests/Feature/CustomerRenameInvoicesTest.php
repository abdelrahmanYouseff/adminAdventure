<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Support\InvoicePdfData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRenameInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchecked_rename_leaves_order_snapshots_unchanged(): void
    {
        $this->withoutVite();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create([
            'customer_name' => 'Old Name',
            'phone' => '0551111111',
            'email' => 'old@example.com',
        ]);

        $order = $this->makeClosedOrder($customer, '0551111111', 'Old Name');

        $this->actingAs($admin)
            ->post(route('customers.update', ['type' => 'individual', 'id' => $customer->id]), [
                'name' => 'New Name',
                'phone' => '0551111111',
                'phone_secondary' => '',
                'email' => 'old@example.com',
                'country' => '',
                'gender' => '',
                'date_of_birth' => '',
                'iban' => '',
                'update_previous_invoices' => false,
            ])
            ->assertRedirect(route('customers'));

        $this->assertSame('New Name', $customer->fresh()->customer_name);
        $this->assertSame('Old Name', $order->fresh()->customer_name);
    }

    public function test_checked_rename_updates_matched_order_snapshots_including_closed_orders(): void
    {
        $this->withoutVite();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create([
            'customer_name' => 'Old Name',
            'phone' => '0551111111',
            'email' => 'old@example.com',
        ]);
        $other = User::factory()->create([
            'customer_name' => 'Someone Else',
            'phone' => '0559999999',
            'email' => 'other@example.com',
        ]);

        $matched = $this->makeClosedOrder($admin, '0551111111', 'Old Name');
        $workerOrder = WorkerOrder::query()->create([
            'order_id' => $matched->id,
            'line_index' => 0,
            'product_name' => 'Tent',
            'customer_name' => 'Old Name',
        ]);
        $unmatched = $this->makeClosedOrder($other, '0559999999', 'Keep Me');

        $this->actingAs($admin)
            ->post(route('customers.update', ['type' => 'individual', 'id' => $customer->id]), [
                'name' => 'New Name',
                'phone' => '0552222222',
                'phone_secondary' => '',
                'email' => 'new@example.com',
                'country' => '',
                'gender' => '',
                'date_of_birth' => '',
                'iban' => '',
                'update_previous_invoices' => true,
            ])
            ->assertRedirect(route('customers'));

        $this->assertSame('New Name', $customer->fresh()->customer_name);
        $this->assertSame('New Name', $matched->fresh()->customer_name);
        $this->assertSame('New Name', $workerOrder->fresh()->customer_name);
        $this->assertSame('Keep Me', $unmatched->fresh()->customer_name);

        $invoice = Invoice::query()->findOrFail($matched->invoice_id);
        $this->assertSame('New Name', InvoicePdfData::fromInvoice($invoice)->customerName());
    }

    private function makeClosedOrder(User $owner, string $phone, string $name): Order
    {
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
            'customer_phone' => $phone,
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => 500,
            'amount_paid' => 500,
            'currency' => 'SAR',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'warehouse_returned_at' => now(),
        ]);
    }
}
