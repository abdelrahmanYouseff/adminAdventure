<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\Order;
use App\Support\WhatsAppConfig;
use Illuminate\Console\Command;

class ResendOrderWhatsAppNotification extends Command
{
    protected $signature = 'order:notify-whatsapp {order_number : Order number e.g. ORD-202607-0002}';

    protected $description = 'إعادة إرسال إشعار واتساب لطلب معيّن';

    public function handle(): int
    {
        $issues = WhatsAppConfig::issues();
        if ($issues !== []) {
            $this->error('إعدادات واتساب غير مكتملة:');
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
            $this->line('شغّل: php artisan whatsapp:diagnose');

            return self::FAILURE;
        }

        $order = Order::query()
            ->where('order_number', $this->argument('order_number'))
            ->first();

        if (! $order) {
            $this->error('الطلب غير موجود.');

            return self::FAILURE;
        }

        $order->forceFill(['whatsapp_notified_at' => null])->saveQuietly();

        try {
            SendOrderWhatsAppNotification::dispatchSync($order->id);
        } catch (\Throwable $e) {
            $this->error('فشل الإرسال: '.$e->getMessage());
            $this->line('شغّل: php artisan whatsapp:test --order='.$order->order_number);

            return self::FAILURE;
        }

        $order->refresh();

        if ($order->whatsapp_notified_at) {
            $this->info("تم إرسال إشعار واتساب للطلب {$order->order_number}.");

            return self::SUCCESS;
        }

        $this->warn('لم يكتمل الإرسال. شغّل: php artisan whatsapp:diagnose ثم راجع storage/logs/laravel.log');

        return self::FAILURE;
    }
}
