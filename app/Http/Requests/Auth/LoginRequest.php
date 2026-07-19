<?php

namespace App\Http\Requests\Auth;

use App\Support\AuthPath;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'redirect' => ['nullable', 'string'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();
        $storeLogin = AuthPath::isSafeStoreRedirect($this->input('redirect'));

        // العمال: تطبيق العمال فقط
        if ($user?->isWorker()) {
            $this->rejectLogin('حساب العامل يدخل فقط من تطبيق العمال (/worker-app).');
        }

        // العملاء: ممنوعون من لوحة التحكم، ومسموح لهم فقط من تسجيل دخول المتجر
        if (! $user?->canAccessDashboard()) {
            if ($storeLogin) {
                RateLimiter::clear($this->throttleKey());

                return;
            }

            $this->rejectLogin('حساب العملاء غير مصرح له بالدخول إلى لوحة التحكم. سجّل الدخول من الموقع كعميل.');
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function rejectLogin(string $message): void
    {
        Auth::logout();

        if ($this->hasSession()) {
            $this->session()->invalidate();
            $this->session()->regenerateToken();
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => $message,
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
