<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudService
{
    public function isConfigured(): bool
    {
        if (! config('services.whatsapp.enabled', false)
            || ! filled(config('services.whatsapp.phone_number_id'))
            || ! filled(config('services.whatsapp.access_token'))) {
            return false;
        }

        return $this->recipientNumbers() !== [];
    }

    public function sendText(string $to, string $body): void
    {
        if (! $this->isConfigured()) {
            Log::info('WhatsApp notification skipped: not configured');

            return;
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

        if (! $response->successful()) {
            Log::error('WhatsApp send failed', [
                'to' => $recipient,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new \RuntimeException('فشل إرسال رسالة واتساب');
        }

        Log::info('WhatsApp message sent', [
            'to' => $recipient,
            'message_id' => $response->json('messages.0.id'),
        ]);
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
                return array_values(array_unique(array_map(
                    fn (string $number) => self::normalizePhone($number),
                    $fromDb
                )));
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

        return array_values(array_unique($normalized));
    }

    public function sendToAllRecipients(string $body): void
    {
        $recipients = $this->recipientNumbers();
        $successCount = 0;

        foreach ($recipients as $recipient) {
            try {
                $this->sendText($recipient, $body);
                $successCount++;
            } catch (\Throwable $e) {
                Log::warning('WhatsApp send to recipient failed', [
                    'to' => $recipient,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($successCount === 0) {
            throw new \RuntimeException('فشل إرسال رسالة واتساب لجميع المستلمين');
        }
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
