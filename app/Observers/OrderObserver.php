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

        if ($this->shouldCreateWorkOrders($order)) {
            $this->syncWorkerOrders($order);
        }
    }

    public function updated(Order $order): void
    {
        // amount_paid changes on accountant approval even when status stays
        // "processing" for partial payments — that must also release work orders.
        if (
            $order->wasChanged('payment_status')
            || $order->wasChanged('status')
            || $order->wasChanged('amount_paid')
            || $order->wasChanged('installation_at')
            || $order->wasChanged('activity_date')
            || $order->wasChanged('customer_name')
            || $order->wasChanged('address')
            || $order->wasChanged('operations_released_at')
        ) {
            if ($this->shouldNotify($order)) {
                $this->dispatchNotification($order);
            }

            if ($this->shouldCreateWorkOrders($order)) {
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
        // Staff order alerts (not delivery-note-to-customer).
        if (! config('services.whatsapp.enabled', false)) {
            return false;
        }

        if (! config('services.whatsapp.order_notifications', false)) {
            return false;
        }

        if ($order->whatsapp_notified_at !== null) {
            return false;
        }

        return $this->shouldCreateWorkOrders($order);
    }

    /**
     * Both work-order sync and the customer notification wait for the order to
     * be live and for the accountant to approve at least one payment receipt.
     */
    private function shouldCreateWorkOrders(Order $order): bool
    {
        return $order->shouldReleaseWorkOrders();
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
