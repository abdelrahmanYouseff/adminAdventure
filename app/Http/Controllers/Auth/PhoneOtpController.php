<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PhoneOtpController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^5\d{8}$/'],
        ]);

        $code = '0000';

        Cache::put($this->cacheKey($data['phone']), $code, now()->addMinutes(5));

        if (config('app.debug')) {
            Log::info('Store OTP code', ['phone' => '+966'.$data['phone'], 'code' => $code]);
        }

        return back();
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^5\d{8}$/'],
            'code' => ['required', 'string', 'size:4'],
            'redirect' => ['nullable', 'string', 'max:255'],
        ]);

        $cached = Cache::get($this->cacheKey($data['phone']));

        if (! $cached || $cached !== $data['code']) {
            throw ValidationException::withMessages([
                'code' => 'رمز التحقق غير صحيح',
            ]);
        }

        Cache::forget($this->cacheKey($data['phone']));

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
