<?php

namespace App\Services\Inbox;

use App\Models\InboxMediaUpload;
use App\Models\InboxSetting;
use App\Support\MediaStorage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppGraphClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.access_token'));
    }

    public function phoneNumberId(): string
    {
        return (string) config('services.whatsapp.phone_number_id');
    }

    public function belongsToThisSystem(?string $phoneNumberId): bool
    {
        $configured = $this->phoneNumberId();

        return $configured !== '' && $phoneNumberId === $configured;
    }

    public function graphVersion(): string
    {
        return (string) config('services.whatsapp.graph_version', 'v21.0');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int}
     */
    public function sendMessage(array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message_id' => null, 'error' => 'واتساب غير مفعّل', 'status' => null];
        }

        $response = $this->client()->post($this->messagesUrl(), array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
        ], $payload));

        return $this->parseSendResponse($response);
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int}
     */
    public function sendText(string $to, string $body): array
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $body,
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int}
     */
    public function sendTemplate(string $to, string $name, string $language, array $components = []): array
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $name,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int}
     */
    public function sendMedia(string $to, string $type, string $link, ?string $caption = null, ?string $filename = null): array
    {
        $media = ['link' => $link];

        if ($caption) {
            $media['caption'] = $caption;
        }

        if ($filename && $type === 'document') {
            $media['filename'] = $filename;
        }

        return $this->sendMessage([
            'to' => $to,
            'type' => $type,
            $type => $media,
        ]);
    }

    /**
     * @return array{success: bool, templates: list<array<string, mixed>>, error: ?string}
     */
    public function approvedTemplates(): array
    {
        $cached = Cache::get('inbox:wa:approved_templates');
        if (is_array($cached) && ($cached['success'] ?? false)) {
            return $cached;
        }

        $result = $this->fetchApprovedTemplates();

        if ($result['success']) {
            Cache::put('inbox:wa:approved_templates', $result, 300);
        }

        return $result;
    }

    /**
     * @return array{success: bool, templates: list<array<string, mixed>>, error: ?string}
     */
    private function fetchApprovedTemplates(): array
    {
        $waba = $this->resolveWabaId();

        if (! $waba['success'] || ! $waba['waba_id']) {
            return ['success' => false, 'templates' => [], 'error' => $waba['error']];
        }

        $templates = [];
        $url = "https://graph.facebook.com/{$this->graphVersion()}/{$waba['waba_id']}/message_templates";
        $params = [
            'status' => 'APPROVED',
            'limit' => 100,
            'fields' => 'name,language,status,category,components',
        ];

        do {
            $response = $this->client()->get($url, $params);

            if (! $response->successful()) {
                $error = (string) ($response->json('error.message') ?? $response->body());

                if (str_contains($error, 'whatsapp_business_account') || str_contains($error, 'non-existing field')) {
                    $error = 'أضف معرّف حساب واتساب للأعمال (WABA ID) من إعدادات واتساب. تجده في Meta Business Suite ← WhatsApp Accounts.';
                }

                return ['success' => false, 'templates' => [], 'error' => $error];
            }

            foreach ($response->json('data', []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $templates[] = [
                    'name' => (string) ($row['name'] ?? ''),
                    'language' => (string) ($row['language'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'category' => (string) ($row['category'] ?? ''),
                    'components' => $row['components'] ?? [],
                ];
            }

            $next = $response->json('paging.next');
            if (is_string($next) && $next !== '') {
                $url = $next;
                $params = [];
            } else {
                $url = '';
            }
        } while ($url !== '');

        usort($templates, fn (array $a, array $b) => [$a['name'], $a['language']] <=> [$b['name'], $b['language']]);

        return ['success' => true, 'templates' => $templates, 'error' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public function phoneQuality(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'واتساب غير مفعّل'];
        }

        $response = $this->client()->get(
            "https://graph.facebook.com/{$this->graphVersion()}/{$this->phoneNumberId()}",
            [
                'fields' => 'display_phone_number,verified_name,whatsapp_business_manager_messaging_limit,quality_rating,throughput,health_status',
            ]
        );

        if (! $response->successful()) {
            return [
                'success' => false,
                'error' => (string) ($response->json('error.message') ?? $response->body()),
            ];
        }

        return [
            'success' => true,
            'display_phone_number' => $response->json('display_phone_number'),
            'verified_name' => $response->json('verified_name'),
            'messaging_limit' => $response->json('whatsapp_business_manager_messaging_limit'),
            'quality_rating' => $response->json('quality_rating'),
            'throughput' => $response->json('throughput'),
            'health_status' => $response->json('health_status'),
        ];
    }

    public function downloadInboundMedia(string $mediaId, ?string $mimeType = null, ?string $filename = null): ?InboxMediaUpload
    {
        if (! $this->isConfigured() || $mediaId === '') {
            return null;
        }

        $meta = $this->client()->get("https://graph.facebook.com/{$this->graphVersion()}/{$mediaId}");

        if (! $meta->successful()) {
            Log::warning('Inbox WhatsApp media meta failed', [
                'media_id' => $mediaId,
                'body' => $meta->json() ?? $meta->body(),
            ]);

            return null;
        }

        $url = (string) $meta->json('url');
        $mime = $mimeType ?: (string) ($meta->json('mime_type') ?? 'application/octet-stream');

        if ($url === '') {
            return null;
        }

        $binary = $this->client()->get($url);

        if (! $binary->successful()) {
            Log::warning('Inbox WhatsApp media download failed', ['media_id' => $mediaId]);

            return null;
        }

        $contents = $binary->body();
        $uuid = (string) Str::uuid();
        $ext = $this->extensionFromMime($mime, $filename);
        $path = "inbox/media/{$uuid}.{$ext}";
        $disk = MediaStorage::DISK;

        Storage::disk($disk)->put($path, $contents, 'public');

        return InboxMediaUpload::query()->create([
            'uuid' => $uuid,
            'disk' => $disk,
            'path' => $path,
            'mime' => $mime,
            'size' => strlen($contents),
            'original_name' => $filename,
            'last_used_at' => now(),
        ]);
    }

    public function uploadMedia(string $binary, string $mimeType, string $filename): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken((string) config('services.whatsapp.access_token'))
            ->attach('file', $binary, $filename, ['Content-Type' => $mimeType])
            ->post("https://graph.facebook.com/{$this->graphVersion()}/{$this->phoneNumberId()}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if (! $response->successful()) {
            Log::error('Inbox WhatsApp media upload failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return null;
        }

        $id = $response->json('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @return array{success: bool, waba_id: ?string, error: ?string}
     */
    public function resolveWabaId(): array
    {
        $configured = trim((string) InboxSetting::current()->resolvedWabaId());

        if ($configured !== '') {
            return ['success' => true, 'waba_id' => $configured, 'error' => null];
        }

        if (! $this->isConfigured()) {
            return ['success' => false, 'waba_id' => null, 'error' => 'واتساب غير مفعّل'];
        }

        $fromToken = $this->wabaIdFromAccessToken();
        if ($fromToken) {
            return ['success' => true, 'waba_id' => $fromToken, 'error' => null];
        }

        $fromAssigned = $this->wabaIdFromAssignedAccounts();
        if ($fromAssigned) {
            return ['success' => true, 'waba_id' => $fromAssigned, 'error' => null];
        }

        $fromBusinesses = $this->wabaIdFromOwnedBusinesses();
        if ($fromBusinesses) {
            return ['success' => true, 'waba_id' => $fromBusinesses, 'error' => null];
        }

        return [
            'success' => false,
            'waba_id' => null,
            'error' => 'أضف معرّف حساب واتساب للأعمال (WABA ID) من إعدادات واتساب. تجده في Meta Business Suite ← WhatsApp Accounts.',
        ];
    }

    private function wabaIdFromAccessToken(): ?string
    {
        $token = (string) config('services.whatsapp.access_token');

        $response = $this->client()->get(
            "https://graph.facebook.com/{$this->graphVersion()}/debug_token",
            ['input_token' => $token]
        );

        if (! $response->successful()) {
            return null;
        }

        $scopes = $response->json('data.granular_scopes') ?? [];

        if (! is_array($scopes)) {
            return null;
        }

        foreach ($scopes as $scope) {
            if (! is_array($scope)) {
                continue;
            }

            $name = (string) ($scope['scope'] ?? '');

            if (! in_array($name, ['whatsapp_business_management', 'whatsapp_business_messaging'], true)) {
                continue;
            }

            $ids = $scope['target_ids'] ?? [];

            if (is_array($ids) && isset($ids[0]) && is_string($ids[0]) && $ids[0] !== '') {
                return $ids[0];
            }
        }

        return null;
    }

    private function wabaIdFromAssignedAccounts(): ?string
    {
        $response = $this->client()->get(
            "https://graph.facebook.com/{$this->graphVersion()}/me/assigned_whatsapp_business_accounts",
            ['fields' => 'id,name']
        );

        $id = $response->json('data.0.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function wabaIdFromOwnedBusinesses(): ?string
    {
        $businesses = $this->client()->get(
            "https://graph.facebook.com/{$this->graphVersion()}/me/businesses",
            ['fields' => 'id,name']
        );

        foreach ($businesses->json('data', []) as $business) {
            if (! is_array($business) || empty($business['id'])) {
                continue;
            }

            foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
                $response = $this->client()->get(
                    "https://graph.facebook.com/{$this->graphVersion()}/{$business['id']}/{$edge}",
                    ['fields' => 'id,name']
                );

                $id = $response->json('data.0.id');

                if (is_string($id) && $id !== '') {
                    return $id;
                }
            }
        }

        return null;
    }

    public function verifyToken(): string
    {
        return InboxSetting::current()->resolvedVerifyToken();
    }

    public function appSecret(): string
    {
        return InboxSetting::current()->resolvedAppSecret();
    }

    private function messagesUrl(): string
    {
        return "https://graph.facebook.com/{$this->graphVersion()}/{$this->phoneNumberId()}/messages";
    }

    private function client(): PendingRequest
    {
        return Http::timeout(30)
            ->acceptJson()
            ->withToken((string) config('services.whatsapp.access_token'));
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int}
     */
    private function parseSendResponse(Response $response): array
    {
        $messageId = $response->json('messages.0.id');

        if ($response->successful() && is_string($messageId) && $messageId !== '') {
            return [
                'success' => true,
                'message_id' => $messageId,
                'error' => null,
                'status' => $response->status(),
            ];
        }

        $errorBody = $response->json() ?? $response->body();
        $errorMessage = is_array($errorBody)
            ? (string) ($errorBody['error']['message'] ?? json_encode($errorBody, JSON_UNESCAPED_UNICODE))
            : (string) $errorBody;

        Log::error('Inbox WhatsApp send failed', [
            'status' => $response->status(),
            'body' => $errorBody,
        ]);

        return [
            'success' => false,
            'message_id' => null,
            'error' => $errorMessage !== '' ? $errorMessage : 'فشل إرسال واتساب',
            'status' => $response->status(),
        ];
    }

    private function extensionFromMime(string $mime, ?string $filename): string
    {
        if ($filename && str_contains($filename, '.')) {
            return strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        }

        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/amr' => 'amr',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
