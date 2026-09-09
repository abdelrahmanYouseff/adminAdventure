<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\PaymentSession;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NoonPaymentGateway
{
    /**
     * @var list<string>
     */
    public const SUCCESS_STATUSES = [
        'CAPTURED',
        'AUTHORIZED',
        'PARTIALLY_CAPTURED',
        'SUCCESS',
        'PAID',
        'COMPLETED',
    ];

    public function isConfigured(): bool
    {
        $noon = config('services.noon', []);

        if (empty($noon['api_key'])) {
            return false;
        }

        return (! empty($noon['business_id']) && ! empty($noon['app_id']))
            || trim((string) ($noon['auth_header'] ?? '')) !== '';
    }

    public function isRealNoonOrderId(?string $noonOrderId, ?string $merchantReference = null): bool
    {
        $id = trim((string) $noonOrderId);

        if ($id === '') {
            return false;
        }

        if (str_starts_with(strtoupper($id), 'MOCK-')) {
            return false;
        }

        if ($merchantReference !== null && strcasecmp($id, trim($merchantReference)) === 0) {
            return false;
        }

        return true;
    }

    public function noonIdFromNotes(?string $notes): ?string
    {
        if (! is_string($notes) || $notes === '') {
            return null;
        }

        if (preg_match('/\(([^)]+)\)\s*$/', $notes, $matches) !== 1) {
            return null;
        }

        $id = trim($matches[1]);

        return $id !== '' ? $id : null;
    }

    public function resolveNoonOrderId(Order $order, ?OrderPaymentReceipt $receipt = null, ?PaymentSession $session = null): ?string
    {
        $merchantReference = $order->order_number;

        foreach ([
            $session?->noon_order_id,
            $order->payment_id,
            $receipt ? $this->noonIdFromNotes($receipt->notes) : null,
        ] as $candidate) {
            if ($this->isRealNoonOrderId(is_string($candidate) ? $candidate : null, $merchantReference)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $noonOrderIds
     * @return array<string, bool>
     */
    public function capturedMap(array $noonOrderIds): array
    {
        $noonOrderIds = array_values(array_unique(array_filter(
            $noonOrderIds,
            fn ($id) => $this->isRealNoonOrderId(is_string($id) ? $id : null),
        )));

        if ($noonOrderIds === []) {
            return [];
        }

        if (! $this->isConfigured()) {
            return array_fill_keys($noonOrderIds, true);
        }

        $result = [];
        $missing = [];

        foreach ($noonOrderIds as $id) {
            $cached = Cache::get($this->cacheKey($id));
            if (is_bool($cached)) {
                $result[$id] = $cached;
            } else {
                $missing[] = $id;
            }
        }

        if ($missing !== []) {
            foreach ($this->fetchCaptured($missing) as $id => $ok) {
                Cache::put($this->cacheKey($id), $ok, now()->addMinutes(10));
                $result[$id] = $ok;
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $noonOrderIds
     * @return array<string, bool>
     */
    private function fetchCaptured(array $noonOrderIds): array
    {
        $noon = config('services.noon', []);
        $base = rtrim((string) ($noon['api_url'] ?? ''), '/');
        $authHeader = $this->authHeader($noon);
        $apiKey = (string) ($noon['api_key'] ?? '');

        if ($base === '' || $authHeader === '' || $apiKey === '') {
            return array_fill_keys($noonOrderIds, false);
        }

        $headers = [
            'Authorization' => $authHeader,
            'Accept' => 'application/json',
            'x-api-key' => $apiKey,
        ];

        $responses = Http::pool(function (Pool $pool) use ($noonOrderIds, $base, $headers) {
            foreach ($noonOrderIds as $id) {
                $pool->as($id)
                    ->withHeaders($headers)
                    ->timeout(15)
                    ->get($base.'/order/'.$id);
            }
        });

        $result = [];

        foreach ($noonOrderIds as $id) {
            $response = $responses[$id] ?? null;

            if (! $response || $response instanceof \Throwable) {
                Log::warning('Noon receipt lookup failed', [
                    'noon_order_id' => $id,
                    'message' => $response instanceof \Throwable ? $response->getMessage() : 'empty response',
                ]);
                $result[$id] = false;

                continue;
            }

            if ($response->status() === 404) {
                $result[$id] = false;

                continue;
            }

            if (! $response->successful()) {
                Log::warning('Noon receipt lookup HTTP failed', [
                    'noon_order_id' => $id,
                    'status' => $response->status(),
                ]);
                $result[$id] = false;

                continue;
            }

            $data = $response->json() ?? [];
            $status = strtoupper(trim((string) (
                $data['result']['order']['status']
                ?? $data['order']['status']
                ?? $data['result']['status']
                ?? ''
            )));

            $result[$id] = in_array($status, self::SUCCESS_STATUSES, true);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $noon
     */
    private function authHeader(array $noon): string
    {
        if (! empty($noon['business_id']) && ! empty($noon['app_id']) && ! empty($noon['api_key'])) {
            return 'Key '.base64_encode($noon['business_id'].'.'.$noon['app_id'].':'.$noon['api_key']);
        }

        $authHeader = trim((string) ($noon['auth_header'] ?? ''));

        return $authHeader !== ''
            ? (string) preg_replace('/^Key_/', 'Key ', $authHeader)
            : '';
    }

    private function cacheKey(string $noonOrderId): string
    {
        return 'noon-receipt-captured:'.$noonOrderId;
    }
}
