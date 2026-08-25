<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\DeliveryNoteWhatsAppService;
use App\Support\EmailLogRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDeliveryNoteWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(DeliveryNoteWhatsAppService $deliveryNoteWhatsApp): void
    {
        $order = Order::query()
            ->with(['invoice:id,invoice_number', 'workerOrders'])
            ->find($this->orderId);

        if (! $order) {
            Log::warning('Delivery note WhatsApp skipped: order not found', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        if ($order->delivery_note_whatsapp_notified_at !== null) {
            Log::info('Delivery note WhatsApp skipped: already sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            EmailLogRecorder::record(
                type: 'delivery_note_whatsapp',
                status: 'skipped',
                order: $order,
                subject: 'إذن تسليم واتساب — '.$order->order_number,
                errorMessage: 'تم إرسال إذن التسليم مسبقاً',
                meta: ['channel' => 'whatsapp', 'trigger' => 'auto_approve', 'reason' => 'already_sent'],
            );

            return;
        }

        if (! $order->work_order_approved_at) {
            Log::info('Delivery note WhatsApp skipped: work order not approved', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            EmailLogRecorder::record(
                type: 'delivery_note_whatsapp',
                status: 'skipped',
                order: $order,
                subject: 'إذن تسليم واتساب — '.$order->order_number,
                errorMessage: 'أمر العمل غير معتمد',
                meta: ['channel' => 'whatsapp', 'trigger' => 'auto_approve', 'reason' => 'not_approved'],
            );

            return;
        }

        if (! $order->hasAllWorkerPhotos()) {
            Log::info('Delivery note WhatsApp skipped: installation photos incomplete', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            EmailLogRecorder::record(
                type: 'delivery_note_whatsapp',
                status: 'skipped',
                order: $order,
                subject: 'إذن تسليم واتساب — '.$order->order_number,
                errorMessage: 'صور التركيب غير مكتملة',
                meta: ['channel' => 'whatsapp', 'trigger' => 'auto_approve', 'reason' => 'photos_incomplete'],
            );

            return;
        }

        $result = $deliveryNoteWhatsApp->sendToCustomer($order, null, 'auto_approve');

        if (! $result['success']) {
            if ($result['skipped']) {
                Log::info('Delivery note WhatsApp skipped', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'message' => $result['message'],
                    'error' => $result['error'],
                ]);

                return;
            }

            throw new \RuntimeException(
                'فشل إرسال إذن التسليم عبر واتساب — '.($result['error'] ?? $result['message'])
            );
        }

        $order->forceFill([
            'delivery_note_whatsapp_notified_at' => now(),
        ])->saveQuietly();

        Log::info('Delivery note WhatsApp sent', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'to' => $result['to'],
            'url' => $result['url'],
        ]);
    }
}
