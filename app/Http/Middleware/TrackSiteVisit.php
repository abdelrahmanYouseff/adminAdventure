<?php

namespace App\Http\Middleware;

use App\Services\SiteVisitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackSiteVisit
{
    /** @var list<string> */
    private const EXCLUDED_PREFIXES = [
        'dashboard',
        'products',
        'categories',
        'packages',
        'customers',
        'users',
        'invoices',
        'quotations',
        'orders',
        'worker-orders',
        'settings',
        'worker-app',
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
        'confirm-password',
        'storage',
        'up',
        'api',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldTrack($request, $response)) {
            return;
        }

        try {
            app(SiteVisitService::class)->record($request);
        } catch (\Throwable) {
            // لا نعطل الموقع إذا فشل التتبع
        }
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return false;
        }

        if ($request->is('livewire/*') || $request->expectsJson()) {
            return false;
        }

        $path = $request->path();

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return true;
    }
}
