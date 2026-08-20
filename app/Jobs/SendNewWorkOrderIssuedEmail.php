<?php

namespace App\Jobs;

use App\Mail\NewWorkOrderIssuedMail;
use App\Models\Order;
use App\Models\User;
use App\Support\PublicAppUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNewWorkOrderIssuedEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::query()
            ->with([
                'invoice:id,invoice_number',
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
                'products',
            ])
            ->find($this->orderId);

        if (! $order) {
            Log::warning('New work order email skipped: order not found', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        if ($order->work_order_issued_notified_at !== null) {
            Log::info('New work order email skipped: already sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        if ($order->workerOrders->isEmpty()) {
            Log::info('New work order email skipped: no worker order lines', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $recipients = $this->workersManagerEmails();
        if ($recipients === []) {
            Log::warning('New work order email skipped: no workers managers with email', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $products = $order->workerOrders
            ->pluck('product_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($products === []) {
            $products = $order->products
                ->pluck('product_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $reference = $order->invoice?->invoice_number ?: $order->order_number;
        $customerName = trim((string) ($order->customer_name ?: 'بدون اسم'));
        $phone = trim((string) ($order->customer_phone ?: ''));

        $mailable = new NewWorkOrderIssuedMail(
            orderNumber: (string) $order->order_number,
            customerName: $customerName !== '' ? $customerName : 'بدون اسم',
            customerPhone: $phone !== '' ? $phone : null,
            products: $products,
            assignWorkersUrl: PublicAppUrl::to('/worker-orders/'.rawurlencode((string) $reference)),
        );

        try {
            Mail::to($recipients)->send($mailable);
        } catch (Throwable $e) {
            Log::error('New work order email failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'recipients' => $recipients,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $order->forceFill([
            'work_order_issued_notified_at' => now(),
        ])->save();

        Log::info('New work order email sent', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'recipients' => $recipients,
            'products' => count($products),
        ]);
    }

    /**
     * @return list<string>
     */
    private function workersManagerEmails(): array
    {
        return User::query()
            ->where('role', User::ROLE_WORKERS_MANAGER)
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
