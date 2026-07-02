<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\WhatsAppCloudService;
use App\Support\OrderWhatsAppMessage;
use App\Support\WhatsAppConfig;
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
            Log::warning('WhatsApp order notification skipped: order not found', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        if ($order->whatsapp_notified_at !== null) {
            Log::info('WhatsApp order notification skipped: already sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $issues = WhatsAppConfig::issues();
        if ($issues !== []) {
            Log::error('WhatsApp order notification skipped: configuration incomplete', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'issues' => $issues,
            ]);

            return;
        }

        $message = OrderWhatsAppMessage::build($order);
        $recipients = $whatsapp->recipientNumbers();

        Log::info('WhatsApp order notification sending', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'recipients' => $recipients,
        ]);

        $results = $whatsapp->sendToAllRecipientsWithReport($message);

        $successCount = count(array_filter($results, fn (array $r) => $r['success']));

        Log::info('WhatsApp order notification results', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'results' => $results,
            'success_count' => $successCount,
        ]);

        if ($successCount === 0) {
            $errors = collect($results)->pluck('detail')->implode(' | ');
            throw new \RuntimeException('فشل إرسال واتساب لجميع المستلمين — '.$errors);
        }

        $order->forceFill(['whatsapp_notified_at' => now()])->saveQuietly();
    }
}
