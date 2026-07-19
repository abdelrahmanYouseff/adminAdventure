<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkerAuthController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isWorker()) {
            return redirect('/worker-app');
        }

        // أي جلسة غير عامل تُصفَّر حتى لا تمنع الدخول
        if ($request->user()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return Inertia::render('Login');
    }

    public function store(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "محاولات كثيرة. حاول مرة أخرى بعد {$seconds} ثانية.",
            ]);
        }

        // بدون remember لتجنب عمود remember_token غير الموجود في قاعدة البيانات
        if (! Auth::attempt($credentials, false)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isWorker()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'تطبيق العمال مخصص لحسابات العمال فقط.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Full visit يتجنب مشاكل إصدار أصول Inertia بعد تسجيل الدخول
        return Inertia::location('/worker-app');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pwa.login');
    }
}
