<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UseMainAppRootView
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::setRootView('main-app');

        return $next($request);
    }
}
