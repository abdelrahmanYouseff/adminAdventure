<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\WorkerOrder;

class WorkerOrderSyncService
{
    public function syncFromOrder(Order $order): void
    {
        if (! $this->isPaid($order)) {
            return;
        }

        $order->loadMissing('products');

        if ($order->products->isNotEmpty()) {
            foreach ($order->products as $index => $product) {
                $this->upsertWorkerOrder($order, $index, $product->id, $product->product_name, $product->image);
            }

            return;
        }

        $items = is_array($order->items) ? $order->items : [];

        foreach ($items as $index => $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $productName = $item['product_name'] ?? $item['name'] ?? 'منتج';
            $image = null;

            if ($productId) {
                $image = Product::query()->whereKey($productId)->value('image');
            }

            $this->upsertWorkerOrder($order, $index, $productId, $productName, $image);
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

    private function isPaid(Order $order): bool
    {
        return $order->payment_status === 'paid' || $order->status === 'paid';
    }
}
