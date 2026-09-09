<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentSession;
use App\Models\User;
use App\Services\OrderPaymentReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_manual_noon_receipts_that_are_not_on_the_gateway_are_hidden(): void
    {
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create();
        $order = $this->makeOrder($accounts, 'لمي الصغير', 1950);
        $order->update([
            'order_number' => 'ORD-202609-0001',
            'payment_method' => 'noon',
        ]);

        $service = app(OrderPaymentReceiptService::class);
        $receipt = $service->recordPayment($order, 1950, $accounts, 'noon', 'payment');
        $service->approveReceipt($receipt, $accounts);

        $this->actingAs($accounts)
            ->get(route('noon-receipts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NoonReceipts/Index')
                ->has('receipts.data', 0)
                ->where('stats.count', 0)
            );
    }

    public function test_only_captured_noon_gateway_receipts_are_listed(): void
    {
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create();
        $this->fakeNoonApi([
            'noon-123' => 'CAPTURED',
            'missing-on-noon' => 404,
        ]);

        $onNoon = $this->makeOrder($accounts, 'عميل نون', 1500);
        $this->attachNoonGateway($onNoon, $accounts, 'noon-123');
        $service = app(OrderPaymentReceiptService::class);
        $noonReceipt = $service->recordPayment($onNoon, 1500, $accounts, 'noon', 'payment', 'دفع إلكتروني عبر Noon (noon-123)');
        $service->approveReceipt($noonReceipt, $accounts);

        $missing = $this->makeOrder($accounts, 'لمي الصغير', 1950);
        $this->attachNoonGateway($missing, $accounts, 'missing-on-noon');
        $missingReceipt = $service->recordPayment($missing, 1950, $accounts, 'noon', 'payment', 'دفع إلكتروني عبر Noon (missing-on-noon)');
        $service->approveReceipt($missingReceipt, $accounts);

        $cashOrder = $this->makeOrder($accounts, 'عميل كاش', 800);
        $cashReceipt = $service->recordPayment($cashOrder, 800, $accounts, 'cash', 'payment');
        $service->approveReceipt($cashReceipt, $accounts);

        $this->actingAs($accounts)
            ->get(route('noon-receipts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NoonReceipts/Index')
                ->has('receipts.data', 1)
                ->where('receipts.data.0.customer_name', 'عميل نون')
                ->where('receipts.data.0.noon_order_id', 'noon-123')
                ->where('stats.count', 1)
            );
    }

    public function test_captured_noon_session_appears_even_without_a_local_receipt(): void
    {
        $admin = User::factory()->admin()->create();
        $this->fakeNoonApi(['noon-888' => 'CAPTURED']);
        $order = $this->makeOrder($admin, 'نورة العتيبي', 640);
        $this->attachNoonGateway($order, $admin, 'noon-888');

        $this->actingAs($admin)
            ->get(route('noon-receipts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NoonReceipts/Index')
                ->has('receipts.data', 1)
                ->where('receipts.data.0.customer_name', 'نورة العتيبي')
                ->where('receipts.data.0.noon_order_id', 'noon-888')
                ->where('receipts.data.0.amount', 640)
            );
    }

    public function test_paid_noon_orders_without_a_gateway_session_are_hidden(): void
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
                ->has('receipts.data', 0)
            );
    }

    public function test_successful_noon_receipt_pdf_can_be_viewed_and_downloaded(): void
    {
        $admin = User::factory()->admin()->create();
        $this->fakeNoonApi(['noon-250' => 'CAPTURED']);
        $order = $this->makeOrder($admin, 'سارة أحمد', 250);
        $this->attachNoonGateway($order, $admin, 'noon-250');
        $service = app(OrderPaymentReceiptService::class);
        $receipt = $service->recordPayment($order, 250, $admin, 'noon', 'payment', 'دفع إلكتروني عبر Noon (noon-250)');
        $service->approveReceipt($receipt, $admin);

        $this->actingAs($admin)
            ->get(route('noon-receipts.transaction-pdf', 'noon-250'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

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

    public function test_paid_noon_order_without_gateway_confirmation_cannot_download_pdf(): void
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
            ->assertNotFound();
    }

    /**
     * @param  array<string, string|int>  $statuses
     */
    private function fakeNoonApi(array $statuses): void
    {
        config([
            'services.noon.api_key' => 'test-key',
            'services.noon.business_id' => 'biz',
            'services.noon.app_id' => 'app',
            'services.noon.api_url' => 'https://api.noon.test/payment/v1/',
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($statuses) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            if (str_contains($path, '/report/')) {
                return Http::response(['message' => 'not found'], 404);
            }

            $id = basename($path);
            $status = $statuses[$id] ?? 404;

            if ($status === 404) {
                return Http::response(['message' => 'not found'], 404);
            }

            return Http::response([
                'result' => [
                    'order' => [
                        'id' => $id,
                        'status' => $status,
                        'amount' => 640,
                        'currency' => 'SAR',
                        'reference' => $id,
                        'creationTime' => now()->toIso8601String(),
                    ],
                ],
            ], 200);
        });
    }

    private function attachNoonGateway(Order $order, User $user, string $noonOrderId): void
    {
        $order->forceFill([
            'payment_method' => 'noon',
            'payment_id' => $noonOrderId,
        ])->save();

        PaymentSession::query()->create([
            'merchant_reference' => $order->order_number,
            'user_id' => $user->id,
            'amount' => $order->total_amount,
            'currency' => $order->currency ?: 'SAR',
            'payload' => ['source' => 'quotation_pdf', 'noon_order_id' => $noonOrderId],
            'noon_order_id' => $noonOrderId,
            'used_at' => now(),
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
