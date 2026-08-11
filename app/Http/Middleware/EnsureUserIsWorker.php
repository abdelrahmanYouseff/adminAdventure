<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isWorker()) {
            if ($user?->canAccessDashboard()) {
                return redirect()->route($user->homeRouteName());
            }

            return redirect()->route('pwa.login');
        }

        $cacheKey = 'worker-last-seen:'.$user->id;
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinute());
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
