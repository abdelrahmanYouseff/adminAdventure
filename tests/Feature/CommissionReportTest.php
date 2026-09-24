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

    public function test_commissions_page_lists_paid_invoices_created_in_the_selected_month(): void
    {
        $admin = User::factory()->admin()->create();
        $month = now()->format('Y-m');

        $included = $this->makePaidInvoice($admin, now()->startOfMonth()->addDays(2), 'INV-IN', 500);
        $this->makePaidInvoice($admin, now()->startOfMonth()->addDays(3), 'INV-OPEN', 500, orderStatus: 'paid', warehouseClosed: false);
        $this->makeUnpaidInvoice($admin, now()->startOfMonth()->addDays(4), 'INV-UNPAID', 300);
        $this->makePaidInvoice($admin, now()->subMonthNoOverflow()->startOfMonth()->addDays(5), 'INV-OLD', 500);

        $this->actingAs($admin)
            ->get(route('reports.commissions', ['month' => $month]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Commissions')
                ->has('rows', 2)
                ->where('rows.0.invoice_number', $included->invoice_number)
                ->where('rows.0.invoice_id', $included->id)
                ->where('rows.0.commission', 15)
                ->where('summary.orders_count', 2)
                ->where('summary.total_amount', 1000)
                ->where('summary.commission_total', 30)
            );
    }

    public function test_changing_the_month_filter_returns_that_months_invoices_only(): void
    {
        $admin = User::factory()->admin()->create();
        $lastMonth = now()->subMonthNoOverflow();

        $this->makePaidInvoice($admin, now()->startOfMonth()->addDays(2), 'INV-NOW', 500);
        $previous = $this->makePaidInvoice($admin, $lastMonth->copy()->startOfMonth()->addDays(5), 'INV-PREV', 500);

        $this->actingAs($admin)
            ->get(route('reports.commissions', ['month' => $lastMonth->format('Y-m')]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Commissions')
                ->has('rows', 1)
                ->where('rows.0.invoice_number', $previous->invoice_number)
                ->where('rows.0.invoice_id', $previous->id)
                ->where('summary.orders_count', 1)
                ->where('summary.total_amount', 500)
                ->where('summary.commission_total', 15)
            );
    }

    public function test_cancelled_order_invoices_are_excluded(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makePaidInvoice($admin, now(), 'INV-CANCELLED', 800, orderStatus: 'cancelled');

        $report = app(CommissionReportService::class)->build(now()->format('Y-m'));

        $this->assertSame(0, $report['summary']['orders_count']);
        $this->assertSame([], $report['rows']);
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

    private function makePaidInvoice(
        User $user,
        $createdAt,
        string $suffix,
        float $amount,
        string $orderStatus = 'paid',
        bool $warehouseClosed = true,
    ): Invoice {
        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'invoice_number' => $suffix,
            'amount' => $amount,
            'status' => 'paid',
        ]);
        $invoice->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'عميل '.$suffix,
            'order_number' => 'ORD-'.$suffix,
            'total_amount' => $amount,
            'amount_paid' => $amount,
            'currency' => 'SAR',
            'status' => $orderStatus,
            'payment_status' => $orderStatus === 'paid' ? 'paid' : 'pending',
            'payment_method' => 'cash',
            'invoice_id' => $invoice->id,
            'warehouse_keeper_approved_at' => $warehouseClosed ? $createdAt : null,
        ]);

        return $invoice->fresh();
    }

    private function makeUnpaidInvoice(User $user, $createdAt, string $suffix, float $amount): Invoice
    {
        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'invoice_number' => $suffix,
            'amount' => $amount,
            'status' => 'pending',
        ]);
        $invoice->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $invoice->fresh();
    }
}
