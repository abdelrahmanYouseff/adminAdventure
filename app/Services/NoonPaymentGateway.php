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

    public function isSuccessStatus(?string $status): bool
    {
        return in_array(strtoupper(trim((string) $status)), self::SUCCESS_STATUSES, true);
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
     * Pull successful Noon orders by gateway id and merchant reference.
     *
     * @param  list<string>  $noonOrderIds
     * @param  list<string>  $merchantReferences
     * @return array<string, array<string, mixed>>
     */
    public function fetchSuccessfulOrders(array $noonOrderIds, array $merchantReferences = []): array
    {
        $noonOrderIds = $this->uniqueIds($noonOrderIds);
        $merchantReferences = $this->uniqueIds($merchantReferences);

        if (! $this->isConfigured()) {
            return [];
        }

        $orders = [];

        foreach ($this->fetchByIds($noonOrderIds) as $id => $order) {
            $orders[$id] = $order;
        }

        foreach ($this->fetchByReferences($merchantReferences) as $id => $order) {
            $orders[$id] = $order;
        }

        return array_filter(
            $orders,
            fn (array $order) => $this->isSuccessStatus($order['status'] ?? null),
        );
    }

    public function fetchSuccessfulOrder(string $noonOrderId): ?array
    {
        $orders = $this->fetchSuccessfulOrders([$noonOrderId]);

        return $orders[$noonOrderId] ?? null;
    }

    /**
     * @param  list<string>  $noonOrderIds
     * @return array<string, bool>
     */
    public function capturedMap(array $noonOrderIds): array
    {
        $orders = $this->fetchSuccessfulOrders($noonOrderIds);

        $map = [];
        foreach ($this->uniqueIds($noonOrderIds) as $id) {
            $map[$id] = isset($orders[$id]);
        }

        return $map;
    }

    /**
     * @param  list<string>  $noonOrderIds
     * @return array<string, array<string, mixed>>
     */
    private function fetchByIds(array $noonOrderIds): array
    {
        $base = $this->paymentBaseUrl();
        if ($base === '' || $noonOrderIds === []) {
            return [];
        }

        return $this->poolGet(
            $noonOrderIds,
            fn (string $id) => $base.'/order/'.$id,
            fn (string $id) => $this->cacheKey('id', $id),
        );
    }

    /**
     * @param  list<string>  $merchantReferences
     * @return array<string, array<string, mixed>>
     */
    private function fetchByReferences(array $merchantReferences): array
    {
        $base = $this->paymentBaseUrl();
        if ($base === '' || $merchantReferences === []) {
            return [];
        }

        return $this->poolGet(
            $merchantReferences,
            fn (string $reference) => $base.'/order/getbyreference/'.rawurlencode($reference),
            fn (string $reference) => $this->cacheKey('ref', $reference),
        );
    }

    /**
     * @param  list<string>  $keys
     * @param  callable(string): string  $urlFor
     * @param  callable(string): string  $cacheKeyFor
     * @return array<string, array<string, mixed>>
     */
    private function poolGet(array $keys, callable $urlFor, callable $cacheKeyFor): array
    {
        $result = [];
        $missing = [];

        foreach ($keys as $key) {
            $cached = Cache::get($cacheKeyFor($key));
            if (is_array($cached) || $cached === false) {
                if (is_array($cached) && isset($cached['id'])) {
                    $result[(string) $cached['id']] = $cached;
                }

                continue;
            }

            $missing[] = $key;
        }

        foreach (array_chunk($missing, 8) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $urlFor) {
                foreach ($chunk as $key) {
                    $pool->as($key)
                        ->withHeaders($this->headers())
                        ->timeout(15)
                        ->get($urlFor($key));
                }
            });

            foreach ($chunk as $key) {
                $response = $responses[$key] ?? null;
                $parsed = null;

                if ($response && ! $response instanceof \Throwable && $response->successful()) {
                    $parsed = $this->parseOrder($response->json() ?? []);
                } elseif ($response instanceof \Throwable) {
                    Log::warning('Noon order lookup failed', [
                        'key' => $key,
                        'message' => $response->getMessage(),
                    ]);
                }

                Cache::put($cacheKeyFor($key), $parsed ?: false, now()->addMinutes(10));

                if (is_array($parsed) && isset($parsed['id'])) {
                    $result[(string) $parsed['id']] = $parsed;
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function parseOrder(array $data): ?array
    {
        $order = $data['result']['order'] ?? $data['order'] ?? null;
        if (! is_array($order)) {
            return null;
        }

        $id = trim((string) ($order['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        $amount = $order['totalCapturedAmount']
            ?? $order['capturedAmount']
            ?? $order['amount']
            ?? 0;

        return [
            'id' => $id,
            'status' => strtoupper(trim((string) ($order['status'] ?? ''))),
            'amount' => round((float) $amount, 2),
            'currency' => strtoupper((string) ($order['currency'] ?? 'SAR')) ?: 'SAR',
            'reference' => trim((string) ($order['reference'] ?? '')),
            'name' => trim((string) ($order['name'] ?? '')),
            'created_at' => $order['creationTime'] ?? $order['created'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $noon = config('services.noon', []);

        return [
            'Authorization' => $this->authHeader($noon),
            'Accept' => 'application/json',
            'x-api-key' => (string) ($noon['api_key'] ?? ''),
        ];
    }

    private function paymentBaseUrl(): string
    {
        return rtrim((string) (config('services.noon.api_url') ?? ''), '/');
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

    /**
     * @param  list<string|null>  $values
     * @return list<string>
     */
    private function uniqueIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = trim((string) $value);
            if ($id !== '' && ! str_starts_with(strtoupper($id), 'MOCK-')) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function cacheKey(string $type, string $id): string
    {
        return 'noon-order-'.$type.':'.$id;
    }
}
