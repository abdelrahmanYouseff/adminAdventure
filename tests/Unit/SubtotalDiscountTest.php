<?php

namespace Tests\Unit;

use App\Support\SubtotalDiscount;
use PHPUnit\Framework\TestCase;

class SubtotalDiscountTest extends TestCase
{
    public function test_percentage_discount_is_applied_before_vat(): void
    {
        $priced = SubtotalDiscount::apply(1000, 10);

        $this->assertSame(10.0, $priced['discount_percent']);
        $this->assertSame(100.0, $priced['discount_amount']);
        $this->assertSame(900.0, $priced['taxable_subtotal']);
        $this->assertSame(135.0, $priced['tax_amount']);
        $this->assertSame(1035.0, $priced['total_amount']);
    }

    public function test_zero_percent_leaves_the_subtotal_unchanged(): void
    {
        $priced = SubtotalDiscount::apply(200, null);

        $this->assertSame(0.0, $priced['discount_percent']);
        $this->assertSame(0.0, $priced['discount_amount']);
        $this->assertSame(200.0, $priced['taxable_subtotal']);
        $this->assertSame(30.0, $priced['tax_amount']);
        $this->assertSame(230.0, $priced['total_amount']);
    }

    public function test_percent_is_clamped_and_rounded(): void
    {
        $this->assertSame(0.0, SubtotalDiscount::normalizePercent(-5));
        $this->assertSame(100.0, SubtotalDiscount::normalizePercent(140));
        $this->assertSame(10.56, SubtotalDiscount::normalizePercent(10.555));
        $this->assertSame('10.5', SubtotalDiscount::formatPercent(10.5));
    }
}
