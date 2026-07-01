<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\Order;
use Illuminate\Console\Command;

class ResendOrderWhatsAppNotification extends Command
{
    protected $signature = 'order:notify-whatsapp {order_number : Order number e.g. ORD-202607-0002}';

    protected $description = 'Resend WhatsApp order details notification for a paid order';

    public function handle(): int
    {
        $order = Order::query()
            ->where('order_number', $this->argument('order_number'))
            ->first();

        if (! $order) {
            $this->error('Order not found.');

            return self::FAILURE;
        }

        if ($order->payment_status !== 'paid' && $order->status !== 'paid') {
            $this->error('Order is not paid yet.');

            return self::FAILURE;
        }

        $order->forceFill(['whatsapp_notified_at' => null])->save();

        SendOrderWhatsAppNotification::dispatchSync($order->id);

        $order->refresh();

        if ($order->whatsapp_notified_at) {
            $this->info("WhatsApp notification sent for {$order->order_number}.");

            return self::SUCCESS;
        }

        $this->warn('Notification did not complete. Check storage/logs/laravel.log');

        return self::FAILURE;
    }
}
