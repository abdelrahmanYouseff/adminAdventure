<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

class OrderInsuranceCalculator
{
    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $lines
     * @return array{total: float, unit_by_product: array<int, float>}
     */
    public static function fromLines(array $lines): array
    {
        $productIds = collect($lines)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return ['total' => 0.0, 'unit_by_product' => []];
        }

        /** @var Collection<int, float|string|null> $unitByProduct */
        $unitByProduct = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('insurance_amount', 'id')
            ->map(fn ($amount) => round((float) $amount, 2));

        $total = 0.0;

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            $unit = (float) ($unitByProduct[$productId] ?? 0);
            $total += $unit * $quantity;
        }

        return [
            'total' => round($total, 2),
            'unit_by_product' => $unitByProduct->all(),
        ];
    }
}
