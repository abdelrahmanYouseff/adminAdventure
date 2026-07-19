<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        return $next($request);
    }
}
