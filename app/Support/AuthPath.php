<?php

namespace App\Support;

class AuthPath
{
    /**
     * مسارات لوحة التحكم / التطبيق الإداري — ممنوعة على العملاء.
     *
     * @var list<string>
     */
    private const DASHBOARD_PREFIXES = [
        'dashboard',
        'login',
        'register',
        'users',
        'products',
        'categories',
        'packages',
        'orders',
        'invoices',
        'quotations',
        'customers',
        'company-clients',
        'worker-orders',
        'insurance-deposits',
        'settings',
        'worker-app',
        'password',
        'email',
        'confirm-password',
        'verification',
    ];

    public static function normalizePath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) && $parsed !== '' ? $parsed : $path;

        return '/'.ltrim($path, '/');
    }

    public static function isDashboardPath(string $path): bool
    {
        $path = self::normalizePath($path);

        if ($path === '/') {
            return false;
        }

        foreach (self::DASHBOARD_PREFIXES as $prefix) {
            if ($path === '/'.$prefix || str_starts_with($path, '/'.$prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * إعادة توجيه آمنة لواجهة المتجر/الموقع (ليست لوحة التحكم).
     */
    public static function isSafeStoreRedirect(?string $redirect): bool
    {
        if (! is_string($redirect) || $redirect === '') {
            return false;
        }

        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return false;
        }

        return ! self::isDashboardPath($redirect);
    }

    public static function sanitizeStoreRedirect(?string $redirect): string
    {
        return self::isSafeStoreRedirect($redirect) ? $redirect : '/home';
    }
}
