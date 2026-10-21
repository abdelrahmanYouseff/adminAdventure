<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuotationSubtotalDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_quotation_applies_a_percentage_discount_after_the_subtotal(): void
    {
        $admin = User::factory()->admin()->create();
        $brand = Brand::query()->create(['name' => 'Adventure']);
        $product = Product::query()->create([
            'product_name' => 'خيمة',
            'price' => 500,
            'insurance_amount' => 0,
            'status' => 'active',
            'brand_id' => $brand->id,
        ]);

        $this->actingAs($admin)
            ->post(route('quotations.store'), [
                'brand_id' => $brand->id,
                'customer_name' => 'عميل الخصم',
                'valid_until' => now()->addDays(7)->toDateString(),
                'discount_percent' => 10,
                'insurance_amount' => 0,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 500,
                    'discount_amount' => 0,
                ]],
            ])
            ->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('1000.00', $quotation->subtotal);
        $this->assertSame('0.00', $quotation->discount_total);
        $this->assertSame('10.00', $quotation->discount_percent);
        $this->assertSame('135.00', $quotation->tax_amount);
        $this->assertSame('1035.00', $quotation->total_amount);
    }

    public function test_percentage_discount_is_calculated_after_line_discounts(): void
    {
        $admin = User::factory()->admin()->create();
        $brand = Brand::query()->create(['name' => 'Adventure']);
        $product = Product::query()->create([
            'product_name' => 'طاقة',
            'price' => 100,
            'insurance_amount' => 0,
            'status' => 'active',
            'brand_id' => $brand->id,
        ]);

        $this->actingAs($admin)
            ->post(route('quotations.store'), [
                'brand_id' => $brand->id,
                'customer_name' => 'عميل الخصم',
                'valid_until' => now()->addDays(7)->toDateString(),
                'discount_percent' => 10,
                'insurance_amount' => 0,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100,
                    'discount_amount' => 10,
                ]],
            ])
            ->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('90.00', $quotation->subtotal);
        $this->assertSame('10.00', $quotation->discount_total);
        $this->assertSame('10.00', $quotation->discount_percent);
        $this->assertSame('12.15', $quotation->tax_amount);
        $this->assertSame('93.15', $quotation->total_amount);
    }

    public function test_discount_percent_above_100_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $brand = Brand::query()->create(['name' => 'Adventure']);
        $product = Product::query()->create([
            'product_name' => 'خيمة',
            'price' => 100,
            'status' => 'active',
            'brand_id' => $brand->id,
        ]);

        $this->actingAs($admin)
            ->from(route('quotations.create', ['brand' => $brand->id]))
            ->post(route('quotations.store'), [
                'brand_id' => $brand->id,
                'customer_name' => 'عميل الخصم',
                'valid_until' => now()->addDays(7)->toDateString(),
                'discount_percent' => 150,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('discount_percent');

        $this->assertSame(0, Quotation::query()->count());
    }

    public function test_order_show_keeps_the_subtotal_percentage_discount(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::query()->create([
            'user_id' => $admin->id,
            'customer_name' => 'عميل الخصم',
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => 1035,
            'discount_percent' => 10,
            'tax_amount' => 135,
            'insurance_amount' => 0,
            'currency' => 'SAR',
            'status' => 'processing',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'items' => [[
                'name' => 'خيمة',
                'quantity' => 1,
                'price' => 1000,
                'discount_amount' => 0,
                'amount' => 1000,
            ]],
        ]);

        $this->actingAs($admin)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Show')
                ->where('order.total_amount', '1035.00')
                ->where('order.tax_amount', '135.00')
                ->where('order.subtotal_discount', 100)
            );
    }
}
