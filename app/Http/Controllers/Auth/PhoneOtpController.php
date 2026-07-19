<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthenticaOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhoneOtpController extends Controller
{
    public function __construct(private AuthenticaOtpService $authentica) {}

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^5\d{8}$/'],
        ]);

        $e164 = AuthenticaOtpService::formatPhoneE164($data['phone']);

        if ($this->authentica->isConfigured()) {
            try {
                $otp = $this->authentica->sendOtp($e164);
                Cache::put($this->cacheKey($data['phone']), $otp, now()->addMinutes(5));
            } catch (\Throwable $e) {
                Log::error('Store OTP send failed', [
                    'phone' => $e164,
                    'message' => $e->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'phone' => 'تعذر إرسال رمز التحقق. حاول مرة أخرى.',
                ]);
            }
        } else {
            $code = '0000';
            Cache::put($this->cacheKey($data['phone']), $code, now()->addMinutes(5));

            if (config('app.debug')) {
                Log::info('Store OTP dev fallback', ['phone' => $e164, 'code' => $code]);
            }
        }

        return back();
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^5\d{8}$/'],
            'code' => ['required', 'string', 'regex:/^\d{4}$/'],
            'redirect' => ['nullable', 'string', 'max:255'],
        ]);

        $e164 = AuthenticaOtpService::formatPhoneE164($data['phone']);
        $verified = false;

        if ($this->authentica->isConfigured()) {
            $verified = $this->authentica->verifyOtp($e164, $data['code']);

            if (! $verified) {
                $cached = Cache::get($this->cacheKey($data['phone']));
                $verified = is_string($cached) && hash_equals($cached, $data['code']);
            }
        } else {
            $cached = Cache::get($this->cacheKey($data['phone']));
            $verified = $cached && $cached === $data['code'];

            if ($verified) {
                Cache::forget($this->cacheKey($data['phone']));
            }
        }

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'رمز التحقق غير صحيح أو منتهي الصلاحية',
            ]);
        }

        Cache::forget($this->cacheKey($data['phone']));

        $user = $this->findUserByPhone($data['phone']);

        if (! $user) {
            $user = $this->createUserFromPhone($data['phone']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $redirect = $data['redirect'] ?? '/home';

        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/home';
        }

        // منع توجيه عملاء المتجر إلى مسارات لوحة التحكم بعد OTP
        if ($this->isDashboardPath($redirect) && ! $user->canAccessDashboard()) {
            $redirect = '/home';
        }

        if (! $user->profile_completed) {
            return redirect()->route('store.complete-registration', ['redirect' => $redirect]);
        }

        return redirect()->to($redirect);
    }

    private function isDashboardPath(string $path): bool
    {
        $path = '/'.ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');

        $blocked = [
            'dashboard',
            'login',
            'register',
            'users',
            'products',
            'categories',
            'packages',
            'orders',
            'invoices',
            'quotations',
            'customers',
            'company-clients',
            'worker-orders',
            'insurance-deposits',
            'settings',
            'worker-app',
        ];

        foreach ($blocked as $prefix) {
            if ($path === '/'.$prefix || str_starts_with($path, '/'.$prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function createUserFromPhone(string $phone): User
    {
        $digits = $this->normalizePhoneDigits($phone);

        return User::create([
            'customer_name' => '',
            'email' => $digits.'@phone.adventureksa.com',
            'password' => Hash::make(Str::random(32)),
            'phone' => $digits,
            'role' => null,
            'profile_completed' => false,
        ]);
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

    private function cacheKey(string $phone): string
    {
        return 'store_otp_'.$phone;
    }

    private function findUserByPhone(string $phone): ?User
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $variants = array_unique(array_filter([
            $phone,
            $digits,
            '0'.$digits,
            '+966'.$digits,
            '966'.$digits,
        ]));

        $user = User::query()->whereIn('phone', $variants)->first();

        if ($user) {
            return $user;
        }

        return User::query()
            ->whereNotNull('phone')
            ->get()
            ->first(function (User $candidate) use ($digits) {
                $stored = preg_replace('/\D/', '', (string) $candidate->phone) ?? '';
                if (str_starts_with($stored, '966')) {
                    $stored = substr($stored, 3);
                }
                if (str_starts_with($stored, '0')) {
                    $stored = substr($stored, 1);
                }

                return $stored === $digits;
            });
    }
}
