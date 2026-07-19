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
            if ($user?->canAccessDashboard()) {
                return redirect()->route($user->homeRouteName());
            }

            abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}
