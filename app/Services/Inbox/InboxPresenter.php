<?php

namespace App\Services\Inbox;

use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\InboxSetting;
use App\Models\Order;
use App\Models\User;
use App\Support\InboxPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InboxPresenter
{
    public function __construct(private ConversationWindow $window) {}

    /**
     * @return array<string, mixed>
     */
    public function conversation(InboxConversation $conversation, ?User $viewer = null): array
    {
        $conversation->loadMissing(['contact', 'assignee']);
        $settings = InboxSetting::current();
        $window = $this->window->for($conversation);
        $aiEnabled = $settings->botIsEnabled();
        $paused = $conversation->isBotPaused();

        $botDot = 'gray';
        if ($aiEnabled && ! $paused && ! $conversation->needs_human_agent) {
            $botDot = 'green';
        } elseif ($aiEnabled && ($paused || $conversation->needs_human_agent)) {
            $botDot = 'orange';
        }

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'contact' => [
                'id' => $conversation->contact?->id,
                'name' => $conversation->contact?->displayName(),
                'phone' => $conversation->contact?->phone_number,
                'phone_display' => InboxPhone::display((string) $conversation->contact?->phone_number),
                'initials' => $this->initials($conversation->contact?->displayName() ?: '?'),
                'subscribed' => (bool) $conversation->contact?->subscribed,
            ],
            'assignee' => $conversation->assignee ? [
                'id' => $conversation->assignee->id,
                'name' => $conversation->assignee->name,
            ] : null,
            'assigned_to_me' => $viewer && (int) $conversation->assigned_user_id === (int) $viewer->id,
            'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
            'last_inbound_at' => optional($conversation->last_inbound_at)?->toIso8601String(),
            'preview' => $conversation->last_message_preview,
            'preview_direction' => $conversation->last_message_direction,
            'preview_sender' => $conversation->last_sender_type,
            'preview_type' => $conversation->last_message_type,
            'needs_human_agent' => (bool) $conversation->needs_human_agent,
            'handoff_reason' => $conversation->handoff_reason,
            'bot_paused' => $paused,
            'bot_paused_until' => optional($conversation->bot_paused_until)?->toIso8601String(),
            'bot_dot' => $botDot,
            'window_closed' => ! $window['is_open'],
            'window' => $window,
            'ai_lead_requirements' => $conversation->ai_lead_requirements,
            'ai_enabled' => $aiEnabled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function message(InboxMessage $message): array
    {
        $payload = $message->payload ?? [];

        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'sender_type' => $message->sender_type,
            'message_type' => $message->message_type,
            'status' => $message->status,
            'error_message' => $message->error_message,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'is_voice' => $message->isVoiceNote(),
            'payload' => [
                'text' => $payload['text'] ?? $payload['caption'] ?? null,
                'caption' => $payload['caption'] ?? null,
                'media_url' => $payload['media_url'] ?? $payload['header_media_url'] ?? null,
                'mime_type' => $payload['mime_type'] ?? null,
                'filename' => $payload['filename'] ?? null,
                'template_name' => $payload['template_name'] ?? null,
                'language' => $payload['language'] ?? null,
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'name' => $payload['name'] ?? null,
                'address' => $payload['address'] ?? null,
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, InboxConversation>  $paginator
     * @return array<string, mixed>
     */
    public function paginated($paginator, ?User $viewer = null): array
    {
        return [
            'data' => $paginator->getCollection()->map(fn (InboxConversation $c) => $this->conversation($c, $viewer))->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function counts(?User $viewer = null): array
    {
        $base = InboxConversation::query();

        return [
            'open' => (clone $base)->where('status', InboxConversation::STATUS_OPEN)->count(),
            'pending' => (clone $base)->where('status', InboxConversation::STATUS_PENDING)->count(),
            'closed' => (clone $base)->where('status', InboxConversation::STATUS_CLOSED)->count(),
            'unassigned' => (clone $base)->whereNull('assigned_user_id')->where('status', '!=', InboxConversation::STATUS_CLOSED)->count(),
            'needs_human' => (clone $base)->where('needs_human_agent', true)->where('status', '!=', InboxConversation::STATUS_CLOSED)->count(),
            'mine' => $viewer
                ? (clone $base)->where('assigned_user_id', $viewer->id)->where('status', '!=', InboxConversation::STATUS_CLOSED)->count()
                : 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bookingsFor(InboxConversation $conversation): array
    {
        $phone = (string) $conversation->contact?->phone_number;
        $variants = InboxPhone::lookupVariants($phone);

        if ($variants === []) {
            return [];
        }

        return Order::query()
            ->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('customer_phone', $variant)
                        ->orWhere('customer_phone', 'like', '%'.ltrim($variant, '+').'%');
                }
            })
            ->latest('id')
            ->limit(20)
            ->get([
                'id',
                'order_number',
                'status',
                'payment_status',
                'items',
                'dismantling_at',
                'activity_date',
                'total_amount',
                'amount_paid',
                'currency',
            ])
            ->map(function (Order $order) {
                $items = is_array($order->items) ? $order->items : [];
                $names = collect($items)->map(function ($item) {
                    if (! is_array($item)) {
                        return null;
                    }

                    return $item['product_name'] ?? $item['name'] ?? null;
                })->filter()->take(3)->implode('، ');

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'order_type' => $order->payment_status,
                    'vehicle' => $names !== '' ? $names : '—',
                    'expected_return' => optional($order->dismantling_at ?? $order->activity_date)?->toDateString(),
                    'remaining_amount' => $order->remaining_amount,
                    'currency' => $order->currency ?: 'SAR',
                    'url' => url('/orders/'.$order->id),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function agents(): array
    {
        return User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER])
            ->orderBy('customer_name')
            ->get(['id', 'customer_name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : '?';
    }
}
