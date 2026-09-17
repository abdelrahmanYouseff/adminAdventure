<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\CommissionReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommissionReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_commissions_page_lists_only_closed_orders_with_invoices_in_the_selected_month(): void
    {
        $admin = User::factory()->admin()->create();
        $month = now()->format('Y-m');

        $included = $this->makeClosedInvoicedOrder($admin, now()->startOfMonth()->addDays(2), 'ORD-IN');
        $this->makeOpenInvoicedOrder($admin, now()->startOfMonth()->addDays(3), 'ORD-OPEN');
        $this->makeClosedOrderWithoutInvoice($admin, now()->startOfMonth()->addDays(4), 'ORD-NOINV');
        $this->makeClosedInvoicedOrder($admin, now()->subMonthNoOverflow()->startOfMonth()->addDays(5), 'ORD-OLD');

        $this->actingAs($admin)
            ->get(route('reports.commissions', ['month' => $month]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Commissions')
                ->has('rows', 1)
                ->where('rows.0.invoice_number', $included->invoice->invoice_number)
                ->where('rows.0.invoice_id', $included->invoice_id)
                ->where('rows.0.commission', 15)
                ->where('summary.orders_count', 1)
                ->where('summary.commission_total', 15)
            );
    }

    public function test_changing_the_month_filter_returns_that_month_only(): void
    {
        $admin = User::factory()->admin()->create();
        $lastMonth = now()->subMonthNoOverflow();

        $this->makeClosedInvoicedOrder($admin, now()->startOfMonth()->addDays(2), 'ORD-NOW');
        $previous = $this->makeClosedInvoicedOrder($admin, $lastMonth->copy()->startOfMonth()->addDays(5), 'ORD-PREV');

        $this->actingAs($admin)
            ->get(route('reports.commissions', ['month' => $lastMonth->format('Y-m')]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Commissions')
                ->has('rows', 1)
                ->where('rows.0.invoice_number', $previous->invoice->invoice_number)
                ->where('rows.0.invoice_id', $previous->invoice_id)
            );
    }

    public function test_commission_is_calculated_from_amount_brackets(): void
    {
        $cases = [
            [498, 0],
            [499, 15],
            [999, 15],
            [1000, 20],
            [1499, 20],
            [1500, 25],
            [2000, 30],
            [2500, 35],
            [10000, 35],
            [10001, 50],
            [15001, 75],
            [25001, 100],
            [50001, 125],
            [75001, 150],
            [100000, 150],
            [120000, 150],
        ];

        foreach ($cases as [$amount, $commission]) {
            $this->assertSame(
                (float) $commission,
                CommissionReportService::commissionForAmount((float) $amount),
                "amount {$amount} should yield commission {$commission}",
            );
        }
    }

    public function test_commission_service_excludes_open_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makeOpenInvoicedOrder($admin, now(), 'ORD-OPEN');

        $report = app(CommissionReportService::class)->build(now()->format('Y-m'));

        $this->assertSame(0, $report['summary']['orders_count']);
        $this->assertSame([], $report['rows']);
    }

    private function makeClosedInvoicedOrder(User $user, $closedAt, string $suffix): Order
    {
        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-'.$suffix,
            'amount' => 500,
            'status' => 'paid',
        ]);

        return Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'عميل '.$suffix,
            'order_number' => $suffix,
            'total_amount' => 500,
            'amount_paid' => 500,
            'currency' => 'SAR',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'invoice_id' => $invoice->id,
            'warehouse_keeper_approved_at' => $closedAt,
        ]);
    }

    private function makeOpenInvoicedOrder(User $user, $createdAt, string $suffix): Order
    {
        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-'.$suffix,
            'amount' => 400,
            'status' => 'paid',
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'عميل '.$suffix,
            'order_number' => $suffix,
            'total_amount' => 400,
            'amount_paid' => 400,
            'currency' => 'SAR',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'invoice_id' => $invoice->id,
        ]);
        $order->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $order;
    }

    private function makeClosedOrderWithoutInvoice(User $user, $closedAt, string $suffix): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'عميل '.$suffix,
            'order_number' => $suffix,
            'total_amount' => 300,
            'amount_paid' => 300,
            'currency' => 'SAR',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'warehouse_keeper_approved_at' => $closedAt,
        ]);
    }
}
