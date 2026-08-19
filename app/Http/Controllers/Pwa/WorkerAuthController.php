<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use App\Services\AuthenticaOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WorkerAuthController extends Controller
{
    /** رقم اختبار: يقبل OTP ثابت 0000 بدون إرسال SMS */
    private const FIXED_OTP_PHONE = '535815072';

    private const FIXED_OTP_CODE = '0000';

    public function __construct(private AuthenticaOtpService $authentica) {}

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

    public function sendOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^5\d{8}$/'],
        ], [
            'phone.required' => 'رقم الجوال مطلوب.',
            'phone.regex' => 'أدخل رقم جوال سعودي صحيح يبدأ بـ 5.',
        ]);

        $throttleKey = 'worker-otp-send:'.$data['phone'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'phone' => "محاولات كثيرة. حاول مرة أخرى بعد {$seconds} ثانية.",
            ]);
        }

        $worker = $this->findWorkerByPhone($data['phone']);

        if (! $worker) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'phone' => 'هذا الرقم غير موجود في النظام، وبالتالي لن يتم إرسال رمز الدخول.',
            ]);
        }

        $e164 = AuthenticaOtpService::formatPhoneE164($data['phone']);

        // وضع تخطي OTP: تسجيل الدخول مباشرة بمجرد إدخال الرقم
        if ($this->shouldSkipOtp()) {
            RateLimiter::clear($throttleKey);
            Auth::login($worker, false);
            $request->session()->regenerate();
            Cache::forget($this->cacheKey($data['phone']));

            return redirect('/worker-app');
        }

        if ($this->shouldForceFixedOtpForAll() || $this->isFixedOtpPhone($data['phone'])) {
            Cache::put($this->cacheKey($data['phone']), self::FIXED_OTP_CODE, now()->addMinutes(5));

            if (config('app.debug')) {
                Log::info('Worker OTP fixed test phone', ['phone' => $e164, 'code' => self::FIXED_OTP_CODE]);
            }

            RateLimiter::clear($throttleKey);

            return back();
        }

        if ($this->authentica->isConfigured()) {
            try {
                $otp = $this->authentica->sendOtp($e164);
                Cache::put($this->cacheKey($data['phone']), $otp, now()->addMinutes(5));
            } catch (\Throwable $e) {
                Log::error('Worker OTP send failed', [
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
                Log::info('Worker OTP dev fallback', ['phone' => $e164, 'code' => $code]);
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

        $throttleKey = 'worker-otp-verify:'.$data['phone'].'|'.$request->ip();

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

        $worker = $this->findWorkerByPhone($data['phone']);

        if (! $worker) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'phone' => 'هذا الرقم غير موجود في النظام، وبالتالي لن يتم إرسال رمز الدخول.',
            ]);
        }

        Cache::forget($this->cacheKey($data['phone']));
        RateLimiter::clear($throttleKey);

        Auth::login($worker, false);
        $request->session()->regenerate();

        return Inertia::location('/worker-app');
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

    public function destroy(Request $request): RedirectResponse|HttpResponse
    {
        if ($response = ImpersonationController::interceptLogout($request)) {
            return $response;
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pwa.login');
    }

    private function isFixedOtpPhone(string $phone): bool
    {
        return $this->normalizePhoneDigits($phone) === self::FIXED_OTP_PHONE;
    }

    private function shouldForceFixedOtpForAll(): bool
    {
        // عند تفعيل هذا المتغير، أي رقم OTP = 0000 (للاختبار فقط).
        return (bool) config('otp.force_fixed', false);
    }

    private function shouldSkipOtp(): bool
    {
        return (bool) config('otp.skip_enabled', false);
    }

    private function cacheKey(string $phone): string
    {
        return 'worker_otp_'.$this->normalizePhoneDigits($phone);
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

    private function findWorkerByPhone(string $phone): ?User
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
            ->where('role', User::ROLE_WORKER)
            ->whereIn('phone', $variants)
            ->first();

        if ($user) {
            return $user;
        }

        return User::query()
            ->where('role', User::ROLE_WORKER)
            ->whereNotNull('phone')
            ->get()
            ->first(function (User $candidate) use ($digits) {
                return $this->normalizePhoneDigits((string) $candidate->phone) === $digits;
            });
    }
}
