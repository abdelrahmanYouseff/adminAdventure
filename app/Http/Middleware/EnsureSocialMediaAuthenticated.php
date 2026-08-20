<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSocialMediaAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('social_media_authenticated') === true) {
            return $next($request);
        }

        return redirect()->route('social-media.login');
    }
}
