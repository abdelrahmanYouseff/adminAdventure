<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthenticaOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
                $this->authentica->sendOtp($e164);
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
            'code' => ['required', 'string', 'regex:/^\d{4,6}$/'],
            'redirect' => ['nullable', 'string', 'max:255'],
        ]);

        $e164 = AuthenticaOtpService::formatPhoneE164($data['phone']);
        $verified = false;

        if ($this->authentica->isConfigured()) {
            $verified = $this->authentica->verifyOtp($e164, $data['code']);
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

        $user = $this->findUserByPhone($data['phone']);

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الجوال غير مسجل',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $redirect = $data['redirect'] ?? '/home';

        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/home';
        }

        return redirect()->to($redirect);
    }

    private function cacheKey(string $phone): string
    {
        return 'store_otp_'.$phone;
    }

    private function findUserByPhone(string $phone): ?User
    {
        $variants = [
            $phone,
            '0'.$phone,
            '+966'.$phone,
            '966'.$phone,
        ];

        return User::query()
            ->whereIn('phone', $variants)
            ->first();
    }
}
