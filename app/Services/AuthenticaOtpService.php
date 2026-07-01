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

    /**
     * Send OTP via SMS. Returns the OTP code that was sent (for local fallback).
     */
    public function sendOtp(string $phoneE164): string
    {
        $length = (int) config('services.authentica.otp_length', 4);
        $otp = $this->generateOtp($length);

        $response = $this->client()->post('send-otp', [
            'method' => 'sms',
            'phone' => $phoneE164,
            'template_id' => (int) config('services.authentica.template_id', 31),
            'otp' => $otp,
        ]);

        if (! $response->successful()) {
            Log::error('Authentica send-otp failed', [
                'phone' => $phoneE164,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new \RuntimeException('فشل إرسال رمز التحقق عبر SMS');
        }

        Log::info('Authentica send-otp success', [
            'phone' => $phoneE164,
            'response' => $response->json(),
        ]);

        return $otp;
    }

    public function verifyOtp(string $phoneE164, string $otp): bool
    {
        $response = $this->client()->post('verify-otp', [
            'phone' => $phoneE164,
            'otp' => $otp,
        ]);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            Log::warning('Authentica verify-otp HTTP error', [
                'phone' => $phoneE164,
                'status' => $response->status(),
                'body' => $data,
            ]);

            return false;
        }

        $verified = $this->parseVerifiedResponse($data);

        Log::info('Authentica verify-otp response', [
            'phone' => $phoneE164,
            'verified' => $verified,
            'body' => $data,
        ]);

        return $verified;
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

    private function generateOtp(int $length): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parseVerifiedResponse(array $data): bool
    {
        if (array_key_exists('verified', $data)) {
            return $data['verified'] === true;
        }

        if (array_key_exists('status', $data)) {
            return $data['status'] === true;
        }

        if (($data['success'] ?? false) === true) {
            $message = strtolower((string) ($data['message'] ?? ''));

            if ($message !== '' && (str_contains($message, 'invalid') || str_contains($message, 'expired') || str_contains($message, 'fail'))) {
                return false;
            }

            return true;
        }

        $inner = $data['data'] ?? null;
        if (is_array($inner)) {
            return $this->parseVerifiedResponse($inner);
        }

        return false;
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
