<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UsePwaRootView
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::setRootView('pwa');

        return $next($request);
    }
}
