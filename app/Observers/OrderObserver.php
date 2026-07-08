<?php

namespace App\Observers;

use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\Order;
use App\Services\WorkerOrderSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(private WorkerOrderSyncService $workerOrderSyncService) {}

    public function created(Order $order): void
    {
        if ($this->shouldNotify($order)) {
            $this->dispatchNotification($order);
        }

        if ($this->isPaid($order)) {
            $this->syncWorkerOrders($order);
        }
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('payment_status') || $order->wasChanged('status')) {
            if ($this->shouldNotify($order)) {
                $this->dispatchNotification($order);
            }

            if ($this->isPaid($order)) {
                $this->syncWorkerOrders($order);
            }
        }
    }

    private function dispatchNotification(Order $order): void
    {
        $orderId = $order->id;

        $run = function () use ($orderId, $order) {
            Log::info('WhatsApp order notification sending immediately (no queue)', [
                'order_id' => $orderId,
                'order_number' => $order->order_number,
            ]);

            try {
                $this->sendNow($orderId);
            } catch (\Throwable $e) {
                Log::error('WhatsApp order notification failed', [
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'message' => $e->getMessage(),
                ]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);
        } else {
            $run();
        }
    }

    private function sendNow(int $orderId): void
    {
        $job = new SendOrderWhatsAppNotification($orderId);
        app()->call([$job, 'handle']);
    }

    private function shouldNotify(Order $order): bool
    {
        if ($order->whatsapp_notified_at !== null) {
            return false;
        }

        return $this->isPaid($order);
    }

    private function isPaid(Order $order): bool
    {
        return $order->payment_status === 'paid' || $order->status === 'paid';
    }

    private function syncWorkerOrders(Order $order): void
    {
        $orderId = $order->id;

        $run = function () use ($orderId) {
            $freshOrder = Order::with('products')->find($orderId);

            if (! $freshOrder) {
                return;
            }

            try {
                $this->workerOrderSyncService->syncFromOrder($freshOrder);
            } catch (\Throwable $e) {
                Log::error('Worker order sync failed', [
                    'order_id' => $orderId,
                    'message' => $e->getMessage(),
                ]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);
        } else {
            $run();
        }
    }
}
