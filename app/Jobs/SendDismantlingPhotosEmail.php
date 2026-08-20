<?php

namespace App\Jobs;

use App\Mail\DismantlingCompletedMail;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use App\Support\PublicAppUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDismantlingPhotosEmail implements ShouldQueue
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
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
                'workerAssemblers' => fn ($q) => $q->dismantling()->with('createdByUser:id,email,customer_name'),
            ])
            ->find($this->orderId);

        if (! $order) {
            Log::warning('Dismantling photos email skipped: order not found', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        if ($order->dismantling_photos_notified_at !== null) {
            Log::info('Dismantling photos email skipped: already sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $lines = $order->workerOrders;
        if ($lines->isEmpty() || $lines->contains(fn (WorkerOrder $line) => blank($line->pickup_photo))) {
            Log::info('Dismantling photos email skipped: pickup photos incomplete', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $recipients = $this->recipientEmails($order);
        if ($recipients === []) {
            Log::warning('Dismantling photos email skipped: no recipients', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $worker = User::query()->find($this->workerUserId);
        $workerName = trim((string) ($worker?->name ?: 'عامل'));
        $customerName = trim((string) ($order->customer_name ?: 'بدون اسم'));

        $photos = $lines->map(function (WorkerOrder $line) {
            $path = (string) $line->pickup_photo;

            return [
                'product_name' => (string) ($line->product_name ?: 'منتج'),
                'photo_path' => $path,
                'photo_url' => $path !== ''
                    ? PublicAppUrl::to('/storage/'.ltrim($path, '/'))
                    : null,
            ];
        })->values()->all();

        $mailable = new DismantlingCompletedMail(
            orderNumber: (string) $order->order_number,
            customerName: $customerName,
            workerName: $workerName !== '' ? $workerName : 'عامل',
            returnsUrl: PublicAppUrl::to('/returns/'.$order->id),
            photos: $photos,
        );

        try {
            Mail::to($recipients)->send($mailable);
        } catch (Throwable $e) {
            Log::error('Dismantling photos email failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'recipients' => $recipients,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $order->forceFill([
            'dismantling_photos_notified_at' => now(),
        ])->save();

        Log::info('Dismantling photos email sent', [
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
            if (! $assembler instanceof WorkerOrderAssembler || ! $assembler->isDismantling()) {
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

        // Fallback: returns decision roles if assigner has no email on file.
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
