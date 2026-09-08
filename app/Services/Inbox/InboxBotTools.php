<?php

namespace App\Services\Inbox;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InboxContact;
use App\Models\InboxConversation;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Support\InboxPhone;

class InboxBotTools
{
    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            $this->fn('get_customer_profile', 'Get the WhatsApp contact profile and subscription status.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->fn('get_active_bookings', 'List recent orders/bookings for this customer.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->fn('get_outstanding_invoices', 'List unpaid or overdue invoices linked to this customer.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->fn('list_pickup_locations', 'List company/brand pickup and contact locations.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->fn('list_vehicle_categories', 'List rental product categories.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->fn('search_fleet', 'Search the rental catalog by name.', [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
                'required' => ['query'],
            ]),
            $this->fn('save_lead_requirements', 'Save what the customer wants so agents can see it in the inbox header.', [
                'type' => 'object',
                'properties' => [
                    'requirements' => ['type' => 'string'],
                ],
                'required' => ['requirements'],
            ]),
            $this->fn('request_human_agent', 'Escalate to a human agent and pause the bot indefinitely.', [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string'],
                ],
            ]),
            $this->fn('unsubscribe_from_marketing', 'Unsubscribe the customer from marketing WhatsApp messages.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->fn('subscribe_to_marketing', 'Re-subscribe the customer to marketing WhatsApp messages.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(InboxConversation $conversation, string $name, array $arguments): array
    {
        $contact = $conversation->contact;

        return match ($name) {
            'get_customer_profile' => $this->customerProfile($contact),
            'get_active_bookings' => ['bookings' => $this->orders($contact)],
            'get_outstanding_invoices' => ['invoices' => $this->invoices($contact)],
            'list_pickup_locations' => ['locations' => $this->locations()],
            'list_vehicle_categories' => ['categories' => $this->categories()],
            'search_fleet' => ['results' => $this->search((string) ($arguments['query'] ?? ''))],
            'save_lead_requirements' => $this->saveLead($conversation, (string) ($arguments['requirements'] ?? '')),
            'request_human_agent' => $this->handoff($conversation, (string) ($arguments['reason'] ?? 'bot')),
            'unsubscribe_from_marketing' => $this->setSubscribed($contact, false),
            'subscribe_to_marketing' => $this->setSubscribed($contact, true),
            default => ['error' => 'unknown_tool'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function customerProfile(?InboxContact $contact): array
    {
        if (! $contact) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'name' => $contact->displayName(),
            'phone' => $contact->phone_number,
            'subscribed' => $contact->subscribed,
            'last_active_at' => optional($contact->last_active_at)?->toDateTimeString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orders(?InboxContact $contact): array
    {
        if (! $contact) {
            return [];
        }

        $variants = InboxPhone::lookupVariants($contact->phone_number);

        return Order::query()
            ->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('customer_phone', $variant)
                        ->orWhere('customer_phone', 'like', '%'.ltrim($variant, '+').'%');
                }
            })
            ->latest('id')
            ->limit(10)
            ->get(['id', 'order_number', 'status', 'payment_status', 'activity_date', 'dismantling_at', 'total_amount', 'amount_paid', 'items'])
            ->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'activity_date' => optional($order->activity_date)?->toDateString(),
                'expected_return' => optional($order->dismantling_at)?->toDateString(),
                'remaining' => $order->remaining_amount,
                'items' => collect(is_array($order->items) ? $order->items : [])
                    ->map(fn ($item) => is_array($item) ? ($item['product_name'] ?? null) : null)
                    ->filter()
                    ->values(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoices(?InboxContact $contact): array
    {
        $orders = collect($this->orders($contact));
        $numbers = $orders->pluck('order_number')->filter()->all();

        if ($numbers === []) {
            return Invoice::query()
                ->whereIn('status', ['pending', 'overdue', 'unpaid', 'partial'])
                ->latest('id')
                ->limit(5)
                ->get(['invoice_number', 'amount', 'status', 'due_date'])
                ->map(fn (Invoice $invoice) => [
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoice->amount,
                    'status' => $invoice->status,
                    'due_date' => optional($invoice->due_date)?->toDateString(),
                ])
                ->all();
        }

        return Order::query()
            ->whereIn('order_number', $numbers)
            ->where(function ($q) {
                $q->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', ['paid']);
            })
            ->get(['order_number', 'total_amount', 'amount_paid', 'payment_status', 'currency'])
            ->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'remaining' => $order->remaining_amount,
                'payment_status' => $order->payment_status,
                'currency' => $order->currency,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function locations(): array
    {
        return Brand::query()
            ->where('is_active', true)
            ->get(['name', 'phone'])
            ->map(fn (Brand $brand) => [
                'name' => $brand->name,
                'phone' => $brand->contactPhone(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(): array
    {
        return Category::query()
            ->orderBy('category_name')
            ->limit(40)
            ->get(['id', 'category_name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->category_name,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        return Product::query()
            ->catalog()
            ->where('product_name', 'like', '%'.$query.'%')
            ->limit(12)
            ->get(['product_name', 'price', 'insurance_amount', 'description'])
            ->map(fn (Product $product) => [
                'name' => $product->product_name,
                'price' => $product->price,
                'insurance' => $product->insurance_amount,
                'description' => mb_substr((string) $product->description, 0, 180),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function saveLead(InboxConversation $conversation, string $requirements): array
    {
        $conversation->forceFill([
            'ai_lead_requirements' => mb_substr(trim($requirements), 0, 2000),
        ])->save();

        return ['saved' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function handoff(InboxConversation $conversation, string $reason): array
    {
        $conversation->requestHuman($reason !== '' ? $reason : 'bot');

        return ['handed_off' => true, 'reason' => $conversation->handoff_reason];
    }

    /**
     * @return array<string, mixed>
     */
    private function setSubscribed(?InboxContact $contact, bool $subscribed): array
    {
        $contact?->forceFill(['subscribed' => $subscribed])->save();

        return ['subscribed' => $subscribed];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function fn(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ],
        ];
    }
}
