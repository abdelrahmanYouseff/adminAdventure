<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(...$roles)) {
            if ($user?->isWorker()) {
                return redirect()->route('pwa.dashboard');
            }

            if ($user?->canAccessDashboard()) {
                return redirect()->route($user->homeRouteName());
            }

            if ($user) {
                return redirect()->route('home');
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
