<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthenticaOtpService
{
    public function isConfigured(): bool
    {
        return filled(config('services.authentica.api_key'));
    }

    public function sendOtp(string $phoneE164): void
    {
        $response = $this->client()->post('send-otp', [
            'method' => 'sms',
            'phone' => $phoneE164,
            'template_id' => (int) config('services.authentica.template_id', 31),
        ]);

        if (! $response->successful()) {
            Log::error('Authentica send-otp failed', [
                'phone' => $phoneE164,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new \RuntimeException('فشل إرسال رمز التحقق عبر SMS');
        }
    }

    public function verifyOtp(string $phoneE164, string $otp): bool
    {
        $response = $this->client()->post('verify-otp', [
            'phone' => $phoneE164,
            'otp' => $otp,
        ]);

        if (! $response->successful()) {
            Log::warning('Authentica verify-otp rejected', [
                'phone' => $phoneE164,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        }

        $data = $response->json() ?? [];

        if (array_key_exists('verified', $data)) {
            return $data['verified'] === true;
        }

        return ($data['success'] ?? false) === true;
    }

    public static function formatPhoneE164(string $localPhone): string
    {
        $digits = preg_replace('/\D/', '', $localPhone) ?? '';

        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '+966'.$digits;
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.authentica.base_url'), '/');

        return Http::timeout((int) config('services.authentica.timeout', 30))
            ->acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Authorization' => (string) config('services.authentica.api_key'),
            ])
            ->baseUrl($baseUrl);
    }
}
