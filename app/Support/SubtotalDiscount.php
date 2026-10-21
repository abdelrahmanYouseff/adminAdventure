<?php

namespace App\Support;

class SubtotalDiscount
{
    public const VAT_RATE = 0.15;

    public static function normalizePercent(mixed $percent): float
    {
        if (! is_numeric($percent)) {
            return 0.0;
        }

        return max(0, min(100, round((float) $percent, 2)));
    }

    public static function formatPercent(mixed $percent): string
    {
        $formatted = number_format(self::normalizePercent($percent), 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * Apply a percentage discount to a subtotal, then VAT on the remainder.
     *
     * @return array{
     *     discount_percent: float,
     *     discount_amount: float,
     *     taxable_subtotal: float,
     *     tax_amount: float,
     *     total_amount: float
     * }
     */
    public static function apply(float $subtotal, mixed $percent, float $vatRate = self::VAT_RATE): array
    {
        $subtotal = round($subtotal, 2);
        $discountPercent = self::normalizePercent($percent);
        $discountAmount = round($subtotal * $discountPercent / 100, 2);
        $taxable = round(max(0, $subtotal - $discountAmount), 2);
        $tax = round($taxable * $vatRate, 2);

        return [
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'taxable_subtotal' => $taxable,
            'tax_amount' => $tax,
            'total_amount' => round($taxable + $tax, 2),
        ];
    }
}
