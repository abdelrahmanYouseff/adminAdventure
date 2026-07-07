<?php

namespace App\Console\Commands;

use App\Services\WhatsAppCloudService;
use App\Support\WhatsAppConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class WhatsappDiagnose extends Command
{
    protected $signature = 'whatsapp:diagnose';

    protected $description = 'فحص إعدادات واتساب وإظهار سبب عدم وصول الإشعارات';

    public function handle(WhatsAppCloudService $whatsapp): int
    {
        $this->info('=== فحص إعدادات واتساب ===');
        $this->line('ملاحظة: إشعارات واتساب تُرسل فوراً ولا تحتاج queue:work');

        $this->table(['الإعداد', 'القيمة'], [
            ['WHATSAPP_ENABLED', config('services.whatsapp.enabled') ? 'true' : 'false'],
            ['WHATSAPP_PHONE_NUMBER_ID', config('services.whatsapp.phone_number_id') ?: '—'],
            ['WHATSAPP_ACCESS_TOKEN', filled(config('services.whatsapp.access_token')) ? 'مضبوط' : 'غير مضبوط'],
            ['WHATSAPP_BUSINESS_PHONE', config('services.whatsapp.business_phone') ?: '—'],
            ['WHATSAPP_ORDER_TEMPLATE', config('services.whatsapp.order_template') ?: '—'],
            ['إرسال واتساب', 'فوري — بدون queue worker'],
            ['GRAPH_VERSION', config('services.whatsapp.graph_version')],
        ]);

        if (Schema::hasTable('whatsapp_notification_recipients')) {
            $dbRecipients = \App\Models\WhatsappNotificationRecipient::query()
                ->orderBy('id')
                ->get(['phone', 'label', 'is_active']);

            if ($dbRecipients->isEmpty()) {
                $this->warn('لا توجد أرقام مستلمة — أضف أرقاماً من إعدادات واتساب في لوحة التحكم');
            } else {
                $this->info('الأرقام في قاعدة البيانات:');
                $this->table(
                    ['الرقم', 'ملاحظة', 'نشط'],
                    $dbRecipients->map(fn ($r) => [$r->phone, $r->label ?? '—', $r->is_active ? 'نعم' : 'لا'])->all()
                );
            }
        } else {
            $this->error('جدول whatsapp_notification_recipients غير موجود — شغّل: php artisan migrate');
        }

        $this->line('رقم الإرسال (لا يُرسل إليه): '.WhatsAppCloudService::senderDisplayPhone());
        $this->info('الأرقام المستخدمة فعلياً للإرسال (من الإعدادات فقط):');
        foreach ($whatsapp->recipientNumbers() as $number) {
            $this->line("  • {$number}");
        }

        $issues = WhatsAppConfig::issues();
        if ($issues !== []) {
            $this->newLine();
            $this->error('مشاكل يجب إصلاحها:');
            foreach ($issues as $issue) {
                $this->line("  ✗ {$issue}");
            }
            $this->newLine();
            $this->line('بعد تعديل .env شغّل: php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('الإعدادات تبدو صحيحة.');

        $template = (string) config('services.whatsapp.order_template', '');
        if ($template === '') {
            $this->warn('WHATSAPP_ORDER_TEMPLATE غير مضبوط — الرسائل النصية قد لا تصل خارج نافذة 24 ساعة.');
        } else {
            $lang = (string) config('services.whatsapp.order_template_language', 'ar');
            $this->line("قالب الطلبات: {$template} ({$lang})");

            $verify = $whatsapp->verifyOrderTemplate();
            if ($verify['error']) {
                $this->warn('تعذّر التحقق من القالب: '.$verify['error']);
            } elseif ($verify['found']) {
                $status = strtoupper((string) $verify['status']);
                if ($status === 'APPROVED') {
                    $this->info('✓ القالب موجود ومعتمد في Meta');
                } else {
                    $this->warn("القالب موجود لكن حالته: {$verify['status']}");
                }
            } else {
                $this->error("✗ القالب {$template} / {$lang} غير موجود — شغّل: php artisan whatsapp:list-templates");
            }
        }

        $this->line('جرّب: php artisan whatsapp:list-templates');
        $this->line('ثم: php artisan whatsapp:test --order=ORD-XXXX');

        return self::SUCCESS;
    }
}
