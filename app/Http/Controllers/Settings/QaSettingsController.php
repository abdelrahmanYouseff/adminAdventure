<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\InboxServiceRating;
use App\Models\InboxSetting;
use App\Models\Order;
use App\Services\Inbox\QaRatingFlow;
use App\Services\Inbox\WhatsAppGraphClient;
use App\Support\InboxPhone;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QaSettingsController extends Controller
{
    public function edit(WhatsAppGraphClient $graph)
    {
        $settings = InboxSetting::current();
        $templates = $graph->approvedTemplates();

        return Inertia::render('settings/Qa', [
            'settings' => [
                'qa_rating_enabled' => $settings->qa_rating_enabled,
                'qa_rating_delay_amount' => $settings->qa_rating_delay_amount,
                'qa_rating_delay_unit' => $settings->qa_rating_delay_unit,
                'qa_rating_template_name' => $settings->qa_rating_template_name,
                'qa_rating_template_language' => $settings->qa_rating_template_language,
                'qa_rating_notify_emails' => $settings->qa_rating_notify_emails,
            ],
            'templates' => $templates['templates'] ?? [],
            'templates_error' => $templates['error'] ?? null,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'qa_rating_enabled' => ['required', 'boolean'],
            'qa_rating_delay_amount' => ['required', 'integer', 'min:0', 'max:365'],
            'qa_rating_delay_unit' => ['required', 'in:minutes,hours,days'],
            'qa_rating_template_name' => ['nullable', 'string', 'max:512'],
            'qa_rating_template_language' => ['nullable', 'string', 'max:16'],
            'qa_rating_notify_emails' => ['nullable', 'string', 'max:2000'],
        ]);

        InboxSetting::current()->fill($data)->save();

        return back()->with('success', 'تم حفظ إعدادات تقييم الخدمة.');
    }

    public function sendTest(Request $request, QaRatingFlow $flow)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $conversation = app(\App\Services\Inbox\OutboundMessageSender::class)
            ->startConversation($data['phone']);

        $flow->sendInvite($conversation);

        return back()->with('success', 'تم إرسال قالب تقييم تجريبي.');
    }

    public function backfill(QaRatingFlow $flow)
    {
        $settings = InboxSetting::current();

        if (! $settings->qa_rating_enabled) {
            return back()->with('error', 'تفعيل تقييم الخدمة أولاً.');
        }

        $delay = $this->delayCarbon($settings);
        $sent = 0;

        $orders = Order::query()
            ->whereNotNull('warehouse_returned_at')
            ->where('warehouse_returned_at', '<=', $delay)
            ->whereNotNull('customer_phone')
            ->whereNotIn('id', InboxServiceRating::query()->whereNotNull('order_id')->select('order_id'))
            ->latest('warehouse_returned_at')
            ->limit(50)
            ->get();

        foreach ($orders as $order) {
            if (! InboxPhone::isValidE164((string) $order->customer_phone)) {
                continue;
            }

            $conversation = app(\App\Services\Inbox\OutboundMessageSender::class)
                ->startConversation((string) $order->customer_phone, $order->customer_name);

            try {
                $flow->sendInvite($conversation, $order);
                $sent++;
            } catch (\Throwable) {
                continue;
            }
        }

        return back()->with('success', "تم إرسال {$sent} دعوة تقييم.");
    }

    private function delayCarbon(InboxSetting $settings): \Carbon\CarbonInterface
    {
        $amount = max(0, (int) $settings->qa_rating_delay_amount);

        return match ($settings->qa_rating_delay_unit) {
            'minutes' => now()->subMinutes($amount),
            'hours' => now()->subHours($amount),
            default => now()->subDays($amount),
        };
    }
}
