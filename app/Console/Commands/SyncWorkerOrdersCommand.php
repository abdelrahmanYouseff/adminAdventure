<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\WorkerOrderSyncService;
use Illuminate\Console\Command;

class SyncWorkerOrdersCommand extends Command
{
    protected $signature = 'worker-orders:sync {--order= : Sync a single order by ID}';

    protected $description = 'Sync paid orders into worker orders for installation';

    public function handle(WorkerOrderSyncService $syncService): int
    {
        $orderId = $this->option('order');

        $query = Order::query()->with('products');

        if ($orderId) {
            $query->whereKey($orderId);
        } else {
            $query->whereHas('paymentReceipts', function ($q) {
                $q->where('approval_status', \App\Models\OrderPaymentReceipt::STATUS_APPROVED);
            });
        }

        $count = 0;

        $query->orderBy('id')->chunkById(50, function ($orders) use ($syncService, &$count) {
            foreach ($orders as $order) {
                $syncService->syncFromOrder($order);
                $count++;
            }
        });

        $this->info("Synced {$count} order(s) to worker orders.");

        return self::SUCCESS;
    }
}
