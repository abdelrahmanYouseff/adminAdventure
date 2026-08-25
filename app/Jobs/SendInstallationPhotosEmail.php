<?php

namespace App\Jobs;

use App\Support\MediaStorage;

use App\Mail\InstallationCompletedMail;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use App\Support\EmailLogRecorder;
use App\Support\PublicAppUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInstallationPhotosEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $orderId,
        public int $workerUserId,
    ) {}

    public function handle(): void
    {
        $order = Order::query()
            ->with([
                'invoice:id,invoice_number',
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
                'workerAssemblers' => fn ($q) => $q->installation()->with('createdByUser:id,email,customer_name'),
            ])
            ->find($this->orderId);

        if (! $order) {
            Log::warning('Installation photos email skipped: order not found', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        if ($order->installation_photos_notified_at !== null) {
            Log::info('Installation photos email skipped: already sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            EmailLogRecorder::record(
                type: 'installation_photos',
                status: 'skipped',
                order: $order,
                subject: 'تم تجاهل إرسال صور التركيب: أُرسل سابقاً',
                meta: ['reason' => 'already_sent'],
            );

            return;
        }

        $lines = $order->workerOrders;
        if ($lines->isEmpty() || $lines->contains(fn (WorkerOrder $line) => $line->status !== 'completed' || blank($line->installation_photo))) {
            Log::info('Installation photos email skipped: installation photos incomplete', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            EmailLogRecorder::record(
                type: 'installation_photos',
                status: 'skipped',
                order: $order,
                subject: 'تم تجاهل إرسال صور التركيب: الصور غير مكتملة',
                meta: ['reason' => 'photos_incomplete'],
            );

            return;
        }

        $recipients = $this->recipientEmails($order);
        if ($recipients === []) {
            Log::warning('Installation photos email skipped: no recipients', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            EmailLogRecorder::record(
                type: 'installation_photos',
                status: 'failed',
                order: $order,
                subject: 'فشل إرسال صور التركيب',
                errorMessage: 'No recipients found',
                meta: ['reason' => 'no_recipients'],
            );

            return;
        }

        $worker = User::query()->find($this->workerUserId);
        $workerName = trim((string) ($worker?->name ?: 'عامل'));
        $customerName = trim((string) ($order->customer_name ?: 'بدون اسم'));

        $photos = $lines->map(function (WorkerOrder $line) {
            $path = (string) $line->installation_photo;

            return [
                'product_name' => (string) ($line->product_name ?: 'منتج'),
                'photo_path' => $path,
                'photo_url' => $path !== ''
                    ? MediaStorage::url($path)
                    : null,
            ];
        })->values()->all();

        $reference = $order->invoice?->invoice_number ?: $order->order_number;

        $mailable = new InstallationCompletedMail(
            orderNumber: (string) $order->order_number,
            customerName: $customerName,
            workerName: $workerName !== '' ? $workerName : 'عامل',
            workOrderUrl: PublicAppUrl::to('/worker-orders/'.rawurlencode((string) $reference)),
            photos: $photos,
        );

        try {
            Mail::to($recipients)->send($mailable);
        } catch (Throwable $e) {
            EmailLogRecorder::record(
                type: 'installation_photos',
                status: 'failed',
                order: $order,
                recipients: $recipients,
                subject: $mailable->envelope()->subject,
                errorMessage: $e->getMessage(),
                meta: ['photos' => count($photos)],
            );

            Log::error('Installation photos email failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'recipients' => $recipients,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $order->forceFill([
            'installation_photos_notified_at' => now(),
        ])->save();

        EmailLogRecorder::record(
            type: 'installation_photos',
            status: 'sent',
            order: $order,
            recipients: $recipients,
            subject: $mailable->envelope()->subject,
            meta: ['photos' => count($photos)],
        );

        Log::info('Installation photos email sent', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'recipients' => $recipients,
            'photos' => count($photos),
        ]);
    }

    /**
     * @return list<string>
     */
    private function recipientEmails(Order $order): array
    {
        $emails = [];

        foreach ($order->workerAssemblers as $assembler) {
            if (! $assembler instanceof WorkerOrderAssembler || ! $assembler->isInstallation()) {
                continue;
            }

            $email = trim((string) ($assembler->createdByUser?->email ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }

        $emails = array_values(array_unique($emails));

        if ($emails !== []) {
            return $emails;
        }

        return User::query()
            ->whereIn('role', [
                User::ROLE_ADMIN,
                User::ROLE_GENERAL_MANAGER,
                User::ROLE_MANAGER,
                User::ROLE_WORKERS_MANAGER,
            ])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
