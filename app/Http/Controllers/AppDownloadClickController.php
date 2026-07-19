<?php

namespace App\Http\Controllers;

use App\Models\AppDownloadClick;
use Illuminate\Http\Request;

class AppDownloadClickController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:ios,android'],
        ]);

        AppDownloadClick::create([
            'platform' => $validated['platform'],
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
            'clicked_at' => now(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}
