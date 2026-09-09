<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\User;
use App\Services\OrderPaymentReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NoonReceiptsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_from_noon_receipts(): void
    {
        $this->get(route('noon-receipts.index'))
            ->assertRedirect(route('login'));
    }

    public function test_accounts_can_view_successful_noon_receipts_only(): void
    {
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create();
        $order = $this->makeOrder($accounts, 'عميل نون', 1500);

        $service = app(OrderPaymentReceiptService::class);
        $noonReceipt = $service->recordPayment($order, 1500, $accounts, 'noon', 'payment', 'دفع إلكتروني عبر Noon (noon-123)');
        $service->approveReceipt($noonReceipt, $accounts);

        $cashOrder = $this->makeOrder($accounts, 'عميل كاش', 800);
        $cashReceipt = $service->recordPayment($cashOrder, 800, $accounts, 'cash', 'payment');
        $service->approveReceipt($cashReceipt, $accounts);

        $pendingOrder = $this->makeOrder($accounts, 'عميل معلّق', 400);
        $service->recordPayment($pendingOrder, 400, $accounts, 'noon', 'payment');

        $this->actingAs($accounts)
            ->get(route('noon-receipts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NoonReceipts/Index')
                ->has('receipts.data', 1)
                ->where('receipts.data.0.customer_name', 'عميل نون')
                ->where('receipts.data.0.receipt_number', $noonReceipt->receipt_number)
                ->where('stats.count', 1)
            );
    }

    public function test_paid_noon_orders_without_a_receipt_still_appear(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder($admin, 'عميل المتجر', 990);
        $order->update([
            'payment_method' => 'noon',
            'payment_status' => 'paid',
            'status' => 'paid',
            'amount_paid' => 990,
            'payment_id' => 'noon-store-1',
        ]);

        $this->actingAs($admin)
            ->get(route('noon-receipts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NoonReceipts/Index')
                ->has('receipts.data', 1)
                ->where('receipts.data.0.customer_name', 'عميل المتجر')
                ->where('receipts.data.0.noon_order_id', 'noon-store-1')
            );
    }

    public function test_successful_noon_receipt_pdf_can_be_viewed_and_downloaded(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder($admin, 'سارة أحمد', 250);
        $service = app(OrderPaymentReceiptService::class);
        $receipt = $service->recordPayment($order, 250, $admin, 'noon', 'payment');
        $service->approveReceipt($receipt, $admin);

        $this->actingAs($admin)
            ->get(route('noon-receipts.pdf', $receipt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="'.$receipt->receipt_number.'.pdf"');

        $this->actingAs($admin)
            ->get(route('noon-receipts.pdf', ['receipt' => $receipt, 'download' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="'.$receipt->receipt_number.'.pdf"');
    }

    public function test_pending_noon_receipt_pdf_is_not_available(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder($admin, 'عميل معلّق', 100);
        $receipt = app(OrderPaymentReceiptService::class)
            ->recordPayment($order, 100, $admin, 'noon', 'payment');

        $this->actingAs($admin)
            ->get(route('noon-receipts.pdf', $receipt))
            ->assertNotFound();
    }

    public function test_paid_noon_order_pdf_can_be_downloaded(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder($admin, 'عميل المتجر', 990);
        $order->update([
            'payment_method' => 'noon',
            'payment_status' => 'paid',
            'status' => 'paid',
            'amount_paid' => 990,
            'payment_id' => 'noon-store-2',
        ]);

        $this->actingAs($admin)
            ->get(route('noon-receipts.order-pdf', ['order' => $order, 'download' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('order_payment_receipts', [
            'order_id' => $order->id,
            'payment_method' => 'noon',
            'approval_status' => OrderPaymentReceipt::STATUS_APPROVED,
        ]);
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
