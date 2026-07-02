<?php

namespace App\Observers;

use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\Order;
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
        Log::info('WhatsApp order notification queued', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'sync' => config('services.whatsapp.dispatch_sync', true),
        ]);

        if (config('services.whatsapp.dispatch_sync', true)) {
            SendOrderWhatsAppNotification::dispatchSync($order->id);

            return;
        }

        SendOrderWhatsAppNotification::dispatch($order->id)->afterCommit();
    }

    private function shouldNotify(Order $order): bool
    {
        if ($order->whatsapp_notified_at !== null) {
            return false;
        }

        return true;
    }
}
