<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceMonthFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_filter_returns_that_months_invoices_with_count_and_total(): void
    {
        $this->withoutVite();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $august = Invoice::query()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-202608-0001',
            'amount' => 200,
            'status' => 'paid',
            'issued_at' => '2026-08-10 12:00:00',
        ]);
        $august->forceFill([
            'created_at' => '2026-08-10 12:00:00',
            'updated_at' => '2026-08-10 12:00:00',
        ])->saveQuietly();

        $septemberOne = Invoice::query()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-202609-0001',
            'amount' => 350,
            'status' => 'paid',
            'issued_at' => '2026-09-05 09:00:00',
        ]);
        $septemberOne->forceFill([
            'created_at' => '2026-09-05 09:00:00',
            'updated_at' => '2026-09-05 09:00:00',
        ])->saveQuietly();

        $septemberTwo = Invoice::query()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-202609-0002',
            'amount' => 150,
            'status' => 'paid',
            'issued_at' => '2026-09-20 09:00:00',
        ]);
        $septemberTwo->forceFill([
            'created_at' => '2026-09-20 09:00:00',
            'updated_at' => '2026-09-20 09:00:00',
        ])->saveQuietly();

        $this->actingAs($admin)
            ->get(route('invoices.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invoices/Index')
                ->where('filters.month', '2026-09')
                ->where('summary.count', 2)
                ->where('summary.total_amount', 500)
                ->has('invoices.data', 2)
                ->where('invoices.data.0.invoice_number', 'INV-202609-0002')
                ->where('invoices.data.1.invoice_number', 'INV-202609-0001')
            );
    }
}
