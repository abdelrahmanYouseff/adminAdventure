<?php

namespace App\Services;

use App\Models\Order;
use App\Support\DeliveryNotePdfData;
use App\Support\EmailLogRecorder;
use App\Support\WhatsAppConfig;
use Illuminate\Support\Facades\Log;

class DeliveryNoteWhatsAppService
{
    public function __construct(
        private WhatsAppCloudService $whatsApp,
        private ShortLinkService $shortLinks,
        private DeliveryNotePdfService $pdfService,
    ) {}

    /**
     * Send the delivery-note WhatsApp template (+ link) to the customer phone on the order.
     *
     * @return array{
     *     success: bool,
     *     skipped: bool,
     *     message: string,
     *     to: ?string,
     *     url: ?string,
     *     error: ?string
     * }
     */
    public function sendToCustomer(Order $order, ?string $overridePhone = null, string $trigger = 'manual'): array
    {
        if (! WhatsAppConfig::isApiConfigured()) {
            $issues = WhatsAppConfig::apiIssues();

            $result = $this->result(
                success: false,
                skipped: true,
                message: 'واتساب غير مفعّل أو إعداداته ناقصة',
                error: implode(' | ', $issues),
            );

            $this->recordLog($order, $result, $trigger);

            return $result;
        }

        $order->loadMissing(['invoice:id,invoice_number']);

        $to = WhatsAppCloudService::normalizePhone(
            $overridePhone !== null && trim($overridePhone) !== ''
                ? $overridePhone
                : (string) ($order->customer_phone ?? ''),
        );

        if (! preg_match('/^9665\d{8}$/', $to)) {
            $result = $this->result(
                success: false,
                skipped: true,
                message: 'رقم جوال العميل غير صالح لإرسال واتساب',
                error: 'expected 9665xxxxxxxx, got: '.$to,
                to: $to !== '' ? $to : null,
            );

            $this->recordLog($order, $result, $trigger);

            return $result;
        }

        $shortLink = $this->shortLinks->createDeliveryNoteLink($order);
        $deliveryNoteUrl = $this->shortLinks->publicUrl($shortLink);

        $data = DeliveryNotePdfData::fromOrder($order, $deliveryNoteUrl);
        $pdf = $this->pdfService->render($data);
        $filename = 'delivery-note-'.$data->referenceNumber().'.pdf';

        $upload = $this->whatsApp->uploadMedia($pdf, 'application/pdf', $filename);
        if (! $upload['success'] || ! $upload['media_id']) {
            $result = $this->result(
                success: false,
                skipped: false,
                message: 'فشل رفع إذن التسليم إلى واتساب',
                error: $upload['error'] ?? 'خطأ غير معروف',
                to: $to,
                url: $deliveryNoteUrl,
            );

            $this->recordLog($order, $result, $trigger);

            return $result;
        }

        $send = $this->whatsApp->sendDeliveryNoteToCustomer(
            $to,
            $upload['media_id'],
            $filename,
            $deliveryNoteUrl,
            $this->shortLinks->whatsappButtonSuffix($shortLink),
        );

        if (! $send['success']) {
            $result = $this->result(
                success: false,
                skipped: false,
                message: 'فشل إرسال إذن التسليم عبر واتساب',
                error: $send['error'] ?? 'خطأ غير معروف',
                to: $to,
                url: $deliveryNoteUrl,
            );

            $this->recordLog($order, $result, $trigger);

            return $result;
        }

        $from = $send['from'] ?? $this->whatsApp->cloudSendingDisplayPhone();

        $result = $this->result(
            success: true,
            skipped: false,
            message: 'تم إرسال إذن التسليم عبر واتساب إلى +'.$to
                .' من رقم '.$from
                .' — رابط النظام: '.$deliveryNoteUrl,
            to: $to,
            url: $deliveryNoteUrl,
            from: is_string($from) ? $from : null,
        );

        $this->recordLog($order, $result, $trigger);

        return $result;
    }

    /**
     * @param  array{success: bool, skipped: bool, message: string, to: ?string, url: ?string, error: ?string, from?: ?string}  $result
     */
    private function recordLog(Order $order, array $result, string $trigger): void
    {
        $status = $result['success'] ? 'sent' : ($result['skipped'] ? 'skipped' : 'failed');
        $phone = $result['to'] ? '+'.$result['to'] : null;

        EmailLogRecorder::record(
            type: 'delivery_note_whatsapp',
            status: $status,
            order: $order,
            recipients: $phone ? [$phone] : [],
            subject: 'إذن تسليم واتساب — '.($order->order_number ?? '#'.$order->id),
            errorMessage: $status === 'sent' ? null : ($result['error'] ?? $result['message']),
            meta: array_filter([
                'channel' => 'whatsapp',
                'trigger' => $trigger,
                'delivery_note_url' => $result['url'] ?? null,
                'from' => $result['from'] ?? null,
                'customer_phone' => $phone,
            ], fn ($value) => $value !== null && $value !== ''),
        );
    }

    /**
     * @return array{success: bool, skipped: bool, message: string, to: ?string, url: ?string, error: ?string, from?: ?string}
     */
    private function result(
        bool $success,
        bool $skipped,
        string $message,
        ?string $error = null,
        ?string $to = null,
        ?string $url = null,
        ?string $from = null,
    ): array {
        if (! $success) {
            Log::warning('Delivery note WhatsApp send result', [
                'success' => $success,
                'skipped' => $skipped,
                'message' => $message,
                'error' => $error,
                'to' => $to,
            ]);
        }

        return [
            'success' => $success,
            'skipped' => $skipped,
            'message' => $message,
            'to' => $to,
            'url' => $url,
            'error' => $error,
            'from' => $from,
        ];
    }
}
