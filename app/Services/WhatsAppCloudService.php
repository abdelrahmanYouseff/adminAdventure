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

    public function isApiConfigured(): bool
    {
        return \App\Support\WhatsAppConfig::isApiConfigured();
    }

    /**
     * Upload a binary file to WhatsApp Cloud media storage.
     *
     * @return array{success: bool, media_id: ?string, error: ?string, status: ?int}
     */
    public function uploadMedia(string $binary, string $mimeType, string $filename): array
    {
        if (! $this->isApiConfigured()) {
            return [
                'success' => false,
                'media_id' => null,
                'error' => 'واتساب غير مفعّل أو الإعدادات ناقصة',
                'status' => null,
            ];
        }

        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $version = (string) config('services.whatsapp.graph_version', 'v21.0');

        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken((string) config('services.whatsapp.access_token'))
            ->attach('file', $binary, $filename, ['Content-Type' => $mimeType])
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if ($response->successful()) {
            $mediaId = $response->json('id');

            return [
                'success' => is_string($mediaId) && $mediaId !== '',
                'media_id' => is_string($mediaId) ? $mediaId : null,
                'error' => is_string($mediaId) && $mediaId !== '' ? null : 'Meta لم تُرجع معرّف الوسائط',
                'status' => $response->status(),
            ];
        }

        $errorBody = $response->json() ?? $response->body();
        $errorMessage = is_array($errorBody)
            ? ($errorBody['error']['message'] ?? json_encode($errorBody, JSON_UNESCAPED_UNICODE))
            : (string) $errorBody;

        Log::error('WhatsApp media upload failed', [
            'status' => $response->status(),
            'body' => $errorBody,
            'filename' => $filename,
        ]);

        return [
            'success' => false,
            'media_id' => null,
            'error' => $errorMessage,
            'status' => $response->status(),
        ];
    }

    /**
     * Send the approved delivery-note Meta template with a PDF header.
     *
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int, mode: string, to: string}
     */
    public function sendDeliveryNoteTemplate(
        string $to,
        string $mediaId,
        string $filename,
        ?string $urlButtonSuffix = null,
        ?string $bodyText = null,
    ): array {
        $resolved = $this->resolveDeliveryNoteTemplate();

        if (($resolved['error'] ?? null) && ! ($resolved['name'] ?? '')) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => $resolved['error'],
                'status' => null,
                'mode' => 'template:missing',
                'to' => self::normalizePhone($to),
            ];
        }

        if (($resolved['error'] ?? null) && filled($resolved['status'] ?? null) && strtoupper((string) $resolved['status']) !== 'APPROVED') {
            return [
                'success' => false,
                'message_id' => null,
                'error' => $resolved['error'],
                'status' => null,
                'mode' => 'template:'.$resolved['name'].'/'.$resolved['language'],
                'to' => self::normalizePhone($to),
            ];
        }

        $template = (string) ($resolved['name'] ?? '');
        $configuredLanguage = (string) ($resolved['language'] ?? '');

        if ($template === '') {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'اضبط WHATSAPP_DELIVERY_NOTE_TEMPLATE في .env باسم القالب المعتمد في Meta.',
                'status' => null,
                'mode' => 'template:missing',
                'to' => self::normalizePhone($to),
            ];
        }

        $headerComponent = [
            'type' => 'header',
            'parameters' => [[
                'type' => 'document',
                'document' => [
                    'id' => $mediaId,
                    'filename' => $filename,
                ],
            ]],
        ];

        $includeUrlButton = filter_var(config('services.whatsapp.delivery_note_url_button', false), FILTER_VALIDATE_BOOLEAN)
            && is_string($urlButtonSuffix)
            && $urlButtonSuffix !== '';

        $componentSets = [];

        if ($includeUrlButton) {
            $componentSets[] = [
                $headerComponent,
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [[
                        'type' => 'text',
                        'text' => $urlButtonSuffix,
                    ]],
                ],
            ];
        }

        $componentSets[] = [$headerComponent];

        $last = [
            'success' => false,
            'message_id' => null,
            'error' => 'تعذر إرسال تمبلت إذن التسليم',
            'status' => null,
            'mode' => 'template:'.$template,
            'to' => self::normalizePhone($to),
        ];

        $names = $this->deliveryNoteNameFallbacks($template);

        foreach ($names as $name) {
            foreach ($this->deliveryNoteLanguageFallbacks($configuredLanguage) as $language) {
                foreach ($componentSets as $index => $components) {
                    $result = $this->sendTemplateWithReport($to, $name, $language, $components);
                    $result['mode'] = 'template:'.$name.'/'.$language.'#'.$index;
                    $result['to'] = self::normalizePhone($to);

                    if ($result['success']) {
                        Log::info('WhatsApp delivery-note template matched', [
                            'name' => $name,
                            'language' => $language,
                        ]);

                        return $result;
                    }

                    $last = $result;
                    $error = strtolower((string) ($result['error'] ?? ''));

                    if (str_contains($error, '132001') || str_contains($error, 'translation')) {
                        break;
                    }

                    $parameterIssue = str_contains($error, 'parameter')
                        || str_contains($error, 'button')
                        || str_contains($error, 'component')
                        || str_contains($error, '132018')
                        || str_contains($error, '132000');

                    if (! $parameterIssue) {
                        return $result;
                    }
                }
            }
        }

        $last['error'] = 'القالب '.$template.' غير موجود أو غير معتمد بهذه اللغة في واتساب (خطأ 132001). '
            .'راجع الاسم واللغة وحالة Approved في Meta Business Manager.';

        return $last;
    }

    /**
     * @return list<string>
     */
    private function deliveryNoteLanguageFallbacks(string $configured): array
    {
        $configured = trim($configured);

        return array_values(array_unique(array_filter([
            $configured !== '' ? $configured : null,
            $configured === 'en' ? 'en_US' : null,
            $configured === 'en_US' ? 'en' : null,
            'ar',
            'en_US',
            'en',
        ])));
    }

    /**
     * @return list<string>
     */
    private function deliveryNoteNameFallbacks(string $configured): array
    {
        return array_values(array_unique(array_filter([
            trim($configured),
            'order_deliver_note',
            'order_delivery_note',
            'delivery_note',
            'order_deliver_note_v2',
        ])));
    }

    /**
     * @return array{name: string, language: string, status: ?string, error: ?string}
     */
    private function resolveDeliveryNoteTemplate(): array
    {
        $configuredName = trim((string) config('services.whatsapp.delivery_note_template', ''));
        $configuredLang = trim((string) config('services.whatsapp.delivery_note_template_language', 'ar'));

        $list = $this->listMessageTemplates(true);

        if (! $list['success']) {
            return [
                'name' => $configuredName,
                'language' => $configuredLang,
                'status' => null,
                'error' => null,
            ];
        }

        $approved = array_values(array_filter(
            $list['templates'],
            fn (array $template) => strtoupper($template['status']) === 'APPROVED'
        ));

        $match = $this->firstTemplate($approved, $configuredName, $configuredLang)
            ?? $this->firstTemplate($approved, $configuredName, null)
            ?? $this->firstDocumentHeaderTemplate($approved)
            ?? $this->firstTemplateByNeedle($approved, ['deliver_note', 'delivery_note', 'deliver']);

        if ($match) {
            return [
                'name' => $match['name'],
                'language' => $match['language'],
                'status' => $match['status'],
                'error' => null,
            ];
        }

        $pending = $this->firstDocumentHeaderTemplate($list['templates'])
            ?? $this->firstTemplateByNeedle($list['templates'], ['deliver_note', 'delivery_note', $configuredName]);

        if ($pending && strtoupper($pending['status']) !== 'APPROVED') {
            return [
                'name' => $pending['name'],
                'language' => $pending['language'],
                'status' => $pending['status'],
                'error' => "القالب {$pending['name']} ({$pending['language']}) حالته {$pending['status']} وليس معتمدًا بعد.",
            ];
        }

        return [
            'name' => $configuredName,
            'language' => $configuredLang,
            'status' => null,
            'error' => null,
        ];
    }

    /**
     * @param  list<array{name: string, language: string, status: string, category: string, components?: mixed}>  $templates
     * @return array{name: string, language: string, status: string, category: string, components?: mixed}|null
     */
    private function firstTemplate(array $templates, string $name, ?string $language): ?array
    {
        if ($name === '') {
            return null;
        }

        foreach ($templates as $template) {
            if ($template['name'] !== $name) {
                continue;
            }

            if ($language !== null && $template['language'] !== $language) {
                continue;
            }

            return $template;
        }

        return null;
    }

    /**
     * @param  list<array{name: string, language: string, status: string, category: string, components?: mixed}>  $templates
     * @param  list<string>  $needles
     * @return array{name: string, language: string, status: string, category: string, components?: mixed}|null
     */
    private function firstTemplateByNeedle(array $templates, array $needles): ?array
    {
        foreach ($needles as $needle) {
            $needle = strtolower(trim($needle));

            if ($needle === '') {
                continue;
            }

            foreach ($templates as $template) {
                if (str_contains(strtolower($template['name']), $needle)) {
                    return $template;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array{name: string, language: string, status: string, category: string, components?: mixed}>  $templates
     * @return array{name: string, language: string, status: string, category: string, components?: mixed}|null
     */
    private function firstDocumentHeaderTemplate(array $templates): ?array
    {
        foreach ($templates as $template) {
            $components = $template['components'] ?? [];

            if (! is_array($components)) {
                continue;
            }

            foreach ($components as $component) {
                if (! is_array($component)) {
                    continue;
                }

                $type = strtoupper((string) ($component['type'] ?? ''));
                $format = strtoupper((string) ($component['format'] ?? ''));

                if ($type === 'HEADER' && $format === 'DOCUMENT') {
                    return $template;
                }
            }
        }

        return null;
    }

    /**
     * Session CTA with a full URL. Works only inside the 24-hour customer window.
     *
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int, mode?: string}
     */
    public function sendCtaUrlWithReport(string $to, string $body, string $buttonText, string $url): array
    {
        if (! $this->isApiConfigured()) {
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
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'cta_url',
                    'body' => [
                        'text' => mb_substr($body, 0, 1024),
                    ],
                    'action' => [
                        'name' => 'cta_url',
                        'parameters' => [
                            'display_text' => mb_substr($buttonText, 0, 20),
                            'url' => $url,
                        ],
                    ],
                ],
            ]
        );

        $result = $this->parseMessageResponse($response, $recipient, 'cta_url');
        $result['mode'] = 'cta_url';

        return $result;
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int, mode?: string}
     */
    public function sendDeliveryNotePublicLink(string $to, string $url): array
    {
        $cta = $this->sendCtaUrlWithReport(
            $to,
            'إذن التسليم جاهز للعرض على النظام',
            'عرض الطلب',
            $url,
        );

        if ($cta['success']) {
            return $cta;
        }

        $text = $this->sendTextWithReport($to, "إذن التسليم:\n".$url);
        $text['mode'] = 'text-link';

        return $text;
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int, mode: string}
     */
    public function sendOrderMessageWithReport(string $to, string $body): array
    {
        $template = (string) config('services.whatsapp.order_template', '');

        if ($template !== '') {
            $result = $this->sendTemplateWithReport(
                $to,
                $template,
                (string) config('services.whatsapp.order_template_language', 'ar'),
                [[
                    'type' => 'body',
                    'parameters' => [[
                        'type' => 'text',
                        'text' => mb_substr($body, 0, 1024),
                    ]],
                ]]
            );
            $result['mode'] = 'template:'.$template;

            return $result;
        }

        $result = $this->sendTextWithReport($to, $body);
        $result['mode'] = 'text';

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int}
     */
    public function sendTemplateWithReport(string $to, string $templateName, string $languageCode, array $components): array
    {
        if (! $this->isApiConfigured()) {
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
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $languageCode],
                    'components' => $components,
                ],
            ]
        );

        return $this->parseMessageResponse($response, $recipient, 'template');
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int, mode?: string}
     */
    public function sendTextWithReport(string $to, string $body): array
    {
        if (! $this->isApiConfigured()) {
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

        return $this->parseMessageResponse($response, $recipient, 'text');
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, status: ?int}
     */
    private function parseMessageResponse(\Illuminate\Http\Client\Response $response, string $recipient, string $mode): array
    {
        if ($response->successful()) {
            Log::info('WhatsApp message sent', [
                'to' => $recipient,
                'mode' => $mode,
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
            'mode' => $mode,
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
        if (! \Illuminate\Support\Facades\Schema::hasTable('whatsapp_notification_recipients')) {
            return [];
        }

        $fromDb = \App\Models\WhatsappNotificationRecipient::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('phone')
            ->all();

        $normalized = array_values(array_unique(array_map(
            fn (string $number) => self::normalizePhone($number),
            $fromDb
        )));

        return $this->filterSenderNumber($normalized);
    }

    public static function isSenderNumber(string $phone): bool
    {
        $sender = self::normalizePhone((string) config('services.whatsapp.business_phone', ''));

        if ($sender === '') {
            return false;
        }

        return self::normalizePhone($phone) === $sender;
    }

    public static function senderDisplayPhone(): string
    {
        $phone = (string) config('services.whatsapp.business_phone', '');
        if ($phone === '') {
            return '—';
        }

        $digits = self::normalizePhone($phone);

        if (strlen($digits) === 12 && str_starts_with($digits, '966')) {
            return '+966 '.substr($digits, 3, 2).' '.substr($digits, 5, 3).' '.substr($digits, 8);
        }

        return '+'.$digits;
    }

    /**
     * @param  list<string>  $numbers
     * @return list<string>
     */
    private function filterSenderNumber(array $numbers): array
    {
        return array_values(array_filter(
            $numbers,
            fn (string $number) => ! self::isSenderNumber($number)
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
    public function sendOrderToAllRecipientsWithReport(string $body): array
    {
        $results = [];

        foreach ($this->recipientNumbers() as $recipient) {
            $report = $this->sendOrderMessageWithReport($recipient, $body);

            $results[] = [
                'to' => $recipient,
                'success' => $report['success'],
                'detail' => $report['success']
                    ? (($report['mode'] ?? 'text').' — message_id='.($report['message_id'] ?? '—'))
                    : ('HTTP '.($report['status'] ?? '—').' — '.($report['error'] ?? 'خطأ غير معروف')),
            ];
        }

        return $results;
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

    /**
     * @return array{success: bool, waba_id: ?string, error: ?string}
     */
    public function resolveBusinessAccountId(): array
    {
        $configured = (string) config('services.whatsapp.waba_id', '');
        if ($configured !== '') {
            return ['success' => true, 'waba_id' => $configured, 'error' => null];
        }

        if (! $this->isApiConfigured()) {
            return ['success' => false, 'waba_id' => null, 'error' => 'واتساب غير مفعّل'];
        }

        $fromToken = $this->wabaIdFromAccessToken();
        if ($fromToken) {
            return ['success' => true, 'waba_id' => $fromToken, 'error' => null];
        }

        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $version = (string) config('services.whatsapp.graph_version', 'v21.0');

        $response = $this->client()->get(
            "https://graph.facebook.com/{$version}/{$phoneNumberId}",
            ['fields' => 'whatsapp_business_account']
        );

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();

            return ['success' => false, 'waba_id' => null, 'error' => (string) $error];
        }

        $wabaId = $response->json('whatsapp_business_account.id');

        if (! is_string($wabaId) || $wabaId === '') {
            return ['success' => false, 'waba_id' => null, 'error' => 'تعذّر جلب WhatsApp Business Account'];
        }

        return ['success' => true, 'waba_id' => $wabaId, 'error' => null];
    }

    private function wabaIdFromAccessToken(): ?string
    {
        $token = (string) config('services.whatsapp.access_token');
        $version = (string) config('services.whatsapp.graph_version', 'v21.0');

        $response = $this->client()->get(
            "https://graph.facebook.com/{$version}/debug_token",
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

    /**
     * @return array{success: bool, templates: list<array{name: string, language: string, status: string, category: string, components?: mixed}>, error: ?string}
     */
    public function listMessageTemplates(bool $withComponents = false): array
    {
        $account = $this->resolveBusinessAccountId();
        if (! $account['success']) {
            return ['success' => false, 'templates' => [], 'error' => $account['error']];
        }

        $version = (string) config('services.whatsapp.graph_version', 'v21.0');
        $templates = [];
        $url = "https://graph.facebook.com/{$version}/{$account['waba_id']}/message_templates";
        $fields = 'name,language,status,category';
        if ($withComponents) {
            $fields .= ',components';
        }
        $params = ['limit' => 100, 'fields' => $fields];

        do {
            $response = $this->client()->get($url, $params);

            if (! $response->successful()) {
                $error = $response->json('error.message') ?? $response->body();

                return ['success' => false, 'templates' => [], 'error' => (string) $error];
            }

            foreach ($response->json('data', []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $templates[] = [
                    'name' => (string) ($row['name'] ?? '—'),
                    'language' => (string) ($row['language'] ?? '—'),
                    'status' => (string) ($row['status'] ?? '—'),
                    'category' => (string) ($row['category'] ?? '—'),
                    'components' => $row['components'] ?? null,
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
     * @return array{found: bool, status: ?string, error: ?string}
     */
    public function verifyOrderTemplate(): array
    {
        $name = (string) config('services.whatsapp.order_template', '');
        $language = (string) config('services.whatsapp.order_template_language', 'ar');

        if ($name === '') {
            return ['found' => false, 'status' => null, 'error' => 'WHATSAPP_ORDER_TEMPLATE غير مضبوط'];
        }

        $list = $this->listMessageTemplates();
        if (! $list['success']) {
            return ['found' => false, 'status' => null, 'error' => $list['error']];
        }

        foreach ($list['templates'] as $template) {
            if ($template['name'] === $name && $template['language'] === $language) {
                return ['found' => true, 'status' => $template['status'], 'error' => null];
            }
        }

        return ['found' => false, 'status' => null, 'error' => null];
    }

    private function client(): PendingRequest
    {
        return Http::timeout(30)
            ->acceptJson()
            ->withToken((string) config('services.whatsapp.access_token'));
    }
}
