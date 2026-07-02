<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\WhatsAppCloudService;
use App\Support\OrderWhatsAppMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(WhatsAppCloudService $whatsapp): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        if ($order->whatsapp_notified_at !== null) {
            return;
        }

        if (! $whatsapp->isConfigured()) {
            Log::warning('WhatsApp order notification skipped: service not configured', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'enabled' => config('services.whatsapp.enabled'),
                'recipients' => $whatsapp->recipientNumbers(),
            ]);

            return;
        }

        $message = OrderWhatsAppMessage::build($order);

        Log::info('WhatsApp order notification sending', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'recipients' => $whatsapp->recipientNumbers(),
        ]);

        $whatsapp->sendToAllRecipients($message);

        $order->forceFill(['whatsapp_notified_at' => now()])->save();
    }
}
