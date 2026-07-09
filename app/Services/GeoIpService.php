<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoIpService
{
    /**
     * @return array{country: string, country_code: string|null, city: string|null}
     */
    public function lookup(?string $ip): array
    {
        if (empty($ip) || $this->isPrivateIp($ip)) {
            return [
                'country' => 'محلي',
                'country_code' => 'LO',
                'city' => null,
            ];
        }

        return Cache::remember("geoip:{$ip}", now()->addDay(), function () use ($ip) {
            try {
                $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,country,countryCode,city',
                ]);

                if (! $response->successful()) {
                    return $this->unknown();
                }

                $data = $response->json();

                if (($data['status'] ?? '') !== 'success') {
                    return $this->unknown();
                }

                return [
                    'country' => $data['country'] ?? 'غير معروف',
                    'country_code' => $data['countryCode'] ?? null,
                    'city' => $data['city'] ?? null,
                ];
            } catch (\Throwable) {
                return $this->unknown();
            }
        });
    }

    private function isPrivateIp(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * @return array{country: string, country_code: string|null, city: string|null}
     */
    private function unknown(): array
    {
        return [
            'country' => 'غير معروف',
            'country_code' => null,
            'city' => null,
        ];
    }
}
