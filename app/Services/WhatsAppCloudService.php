<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudService
{
    public function isConfigured(): bool
    {
        return \App\Support\WhatsAppConfig::isReady();
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int}
     */
    public function sendTextWithReport(string $to, string $body): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'واتساب غير مفعّل أو الإعدادات ناقصة',
                'status' => null,
            ];
        }

        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $version = (string) config('services.whatsapp.graph_version', 'v21.0');
        $recipient = self::normalizePhone($to);

        $response = $this->client()->post(
            "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages",
            [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => true,
                    'body' => $body,
                ],
            ]
        );

        if ($response->successful()) {
            Log::info('WhatsApp message sent', [
                'to' => $recipient,
                'message_id' => $response->json('messages.0.id'),
            ]);

            return [
                'success' => true,
                'message_id' => $response->json('messages.0.id'),
                'error' => null,
                'status' => $response->status(),
            ];
        }

        $errorBody = $response->json() ?? $response->body();
        $errorMessage = is_array($errorBody)
            ? ($errorBody['error']['message'] ?? json_encode($errorBody, JSON_UNESCAPED_UNICODE))
            : (string) $errorBody;

        Log::error('WhatsApp send failed', [
            'to' => $recipient,
            'status' => $response->status(),
            'body' => $errorBody,
        ]);

        return [
            'success' => false,
            'message_id' => null,
            'error' => $errorMessage,
            'status' => $response->status(),
        ];
    }

    public function sendText(string $to, string $body): void
    {
        $result = $this->sendTextWithReport($to, $body);

        if (! $result['success']) {
            throw new \RuntimeException($result['error'] ?? 'فشل إرسال رسالة واتساب');
        }
    }

    public function sendToDefaultRecipient(string $body): void
    {
        $this->sendToAllRecipients($body);
    }

    /**
     * @return list<string>
     */
    public function recipientNumbers(): array
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('whatsapp_notification_recipients')) {
            $fromDb = \App\Models\WhatsappNotificationRecipient::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->pluck('phone')
                ->all();

            if ($fromDb !== []) {
                return $this->filterExcluded(array_values(array_unique(array_map(
                    fn (string $number) => self::normalizePhone($number),
                    $fromDb
                ))));
            }
        }

        $numbers = array_filter([
            (string) config('services.whatsapp.to'),
        ]);

        $extra = (string) config('services.whatsapp.extra_to', '');
        if ($extra !== '') {
            foreach (explode(',', $extra) as $number) {
                $trimmed = trim($number);
                if ($trimmed !== '') {
                    $numbers[] = $trimmed;
                }
            }
        }

        $normalized = array_map(fn (string $number) => self::normalizePhone($number), $numbers);

        return $this->filterExcluded(array_values(array_unique($normalized)));
    }

    /**
     * @param  list<string>  $numbers
     * @return list<string>
     */
    private function filterExcluded(array $numbers): array
    {
        $excluded = collect(explode(',', (string) config('services.whatsapp.exclude_to', '')))
            ->map(fn (string $n) => self::normalizePhone(trim($n)))
            ->filter()
            ->all();

        if ($excluded === []) {
            return $numbers;
        }

        return array_values(array_filter(
            $numbers,
            fn (string $number) => ! in_array($number, $excluded, true)
        ));
    }

    public function sendToAllRecipients(string $body): void
    {
        $results = $this->sendToAllRecipientsWithReport($body);

        $successCount = count(array_filter($results, fn (array $r) => $r['success']));

        if ($successCount === 0) {
            $errors = collect($results)
                ->map(fn (array $r) => "{$r['to']}: {$r['detail']}")
                ->implode(' | ');

            throw new \RuntimeException('فشل إرسال رسالة واتساب لجميع المستلمين — '.$errors);
        }
    }

    /**
     * @return list<array{to: string, success: bool, detail: string}>
     */
    public function sendToAllRecipientsWithReport(string $body): array
    {
        $results = [];

        foreach ($this->recipientNumbers() as $recipient) {
            $report = $this->sendTextWithReport($recipient, $body);

            $results[] = [
                'to' => $recipient,
                'success' => $report['success'],
                'detail' => $report['success']
                    ? ('message_id='.($report['message_id'] ?? '—'))
                    : ('HTTP '.($report['status'] ?? '—').' — '.($report['error'] ?? 'خطأ غير معروف')),
            ];
        }

        return $results;
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '966'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            $digits = '966'.$digits;
        }

        return $digits;
    }

    private function client(): PendingRequest
    {
        return Http::timeout(30)
            ->acceptJson()
            ->withToken((string) config('services.whatsapp.access_token'));
    }
}
