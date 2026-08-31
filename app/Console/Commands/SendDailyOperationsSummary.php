<?php

namespace App\Console\Commands;

use App\Mail\DailyOperationsSummaryMail;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Support\EmailLogRecorder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyOperationsSummary extends Command
{
    protected $signature = 'reports:daily-operations-summary
                            {--date= : تاريخ الملخص بصيغة Y-m-d (افتراضي: اليوم بتوقيت الرياض)}
                            {--to= : تجاوز المستلمين (إيميلات مفصولة بفاصلة)}';

    protected $description = 'إرسال ملخص يومي: الطلبات الجديدة وعمليات التركيب والفك';

    public function handle(): int
    {
        $timezone = 'Asia/Riyadh';
        $dateInput = $this->option('date');

        try {
            $day = filled($dateInput)
                ? Carbon::createFromFormat('Y-m-d', (string) $dateInput, $timezone)->startOfDay()
                : now($timezone)->startOfDay();
        } catch (Throwable) {
            $this->error('صيغة التاريخ غير صحيحة. استخدم Y-m-d مثل 2026-08-31');

            return self::FAILURE;
        }

        $recipients = $this->resolveRecipients();
        if ($recipients === []) {
            $this->error('مستلمو الملخص غير مضبوطين. عيّن MAIL_DAILY_SUMMARY_TO في .env');

            return self::FAILURE;
        }

        $start = $day->copy()->utc();
        $end = $day->copy()->endOfDay()->utc();

        $orders = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->orderBy('id')
            ->get(['order_number', 'customer_name']);

        $ordersPayload = $orders->map(fn (Order $order) => [
            'order_number' => (string) $order->order_number,
            'customer_name' => trim((string) ($order->customer_name ?: 'بدون اسم')) ?: 'بدون اسم',
        ])->values()->all();

        $installationsCount = WorkerOrder::query()
            ->whereBetween('completed_at', [$start, $end])
            ->where('status', 'completed')
            ->count();

        $dismantlingCount = WorkerOrder::query()
            ->whereBetween('pickup_at', [$start, $end])
            ->whereNotNull('pickup_photo')
            ->count();

        $dateLabel = $day->locale('ar')->translatedFormat('l j F Y');

        $mailable = new DailyOperationsSummaryMail(
            dateLabel: $dateLabel,
            ordersCount: count($ordersPayload),
            installationsCount: $installationsCount,
            dismantlingCount: $dismantlingCount,
            orders: $ordersPayload,
        );

        try {
            Mail::to($recipients)->send($mailable);
        } catch (Throwable $e) {
            EmailLogRecorder::record(
                type: 'daily_operations_summary',
                status: 'failed',
                order: null,
                recipients: $recipients,
                subject: $mailable->envelope()->subject,
                errorMessage: $e->getMessage(),
                meta: [
                    'date' => $day->toDateString(),
                    'orders' => count($ordersPayload),
                    'installations' => $installationsCount,
                    'dismantling' => $dismantlingCount,
                ],
            );

            $this->error('فشل إرسال الملخص: '.$e->getMessage());

            return self::FAILURE;
        }

        EmailLogRecorder::record(
            type: 'daily_operations_summary',
            status: 'sent',
            order: null,
            recipients: $recipients,
            subject: $mailable->envelope()->subject,
            meta: [
                'date' => $day->toDateString(),
                'orders' => count($ordersPayload),
                'installations' => $installationsCount,
                'dismantling' => $dismantlingCount,
            ],
        );

        $this->info('تم إرسال الملخص إلى: '.implode(', ', $recipients));
        $this->line('التاريخ: '.$day->toDateString());
        $this->line('طلبات: '.count($ordersPayload));
        $this->line('تركيب: '.$installationsCount);
        $this->line('فك: '.$dismantlingCount);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveRecipients(): array
    {
        $raw = $this->option('to');
        if (! filled($raw)) {
            $configured = config('mail.daily_summary_to', []);

            return is_array($configured)
                ? array_values(array_filter($configured, 'is_string'))
                : [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', (string) $raw),
        ), static fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))));
    }
}
