<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;

class OrderJourney
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Order $order): array
    {
        $journey = self::build($order);
        $current = collect($journey['steps'])->firstWhere('status', 'current');
        $completedCount = collect($journey['steps'])->where('status', 'completed')->count();
        $visibleCount = collect($journey['steps'])->where('status', '!=', 'skipped')->count();

        $visibleSteps = collect($journey['steps'])
            ->where('status', '!=', 'skipped')
            ->values()
            ->map(fn (array $step) => [
                'key' => $step['key'],
                'icon' => $step['icon'],
                'title' => $step['title'],
                'status' => $step['status'],
            ])
            ->all();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'invoice_number' => $order->invoice?->invoice_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'created_at' => $order->created_at?->toIso8601String(),
            'status' => $order->status,
            'is_cancelled' => in_array($order->status, ['cancelled', 'refunded'], true),
            'current_key' => $current['key'] ?? ($journey['is_complete'] ? 'done' : null),
            'current_title' => $current['title'] ?? ($journey['is_complete'] ? 'اكتملت الرحلة' : null),
            'waiting' => $current['waiting'] ?? ($journey['is_complete'] ? 'لا يوجد انتظار — اكتملت دورة الطلب' : null),
            'percent' => $visibleCount > 0 ? (int) round(($completedCount / $visibleCount) * 100) : 0,
            'completed_steps' => $completedCount,
            'total_steps' => $visibleCount,
            'is_complete' => $journey['is_complete'],
            'steps' => $visibleSteps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Order $order): array
    {
        $summary = self::summary($order);
        $journey = self::build($order);

        return [
            ...$summary,
            'quotation_number' => $order->quotation?->quotation_number,
            'invoice_number' => $order->invoice?->invoice_number,
            'activity_date' => $order->activity_date?->format('Y-m-d'),
            'total_amount' => (float) $order->total_amount,
            'amount_paid' => (float) ($order->amount_paid ?? 0),
            'currency' => $order->currency ?: 'SAR',
            'hrefs' => [
                'order' => '/orders/'.$order->id,
                'quotation' => $order->quotation_id ? '/quotations/'.$order->quotation_id : null,
                'work_order' => $order->workerOrders->isNotEmpty() ? '/worker-orders/'.rawurlencode($order->order_number) : null,
                'returns' => '/returns/'.$order->id,
                'payment_receipts' => '/payment-receipts',
            ],
            'steps' => $journey['steps'],
            'is_complete' => $journey['is_complete'],
        ];
    }

    /**
     * @return array{steps: list<array<string, mixed>>, is_complete: bool}
     */
    public static function build(Order $order): array
    {
        $receipts = $order->relationLoaded('paymentReceipts')
            ? $order->paymentReceipts
            : $order->paymentReceipts()->with(['approvedBy:id,customer_name', 'recordedBy:id,customer_name'])->get();

        $lines = $order->relationLoaded('workerOrders')
            ? $order->workerOrders
            : $order->workerOrders()->get();

        $assemblers = $order->relationLoaded('workerAssemblers')
            ? $order->workerAssemblers
            : $order->workerAssemblers()->get();

        $installationWorkers = $assemblers
            ->filter(fn (WorkerOrderAssembler $assembler) => $assembler->isInstallation())
            ->sortBy('created_at')
            ->values();

        $firstReceipt = $receipts->sortBy('created_at')->first();
        $pendingReceipt = $receipts->firstWhere('approval_status', OrderPaymentReceipt::STATUS_PENDING);
        $approvedReceipts = $receipts
            ->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)
            ->sortBy('approved_at')
            ->values();
        $firstApproved = $approvedReceipts->first();
        $approvedTotal = round((float) $approvedReceipts->sum('amount'), 2);

        $photosDone = $lines->filter(
            fn (WorkerOrder $line) => $line->status === 'completed' && filled($line->installation_photo),
        )->count();
        $photosTotal = $lines->count();
        $allPhotosDone = $photosTotal > 0 && $photosDone === $photosTotal;

        $pickupsDone = $lines->filter(fn (WorkerOrder $line) => filled($line->pickup_photo))->count();
        $hasReturnTrack = filled($order->dismantling_at)
            || filled($order->warehouse_returned_at)
            || $pickupsDone > 0
            || $order->work_order_approved_at !== null;

        $hasInsurance = round((float) ($order->insurance_amount ?? 0), 2) > 0
            || in_array($order->insurance_status, ['pending', 'refunded', 'withheld'], true);

        $quotation = $order->quotation;
        $steps = [];

        if ($quotation) {
            $steps[] = self::step(
                key: 'quotation',
                icon: 'file-spreadsheet',
                title: 'عرض السعر',
                description: 'تم إنشاء عرض السعر رقم '.$quotation->quotation_number.' بقيمة '.self::money((float) $quotation->total_amount).'.',
                completed: true,
                at: $quotation->created_at?->toIso8601String(),
                actor: null,
                waiting: 'بانتظار تحويل عرض السعر إلى طلب',
                href: '/quotations/'.$quotation->id,
            );
        }

        $steps[] = self::step(
            key: 'order_created',
            icon: 'shopping-cart',
            title: 'إنشاء الطلب',
            description: 'تم إنشاء الطلب رقم '.$order->order_number
                .(filled($order->customer_name) ? ' للعميل '.$order->customer_name : '')
                .' بقيمة '.self::money((float) $order->total_amount).'.',
            completed: true,
            at: $order->created_at?->toIso8601String(),
            actor: null,
            waiting: null,
            href: '/orders/'.$order->id,
        );

        $steps[] = self::step(
            key: 'receipt_recorded',
            icon: 'receipt',
            title: 'تسجيل سند القبض',
            description: $firstReceipt
                ? 'تم تسجيل سند رقم '.$firstReceipt->receipt_number.' بمبلغ '.self::money((float) $firstReceipt->amount).'.'
                : 'لم يُسجَّل سند قبض بعد.',
            completed: (bool) $firstReceipt,
            at: $firstReceipt?->created_at?->toIso8601String(),
            actor: $firstReceipt?->recordedBy?->name,
            waiting: 'بانتظار تسجيل سند قبض على الطلب',
            href: '/payment-receipts',
        );

        $accountantDescription = 'بانتظار اعتماد المحاسب للمبلغ المسجّل.';
        if ($firstApproved) {
            $accountantDescription = 'عمد المحاسب على مبلغ '.self::money((float) $firstApproved->amount).'.';
            if ($approvedReceipts->count() > 1) {
                $accountantDescription .= ' إجمالي المعتمد '.self::money($approvedTotal).'.';
            }
        } elseif ($pendingReceipt) {
            $accountantDescription = 'يوجد سند معلّق بمبلغ '.self::money((float) $pendingReceipt->amount).' بانتظار اعتماد المحاسب.';
        }

        $steps[] = self::step(
            key: 'accountant_approved',
            icon: 'wallet',
            title: 'تعميد المحاسب',
            description: $accountantDescription,
            completed: (bool) $firstApproved,
            at: $firstApproved?->approved_at?->toIso8601String() ?? $firstApproved?->created_at?->toIso8601String(),
            actor: $firstApproved?->approvedBy?->name,
            waiting: $pendingReceipt
                ? 'بانتظار تعميد المحاسب لسند بمبلغ '.self::money((float) $pendingReceipt->amount)
                : 'بانتظار تعميد المحاسب لسند القبض',
            href: '/payment-receipts',
        );

        $steps[] = self::step(
            key: 'work_order_issued',
            icon: 'hard-hat',
            title: 'إصدار أمر العمل',
            description: $lines->isNotEmpty()
                ? 'تم إصدار أمر العمل وربطه بـ '.$photosTotal.' منتج'.($photosTotal === 1 ? '' : 'ات').'.'
                : 'لم يصدر أمر العمل بعد. يصدر تلقائياً بعد اعتماد أول سند قبض.',
            completed: $lines->isNotEmpty(),
            at: $lines->sortBy('created_at')->first()?->created_at?->toIso8601String(),
            actor: null,
            waiting: 'بانتظار إصدار أمر العمل بعد اعتماد المحاسب',
            href: $lines->isNotEmpty() ? '/worker-orders/'.rawurlencode($order->order_number) : '/worker-orders',
        );

        $workerNames = $installationWorkers->pluck('worker_name')->unique()->filter()->values();
        $steps[] = self::step(
            key: 'workers_assigned',
            icon: 'users',
            title: 'تعيين العمال',
            description: $workerNames->isNotEmpty()
                ? 'تم تعيين: '.$workerNames->implode('، ').'.'
                : 'لم يتم تعيين عمال تركيب بعد.',
            completed: $workerNames->isNotEmpty(),
            at: $installationWorkers->first()?->created_at?->toIso8601String(),
            actor: $workerNames->first(),
            waiting: 'بانتظار تعيين عمال التركيب من مدير العمال',
            href: $lines->isNotEmpty() ? '/worker-orders/'.rawurlencode($order->order_number) : null,
        );

        $photoDescription = $photosTotal === 0
            ? 'لا توجد منتجات في أمر العمل بعد.'
            : ($allPhotosDone
                ? 'تم التركيب واستلام صور كل المنتجات ('.$photosDone.'/'.$photosTotal.').'
                : 'تم استلام صور '.$photosDone.' من '.$photosTotal.' منتجات.');

        $steps[] = self::step(
            key: 'installation_photos',
            icon: 'camera',
            title: 'التركيب واستلام الصور',
            description: $photoDescription,
            completed: $allPhotosDone,
            at: $allPhotosDone
                ? $lines->sortByDesc('completed_at')->first()?->completed_at?->toIso8601String()
                : null,
            actor: $lines->firstWhere('completed_by')?->completedByUser?->name,
            waiting: 'بانتظار تركيب المنتجات ورفع الصور من العمال',
            href: $lines->isNotEmpty() ? '/worker-orders/'.rawurlencode($order->order_number) : null,
        );

        $steps[] = self::step(
            key: 'workers_manager_approved',
            icon: 'shield-check',
            title: 'تعميد مدير العمال',
            description: $order->work_order_approved_at
                ? 'تم تعميد أمر العمل من مدير العمال.'
                : 'بانتظار تعميد مدير العمال بعد اكتمال صور التركيب.',
            completed: (bool) $order->work_order_approved_at,
            at: $order->work_order_approved_at?->toIso8601String(),
            actor: $order->workOrderApprovedBy?->name,
            waiting: 'بانتظار تعميد مدير العمال لأمر العمل',
            href: $lines->isNotEmpty() ? '/worker-orders/'.rawurlencode($order->order_number) : null,
        );

        if ($hasReturnTrack) {
            $dismantleDone = $photosTotal > 0 && $pickupsDone === $photosTotal;
            $steps[] = self::step(
                key: 'dismantling',
                icon: 'undo-2',
                title: 'الفك والاسترجاع',
                description: $dismantleDone
                    ? 'تم فك المنتجات واستلام صور الفك ('.$pickupsDone.'/'.$photosTotal.').'
                    : ($pickupsDone > 0
                        ? 'تم استلام صور فك '.$pickupsDone.' من '.$photosTotal.' منتجات.'
                        : 'بانتظار فك المنتجات من الموقع.'),
                completed: $dismantleDone,
                at: $dismantleDone
                    ? $lines->sortByDesc('pickup_at')->first()?->pickup_at?->toIso8601String()
                    : ($order->dismantling_at?->toIso8601String()),
                actor: null,
                waiting: 'بانتظار فك المنتجات ورفع صور الاسترجاع',
                href: '/returns/'.$order->id,
            );

            $steps[] = self::step(
                key: 'return_confirmed',
                icon: 'undo-2',
                title: 'تعميد الاسترجاع',
                description: $order->warehouse_returned_at
                    ? 'تم تعميد الاسترجاع من صفحة الاسترجاع.'
                    : 'بانتظار تعميد الاسترجاع.',
                completed: (bool) $order->warehouse_returned_at,
                at: $order->warehouse_returned_at?->toIso8601String(),
                actor: $order->warehouseReturnedBy?->name,
                waiting: 'بانتظار تعميد الاسترجاع',
                href: '/returns/'.$order->id,
            );

            $steps[] = self::step(
                key: 'warehouse_confirmed',
                icon: 'package-check',
                title: 'تعميد المستودع',
                description: $order->warehouse_keeper_approved_at
                    ? 'تم تعميد المستودع وإغلاق الطلب.'
                    : ($order->warehouse_returned_at
                        ? 'بانتظار تعميد أمين المستودع من أوامر العمل.'
                        : 'بانتظار تعميد الاسترجاع أولاً.'),
                completed: (bool) $order->warehouse_keeper_approved_at,
                at: $order->warehouse_keeper_approved_at?->toIso8601String(),
                actor: $order->warehouseKeeperApprovedBy?->name,
                waiting: 'بانتظار تعميد أمين المستودع',
                href: '/worker-orders/'.$order->id,
            );
        }

        if ($hasInsurance) {
            $insuranceDone = in_array($order->insurance_status, ['refunded', 'withheld'], true);
            $insuranceDescription = match ($order->insurance_status) {
                'refunded' => 'تم استرداد التأمين.',
                'withheld' => 'تم حجز التأمين.',
                default => 'بانتظار استرداد أو حجز التأمين بعد تأكيد المستودع.',
            };

            $steps[] = self::step(
                key: 'insurance',
                icon: 'shield',
                title: 'استرداد التأمين',
                description: $insuranceDescription,
                completed: $insuranceDone,
                at: $order->insurance_refunded_at?->toIso8601String(),
                actor: null,
                waiting: 'بانتظار اعتماد استرداد التأمين',
                href: '/insurance-deposits/'.$order->id,
            );
        }

        $foundCurrent = false;
        foreach ($steps as $index => $step) {
            if ($step['status'] === 'skipped') {
                continue;
            }

            if ($step['completed']) {
                $steps[$index]['status'] = 'completed';
                continue;
            }

            if (! $foundCurrent) {
                $steps[$index]['status'] = 'current';
                $foundCurrent = true;
                continue;
            }

            $steps[$index]['status'] = 'upcoming';
        }

        $isComplete = ! $foundCurrent && ! in_array($order->status, ['cancelled', 'refunded'], true);

        if ($isComplete && $steps !== []) {
            $last = count($steps) - 1;
            $steps[$last]['waiting'] = null;
        }

        return [
            'steps' => $steps,
            'is_complete' => $isComplete,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function step(
        string $key,
        string $icon,
        string $title,
        string $description,
        bool $completed,
        ?string $at,
        ?string $actor,
        ?string $waiting,
        ?string $href,
    ): array {
        return [
            'key' => $key,
            'icon' => $icon,
            'title' => $title,
            'description' => $description,
            'completed' => $completed,
            'status' => $completed ? 'completed' : 'upcoming',
            'at' => $at,
            'actor' => $actor,
            'waiting' => $completed ? null : $waiting,
            'href' => $href,
        ];
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2).' ر.س';
    }
}
