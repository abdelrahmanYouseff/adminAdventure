<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\Order;
use App\Support\WhatsAppConfig;
use Illuminate\Console\Command;

class RetryPendingWhatsAppNotifications extends Command
{
    protected $signature = 'orders:retry-whatsapp-notifications {--limit=20 : Max orders per run}';

    protected $description = 'إعادة محاولة إرسال إشعارات واتساب للطلبات التي لم تُرسل بعد (احتياطي تلقائي)';

    public function handle(): int
    {
        if (! WhatsAppConfig::isReady()) {
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $orders = Order::query()
            ->whereNull('whatsapp_notified_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                SendOrderWhatsAppNotification::dispatchSync($order->id);
                $order->refresh();

                if ($order->whatsapp_notified_at) {
                    $sent++;
                    $this->line("✓ {$order->order_number}");
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("✗ {$order->order_number}: {$e->getMessage()}");
            }
        }

        $this->info("WhatsApp retry: sent={$sent}, failed={$failed}");

        return $failed > 0 && $sent === 0 ? self::FAILURE : self::SUCCESS;
    }
}
