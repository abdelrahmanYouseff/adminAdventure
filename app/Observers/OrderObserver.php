<?php

namespace App\Observers;

use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function created(Order $order): void
    {
        if ($this->shouldNotify($order)) {
            $this->dispatchNotification($order);
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('payment_status') && ! $order->wasChanged('status')) {
            return;
        }

        if ($this->shouldNotify($order)) {
            $this->dispatchNotification($order);
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
        return $order->whatsapp_notified_at === null;
    }
}
