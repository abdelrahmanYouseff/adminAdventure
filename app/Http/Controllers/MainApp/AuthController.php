<?php

namespace App\Http\Controllers\MainApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isWorkersManager()) {
            return redirect()->route('main.home');
        }

        if ($request->user()?->isWorker()) {
            return redirect()->route('pwa.dashboard');
        }

        // أي جلسة موظف آخر تُحوَّل للوحة المناسبة — التطبيق مخصص لمدير العمال فقط
        if ($request->user()?->canAccessDashboard()) {
            return redirect()->route($request->user()->homeRouteName());
        }

        // أي جلسة عميل تُصفَّر حتى لا تمنع دخول مدير العمال
        if ($request->user()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return Inertia::render('Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد غير صحيحة.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ]);

        $throttleKey = 'main-app-login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "محاولات كثيرة. حاول مرة أخرى بعد {$seconds} ثانية.",
            ]);
        }

        if (! Auth::attempt($credentials, false)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isWorker()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'حساب العامل يدخل من تطبيق العمال (/worker-app).',
            ]);
        }

        if (! $user->isWorkersManager()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'تطبيق الإدارة مخصص لمدير العمال فقط.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return Inertia::location('/main-app');
    }

    public function destroy(Request $request): RedirectResponse|HttpResponse
    {
        if ($response = ImpersonationController::interceptLogout($request)) {
            return $response;
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('main.login');
    }
}
