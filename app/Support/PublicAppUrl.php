<?php

namespace App\Support;

class PublicAppUrl
{
    public static function base(): string
    {
        $url = rtrim((string) config('app.public_url', config('app.url')), '/');

        // Keep APP_PUBLIC_URL exactly as configured (including www) so WhatsApp
        // buttons do not hit an extra 301 hop that some in-app browsers mishandle.
        return $url !== '' ? $url : 'http://localhost';
    }

    public static function to(string $path = ''): string
    {
        return self::base().'/'.ltrim($path, '/');
    }
}
