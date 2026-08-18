<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderPaymentReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentReceiptAccumulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_extra_payment_on_the_same_order_updates_the_same_receipt(): void
    {
        $staff = User::factory()->admin()->create();
        $order = $this->makeOrder($staff, 'شركة الاختبار', 6000);

        $service = app(OrderPaymentReceiptService::class);

        $first = $service->recordPayment($order, 2000, $staff, 'bank_transfer', 'initial');
        $this->assertEquals(2000.0, (float) $first->amount);

        $second = $service->recordPayment($order->fresh(), 1500, $staff, 'bank_transfer', 'settlement');

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(3500.0, (float) $second->amount);
        $this->assertEquals(6000.0, (float) $second->total_amount);
        $this->assertTrue($second->isPending());
        $this->assertSame(1, $order->paymentReceipts()->count());
    }

    public function test_new_order_for_the_same_customer_gets_a_separate_receipt(): void
    {
        $staff = User::factory()->admin()->create();
        $firstOrder = $this->makeOrder($staff, 'شركة الاختبار', 6000, '0500000001');
        $secondOrder = $this->makeOrder($staff, 'شركة الاختبار', 4000, '0500000001');

        $service = app(OrderPaymentReceiptService::class);

        $firstReceipt = $service->recordPayment($firstOrder, 2000, $staff, 'cash', 'initial');
        $secondReceipt = $service->recordPayment($secondOrder, 1000, $staff, 'cash', 'initial');

        $this->assertNotSame($firstReceipt->id, $secondReceipt->id);
        $this->assertSame($firstOrder->id, $firstReceipt->order_id);
        $this->assertSame($secondOrder->id, $secondReceipt->order_id);
        $this->assertEquals(2000.0, (float) $firstReceipt->amount);
        $this->assertEquals(1000.0, (float) $secondReceipt->amount);
    }

    public function test_additional_payment_after_approval_stays_on_the_same_receipt(): void
    {
        $staff = User::factory()->admin()->create();
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create();
        $order = $this->makeOrder($staff, 'شركة الاختبار', 6000);

        $service = app(OrderPaymentReceiptService::class);

        $receipt = $service->recordPayment($order, 2000, $staff, 'bank_transfer', 'initial');
        $service->approveReceipt($receipt, $accounts);

        $updated = $service->recordPayment($order->fresh(), 2000, $staff, 'bank_transfer', 'settlement');

        $this->assertSame($receipt->id, $updated->id);
        $this->assertEquals(4000.0, (float) $updated->amount);
        $this->assertTrue($updated->isPending());
        $this->assertEquals(2000.0, (float) $order->fresh()->amount_paid);

        $service->approveReceipt($updated, $accounts);

        $this->assertEquals(4000.0, (float) $order->fresh()->amount_paid);
        $this->assertSame(1, $order->paymentReceipts()->count());
    }

    public function test_payment_receipts_index_lists_each_order_as_its_own_row(): void
    {
        $admin = User::factory()->admin()->create();
        $firstOrder = $this->makeOrder($admin, 'شركة الاختبار', 6000, '0500000001');
        $secondOrder = $this->makeOrder($admin, 'شركة الاختبار', 4000, '0500000001');

        $service = app(OrderPaymentReceiptService::class);
        $service->recordPayment($firstOrder, 2000, $admin, 'cash', 'initial');
        $service->recordPayment($secondOrder, 1000, $admin, 'cash', 'initial');

        $this->actingAs($admin)
            ->get(route('payment-receipts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PaymentReceipts/Index')
                ->has('groups.data', 2)
                ->where('groups.data.0.orders_count', 1)
                ->where('groups.data.1.orders_count', 1)
            );
    }

    private function makeOrder(User $user, string $customerName, float $total, string $phone = '0500000000'): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => $customerName,
            'customer_phone' => $phone,
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => $total,
            'amount_paid' => 0,
            'currency' => 'SAR',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'bank_transfer',
        ]);
    }
}
