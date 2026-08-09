<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsMainAppStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('main.login');
        }

        if ($user->isWorker()) {
            return redirect()->route('pwa.dashboard');
        }

        if (! $user->canAccessDashboard()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
