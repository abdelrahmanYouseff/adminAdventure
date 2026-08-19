<?php

namespace App\Http\Controllers\MainApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use App\Services\AuthenticaOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthController extends Controller
{
    /** رقم اختبار: يقبل OTP ثابت 0000 بدون إرسال SMS */
    private const FIXED_OTP_PHONE = '535815072';

    private const FIXED_OTP_CODE = '0000';

    public function __construct(private AuthenticaOtpService $authentica) {}

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
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'البريد الإلكتروني أو رقم الجوال مطلوب.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ]);

        $login = trim($credentials['login']);
        $throttleKey = 'main-app-login:'.strtolower($login).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => "محاولات كثيرة. حاول مرة أخرى بعد {$seconds} ثانية.",
            ]);
        }

        $user = $this->resolveUserFromLogin($login);

        if (! $user || ! Hash::check($credentials['password'], (string) $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        $this->assertWorkersManagerCanLogin($user, $throttleKey, 'login');

        RateLimiter::clear($throttleKey);
        Auth::login($user, false);
        $request->session()->regenerate();

        return Inertia::location('/main-app');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^5\d{8}$/'],
        ], [
            'phone.required' => 'رقم الجوال مطلوب.',
            'phone.regex' => 'أدخل رقم جوال سعودي صحيح يبدأ بـ 5.',
        ]);

        $throttleKey = 'main-app-otp-send:'.$data['phone'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'phone' => "محاولات كثيرة. حاول مرة أخرى بعد {$seconds} ثانية.",
            ]);
        }

        $user = $this->findUserByPhone($data['phone']);

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'phone' => 'هذا الرقم غير موجود في النظام، وبالتالي لن يتم إرسال رمز الدخول.',
            ]);
        }

        $this->assertWorkersManagerCanLogin($user, $throttleKey, 'phone');

        $e164 = AuthenticaOtpService::formatPhoneE164($data['phone']);

        // وضع تخطي OTP: تسجيل الدخول مباشرة بمجرد إدخال الرقم
        if ($this->shouldSkipOtp()) {
            RateLimiter::clear($throttleKey);
            Auth::login($user, false);
            $request->session()->regenerate();
            Cache::forget($this->cacheKey($data['phone']));

            return Inertia::location('/main-app');
        }

        if ($this->shouldForceFixedOtpForAll() || $this->isFixedOtpPhone($data['phone'])) {
            Cache::put($this->cacheKey($data['phone']), self::FIXED_OTP_CODE, now()->addMinutes(5));

            if (config('app.debug')) {
                Log::info('Main-app OTP fixed test phone', ['phone' => $e164, 'code' => self::FIXED_OTP_CODE]);
            }

            RateLimiter::clear($throttleKey);

            return back();
        }

        if ($this->authentica->isConfigured()) {
            try {
                $otp = $this->authentica->sendOtp($e164);
                Cache::put($this->cacheKey($data['phone']), $otp, now()->addMinutes(5));
            } catch (\Throwable $e) {
                Log::error('Main-app OTP send failed', [
                    'phone' => $e164,
                    'message' => $e->getMessage(),
                ]);

                RateLimiter::hit($throttleKey, 60);

                throw ValidationException::withMessages([
                    'phone' => 'تعذر إرسال رمز التحقق. حاول مرة أخرى.',
                ]);
            }
        } else {
            $code = '0000';
            Cache::put($this->cacheKey($data['phone']), $code, now()->addMinutes(5));

            if (config('app.debug')) {
                Log::info('Main-app OTP dev fallback', ['phone' => $e164, 'code' => $code]);
            }
        }

        RateLimiter::clear($throttleKey);

        return back();
    }

    public function verifyOtp(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^5\d{8}$/'],
            'code' => ['required', 'string', 'regex:/^\d{4}$/'],
        ], [
            'phone.required' => 'رقم الجوال مطلوب.',
            'phone.regex' => 'أدخل رقم جوال سعودي صحيح يبدأ بـ 5.',
            'code.required' => 'رمز التحقق مطلوب.',
            'code.regex' => 'رمز التحقق يجب أن يكون 4 أرقام.',
        ]);

        $throttleKey = 'main-app-otp-verify:'.$data['phone'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => "محاولات كثيرة. حاول مرة أخرى بعد {$seconds} ثانية.",
            ]);
        }

        $e164 = AuthenticaOtpService::formatPhoneE164($data['phone']);
        $verified = false;

        if ($this->shouldForceFixedOtpForAll() && hash_equals(self::FIXED_OTP_CODE, $data['code'])) {
            $verified = true;
        } elseif ($this->isFixedOtpPhone($data['phone']) && hash_equals(self::FIXED_OTP_CODE, $data['code'])) {
            $verified = true;
        } elseif ($this->authentica->isConfigured()) {
            $verified = $this->authentica->verifyOtp($e164, $data['code']);

            if (! $verified) {
                $cached = Cache::get($this->cacheKey($data['phone']));
                $verified = is_string($cached) && hash_equals($cached, $data['code']);
            }
        } else {
            $cached = Cache::get($this->cacheKey($data['phone']));
            $verified = is_string($cached) && hash_equals($cached, $data['code']);
        }

        if (! $verified) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'code' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ]);
        }

        $user = $this->findUserByPhone($data['phone']);

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'phone' => 'هذا الرقم غير موجود في النظام، وبالتالي لن يتم إرسال رمز الدخول.',
            ]);
        }

        $this->assertWorkersManagerCanLogin($user, $throttleKey, 'phone');

        Cache::forget($this->cacheKey($data['phone']));
        RateLimiter::clear($throttleKey);

        Auth::login($user, false);
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

    private function assertWorkersManagerCanLogin(User $user, string $throttleKey, string $errorField): void
    {
        if ($user->isWorker()) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                $errorField => 'حساب العامل يدخل من تطبيق العمال (/worker-app).',
            ]);
        }

        if (! $user->isWorkersManager()) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                $errorField => 'تطبيق الإدارة مخصص لمدير العمال فقط.',
            ]);
        }
    }

    private function resolveUserFromLogin(string $login): ?User
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', $login)->first();
        }

        $digits = $this->normalizePhoneDigits($login);

        if (! preg_match('/^5\d{8}$/', $digits)) {
            return null;
        }

        return $this->findUserByPhone($digits);
    }

    private function isFixedOtpPhone(string $phone): bool
    {
        return $this->normalizePhoneDigits($phone) === self::FIXED_OTP_PHONE;
    }

    private function shouldSkipOtp(): bool
    {
        return filter_var(env('OTP_SKIP_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function shouldForceFixedOtpForAll(): bool
    {
        // عند تفعيل هذا المتغير، أي رقم OTP = 0000 (للاختبار فقط).
        return filter_var(env('OTP_FORCE_FIXED', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function cacheKey(string $phone): string
    {
        return 'main_app_otp_'.$this->normalizePhoneDigits($phone);
    }

    private function normalizePhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    private function findUserByPhone(string $phone): ?User
    {
        $digits = $this->normalizePhoneDigits($phone);

        $variants = array_unique(array_filter([
            $phone,
            $digits,
            '0'.$digits,
            '+966'.$digits,
            '966'.$digits,
        ]));

        $user = User::query()
            ->whereIn('phone', $variants)
            ->first();

        if ($user) {
            return $user;
        }

        return User::query()
            ->whereNotNull('phone')
            ->whereIn('role', User::STAFF_ROLES)
            ->get()
            ->first(function (User $candidate) use ($digits) {
                return $this->normalizePhoneDigits((string) $candidate->phone) === $digits;
            });
    }
}
