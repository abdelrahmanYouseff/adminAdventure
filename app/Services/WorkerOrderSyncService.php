<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\WorkerOrder;

class WorkerOrderSyncService
{
    /**
     * Prefer pivot products; fall back to items JSON (with product_id when available).
     */
    public function syncFromOrder(Order $order): void
    {
        if (! $this->shouldCreateWorkOrders($order)) {
            return;
        }

        $order->loadMissing('products');

        $items = is_array($order->items) ? $order->items : [];

        // Prefer items JSON so custom lines are not dropped when pivot products exist.
        if ($items !== []) {
            foreach ($items as $index => $item) {
                $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
                if ($productId !== null && $productId <= 0) {
                    $productId = null;
                }
                $productName = $item['product_name'] ?? $item['name'] ?? 'منتج';
                $image = null;

                if ($productId) {
                    $product = Product::query()->find($productId);
                    $image = $product?->image;
                    if ($product && ($productName === 'منتج' || $productName === '')) {
                        $productName = $product->product_name;
                    }
                }

                $this->upsertWorkerOrder($order, $index, $productId, $productName, $image);
            }

            return;
        }

        if ($order->products->isNotEmpty()) {
            foreach ($order->products as $index => $product) {
                $this->upsertWorkerOrder($order, $index, $product->id, $product->product_name, $product->image);
            }
        }
    }

    private function upsertWorkerOrder(
        Order $order,
        int $lineIndex,
        ?int $productId,
        string $productName,
        ?string $productImage,
    ): void {
        $workerOrder = WorkerOrder::firstOrNew([
            'order_id' => $order->id,
            'line_index' => $lineIndex,
        ]);

        $workerOrder->fill([
            'product_id' => $productId,
            'product_name' => $productName,
            'product_image' => $productImage,
            'customer_name' => $order->customer_name,
            'installation_date' => $order->activity_date,
            'customer_address' => $order->address,
        ]);

        if (! $workerOrder->exists) {
            $workerOrder->status = 'pending';
        }

        $workerOrder->save();
    }

    /**
     * Work orders are released once the accountant approves at least one
     * payment receipt — even a partial one — regardless of whether the order
     * is fully paid yet.
     */
    private function shouldCreateWorkOrders(Order $order): bool
    {
        return $order->hasApprovedPaymentReceipt();
    }
}
