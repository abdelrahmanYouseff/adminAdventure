<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessDashboard()) {
            if ($user?->isWorker()) {
                return redirect()->route('pwa.dashboard');
            }

            if ($user) {
                return redirect()->route('home');
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
