<?php

namespace App\Console\Commands;

use App\Models\InboxServiceRating;
use App\Models\InboxSetting;
use App\Models\Order;
use App\Services\Inbox\OutboundMessageSender;
use App\Services\Inbox\QaRatingFlow;
use App\Support\InboxPhone;
use Illuminate\Console\Command;

class SendInboxQaInvites extends Command
{
    protected $signature = 'inbox:send-qa-invites';

    protected $description = 'Send WhatsApp QA rating invites after returned orders';

    public function handle(QaRatingFlow $flow, OutboundMessageSender $outbound): int
    {
        $settings = InboxSetting::current();

        if (! $settings->qa_rating_enabled || ! filled($settings->qa_rating_template_name)) {
            return self::SUCCESS;
        }

        $cutoff = match ($settings->qa_rating_delay_unit) {
            'minutes' => now()->subMinutes((int) $settings->qa_rating_delay_amount),
            'hours' => now()->subHours((int) $settings->qa_rating_delay_amount),
            default => now()->subDays((int) $settings->qa_rating_delay_amount),
        };

        $orders = Order::query()
            ->whereNotNull('warehouse_returned_at')
            ->where('warehouse_returned_at', '<=', $cutoff)
            ->whereNotNull('customer_phone')
            ->whereNotIn('id', InboxServiceRating::query()->whereNotNull('order_id')->select('order_id'))
            ->limit(20)
            ->get();

        foreach ($orders as $order) {
            if (! InboxPhone::isValidE164((string) $order->customer_phone)) {
                continue;
            }

            $conversation = $outbound->startConversation((string) $order->customer_phone, $order->customer_name);

            try {
                $flow->sendInvite($conversation, $order);
            } catch (\Throwable $e) {
                $this->warn($e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
