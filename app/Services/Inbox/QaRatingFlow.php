<?php

namespace App\Services\Inbox;

use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\InboxServiceRating;
use App\Models\InboxSetting;
use App\Models\Order;
use App\Support\InboxPhone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QaRatingFlow
{
    public function __construct(private WhatsAppGraphClient $graph) {}

    public function activeFor(InboxConversation $conversation): ?InboxServiceRating
    {
        return InboxServiceRating::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', [InboxServiceRating::STATUS_INVITE, InboxServiceRating::STATUS_IN_PROGRESS])
            ->latest('id')
            ->first();
    }

    public function handleInbound(InboxConversation $conversation, InboxMessage $message): bool
    {
        $rating = $this->activeFor($conversation);

        if (! $rating) {
            return false;
        }

        $text = trim((string) (($message->payload['text'] ?? '') ?: ($message->payload['caption'] ?? '')));
        $buttonId = strtolower((string) (($message->payload['button_id'] ?? '') ?: ''));

        if ($buttonId !== '' && (str_contains($buttonId, 'unsub') || str_contains($text, 'إلغاء الاشتراك') || strcasecmp($text, 'Unsubscribe') === 0)) {
            $conversation->contact?->forceFill(['subscribed' => false])->save();
            $this->sendText($conversation, "تم إلغاء الاشتراك من الرسائل التسويقية.\nYou have been unsubscribed from marketing messages.");

            return true;
        }

        if ($rating->step === InboxServiceRating::STEP_AWAITING_SCORE) {
            if (preg_match('/^[1-5]$/', $text)) {
                $rating->forceFill([
                    'score' => (int) $text,
                    'status' => InboxServiceRating::STATUS_IN_PROGRESS,
                    'step' => InboxServiceRating::STEP_AWAITING_COMMENT,
                ])->save();

                $this->sendText(
                    $conversation,
                    "شكراً لتقييمك {$text}/5. يمكنك كتابة تعليق أو إرسال صورة (اختياري)، أو أرسل تخطي.\nThanks for rating {$text}/5. You may send a comment or photo, or type Skip."
                );

                return true;
            }

            $this->sendText(
                $conversation,
                "رجاءً أرسل رقماً من 1 إلى 5 لتقييم الخدمة.\nPlease reply with a number from 1 to 5."
            );

            return true;
        }

        if ($rating->step === InboxServiceRating::STEP_AWAITING_COMMENT) {
            $skip = in_array(mb_strtolower($text), ['skip', 'تخطي', 'لا', 'no'], true);

            $rating->forceFill([
                'comment' => $skip ? null : ($text !== '' ? $text : $rating->comment),
                'media_uuid' => $message->payload['media_uuid'] ?? $rating->media_uuid,
                'status' => InboxServiceRating::STATUS_COMPLETED,
                'step' => InboxServiceRating::STEP_DONE,
                'completed_at' => now(),
            ])->save();

            $this->notifyEmails($rating);
            $this->sendText($conversation, "تم استلام تقييمك، شكراً لك.\nYour feedback was received. Thank you.");

            return true;
        }

        return true;
    }

    public function sendInvite(InboxConversation $conversation, ?Order $order = null): InboxServiceRating
    {
        $settings = InboxSetting::current();
        $template = trim((string) $settings->qa_rating_template_name);
        $language = trim((string) $settings->qa_rating_template_language) ?: 'ar';

        if ($template === '') {
            throw new \RuntimeException('قالب تقييم الخدمة غير مضبوط.');
        }

        $to = InboxPhone::normalize((string) $conversation->contact?->phone_number);
        $result = $this->graph->sendTemplate($to, $template, $language);

        $rating = InboxServiceRating::query()->create([
            'conversation_id' => $conversation->id,
            'order_id' => $order?->id,
            'status' => InboxServiceRating::STATUS_INVITE,
            'step' => InboxServiceRating::STEP_AWAITING_SCORE,
            'invited_at' => now(),
        ]);

        InboxMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => InboxMessage::SENDER_SYSTEM,
            'direction' => InboxMessage::DIRECTION_OUTBOUND,
            'message_type' => InboxMessage::TYPE_TEMPLATE,
            'payload' => [
                'template_name' => $template,
                'language' => $language,
                'text' => 'QA invite',
            ],
            'external_message_id' => $result['message_id'],
            'status' => $result['success'] ? InboxMessage::STATUS_SENT : InboxMessage::STATUS_FAILED,
            'error_message' => $result['error'],
        ]);

        if (! $result['success']) {
            $rating->delete();
            throw new \RuntimeException($result['error'] ?: 'فشل إرسال قالب التقييم');
        }

        return $rating;
    }

    private function sendText(InboxConversation $conversation, string $body): void
    {
        $to = InboxPhone::normalize((string) $conversation->contact?->phone_number);
        $result = $this->graph->sendText($to, $body);

        $message = InboxMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => InboxMessage::SENDER_SYSTEM,
            'direction' => InboxMessage::DIRECTION_OUTBOUND,
            'message_type' => InboxMessage::TYPE_TEXT,
            'payload' => ['text' => $body],
            'external_message_id' => $result['message_id'],
            'status' => $result['success'] ? InboxMessage::STATUS_SENT : InboxMessage::STATUS_FAILED,
            'error_message' => $result['error'],
        ]);

        $conversation->applyLastMessage($message);
    }

    private function notifyEmails(InboxServiceRating $rating): void
    {
        $emails = InboxSetting::current()->qaNotifyEmails();

        if ($emails === []) {
            return;
        }

        $rating->load(['conversation.contact', 'order']);
        $body = sprintf(
            "تقييم خدمة واتساب\nالرقم: %s\nالدرجة: %s/5\nالتعليق: %s\nالطلب: %s",
            $rating->conversation?->contact?->phone_number,
            $rating->score,
            $rating->comment ?: '—',
            $rating->order?->order_number ?: '—'
        );

        try {
            Mail::raw($body, function ($message) use ($emails) {
                $message->to($emails)->subject('تقييم خدمة واتساب — عالم المغامرة');
            });
        } catch (\Throwable $e) {
            Log::warning('QA rating email failed', ['error' => $e->getMessage()]);
        }
    }
}
