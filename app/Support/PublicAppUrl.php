<?php

namespace App\Support;

class PublicAppUrl
{
    public static function base(): string
    {
        $url = config('app.public_url', config('app.url'));
        $parsed = parse_url((string) $url);

        if (! $parsed || empty($parsed['host'])) {
            return rtrim((string) $url, '/');
        }

        $host = $parsed['host'];
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    public static function to(string $path = ''): string
    {
        return self::base().'/'.ltrim($path, '/');
    }
}
