<?php

namespace App\Observers;

use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\Order;

class OrderObserver
{
    public function created(Order $order): void
    {
        if ($this->shouldNotify($order)) {
            SendOrderWhatsAppNotification::dispatch($order->id);
        }
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('payment_status') && $this->shouldNotify($order)) {
            SendOrderWhatsAppNotification::dispatch($order->id);
        }
    }

    private function shouldNotify(Order $order): bool
    {
        if ($order->whatsapp_notified_at !== null) {
            return false;
        }

        return $order->payment_status === 'paid' || $order->status === 'paid';
    }
}
