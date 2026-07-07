<?php

namespace App\Console\Commands;

use App\Services\WhatsAppCloudService;
use App\Support\WhatsAppConfig;
use Illuminate\Console\Command;

class WhatsappListTemplates extends Command
{
    protected $signature = 'whatsapp:list-templates';

    protected $description = 'عرض قوالب واتساب المعتمدة في حساب Meta';

    public function handle(WhatsAppCloudService $whatsapp): int
    {
        $issues = WhatsAppConfig::issues();
        if ($issues !== []) {
            $this->error('الإعدادات غير مكتملة. شغّل: php artisan whatsapp:diagnose');

            return self::FAILURE;
        }

        $configuredName = (string) config('services.whatsapp.order_template', '');
        $configuredLang = (string) config('services.whatsapp.order_template_language', 'ar');

        $this->info('جاري جلب القوالب من Meta...');

        $result = $whatsapp->listMessageTemplates();

        if (! $result['success']) {
            $this->error('فشل الجلب: '.($result['error'] ?? 'خطأ غير معروف'));

            return self::FAILURE;
        }

        if ($result['templates'] === []) {
            $this->warn('لا توجد قوالب في الحساب. أنشئ قالباً من Meta Business Manager → WhatsApp Manager → Message templates');

            return self::SUCCESS;
        }

        $this->table(
            ['الاسم', 'اللغة', 'الحالة', 'الفئة'],
            array_map(
                fn (array $row) => [$row['name'], $row['language'], $row['status'], $row['category']],
                $result['templates']
            )
        );

        $this->newLine();
        $this->line('الإعداد الحالي في .env:');
        $this->line("  WHATSAPP_ORDER_TEMPLATE={$configuredName}");
        $this->line("  WHATSAPP_ORDER_TEMPLATE_LANGUAGE={$configuredLang}");

        if ($configuredName === '') {
            $this->warn('اضبط WHATSAPP_ORDER_TEMPLATE على أحد الأسماء أعلاه (حالة APPROVED).');

            return self::SUCCESS;
        }

        $match = collect($result['templates'])->first(
            fn (array $t) => $t['name'] === $configuredName && $t['language'] === $configuredLang
        );

        if ($match) {
            if (strtoupper($match['status']) === 'APPROVED') {
                $this->info("✓ القالب المضبوط موجود ومعتمد ({$configuredName} / {$configuredLang})");
            } else {
                $this->warn("القالب موجود لكن حالته: {$match['status']} — انتظر الاعتماد من Meta");
            }
        } else {
            $this->error("✗ القالب {$configuredName} باللغة {$configuredLang} غير موجود — هذا سبب خطأ (#132001)");
            $this->line('إما أنشئ القالب بهذا الاسم واللغة، أو عدّل .env ليطابق قالباً موجوداً أعلاه.');
            $this->line('مثال قالب مطلوب:');
            $this->line('  الاسم: order_new');
            $this->line('  اللغة: Arabic');
            $this->line('  النص: طلب جديد — عالم المغامرة');
            $this->line('        {{1}}');
        }

        return self::SUCCESS;
    }
}
