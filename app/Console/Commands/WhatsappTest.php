<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\WhatsAppCloudService;
use App\Support\OrderWhatsAppMessage;
use App\Support\WhatsAppConfig;
use Illuminate\Console\Command;

class WhatsappTest extends Command
{
    protected $signature = 'whatsapp:test {--order= : رقم طلب لإرسال رسالته الفعلية}';

    protected $description = 'إرسال رسالة واتساب تجريبية وعرض نتيجة الـ API';

    public function handle(WhatsAppCloudService $whatsapp): int
    {
        $issues = WhatsAppConfig::issues();
        if ($issues !== []) {
            $this->error('الإعدادات غير مكتملة. شغّل: php artisan whatsapp:diagnose');

            return self::FAILURE;
        }

        $orderNumber = $this->option('order');
        if ($orderNumber) {
            $order = Order::query()->where('order_number', $orderNumber)->first();
            if (! $order) {
                $this->error("الطلب {$orderNumber} غير موجود.");

                return self::FAILURE;
            }
            $message = OrderWhatsAppMessage::build($order);
            $this->info("إرسال رسالة الطلب {$orderNumber}...");
        } else {
            $message = "اختبار واتساب — عالم المغامرة\nالوقت: ".now()->format('Y-m-d H:i:s');
            $this->info('إرسال رسالة اختبار...');
        }

        $this->line('المستلمون: '.implode(', ', $whatsapp->recipientNumbers()));
        $this->newLine();
        $this->line('--- نص الرسالة ---');
        $this->line($message);
        $this->line('------------------');
        $this->newLine();

        $results = $whatsapp->sendToAllRecipientsWithReport($message);

        $this->table(['الرقم', 'الحالة', 'التفاصيل'], array_map(
            fn (array $row) => [$row['to'], $row['success'] ? 'نجح' : 'فشل', $row['detail']],
            $results
        ));

        $successCount = count(array_filter($results, fn (array $r) => $r['success']));

        if ($successCount === 0) {
            $this->error('لم تُرسل الرسالة لأي رقم. راجع التفاصيل أعلاه و storage/logs/laravel.log');

            return self::FAILURE;
        }

        $this->info("تم الإرسال بنجاح إلى {$successCount} رقم/أرقام.");

        return self::SUCCESS;
    }
}
