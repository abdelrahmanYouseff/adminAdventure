<?php

namespace App\Support;

class WhatsAppConfig
{
    /**
     * @return list<string>
     */
    public static function apiIssues(): array
    {
        $issues = [];

        if (! config('services.whatsapp.enabled', false)) {
            $issues[] = 'WHATSAPP_ENABLED غير مفعّل (يجب أن يكون true في .env)';
        }

        if (! filled(config('services.whatsapp.phone_number_id'))) {
            $issues[] = 'WHATSAPP_PHONE_NUMBER_ID غير موجود في .env';
        }

        if (! filled(config('services.whatsapp.access_token'))) {
            $issues[] = 'WHATSAPP_ACCESS_TOKEN غير موجود في .env';
        }

        return $issues;
    }

    public static function isApiConfigured(): bool
    {
        return self::apiIssues() === [];
    }

    /**
     * @return list<string>
     */
    public static function issues(): array
    {
        $issues = self::apiIssues();

        $recipients = app(\App\Services\WhatsAppCloudService::class)->recipientNumbers();
        if ($recipients === []) {
            $issues[] = 'لا توجد أرقام مستلمة — أضف أرقاماً من لوحة التحكم → إعدادات واتساب';
        }

        return $issues;
    }

    public static function isReady(): bool
    {
        return self::issues() === [];
    }
}
