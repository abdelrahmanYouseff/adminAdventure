<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

class DeliveryNotePdfData
{
    public function __construct(public Order $order) {}

    public static function fromOrder(Order $order): self
    {
        $order->load([
            'invoice:id,invoice_number',
            'products',
            'workerOrders' => fn ($query) => $query->orderBy('line_index'),
        ]);

        return new self($order);
    }

    public function logoPath(): string
    {
        return public_path('assets/logo.png');
    }

    public function hasLogo(): bool
    {
        return file_exists($this->logoPath());
    }

    public function referenceNumber(): string
    {
        return $this->order->invoice?->invoice_number ?? $this->order->order_number;
    }

    public function orderNumber(): string
    {
        return $this->order->order_number;
    }

    public function invoiceNumber(): ?string
    {
        return $this->order->invoice?->invoice_number;
    }

    public function customerName(): string
    {
        return $this->order->customer_name
            ?: $this->order->workerOrders->first()?->customer_name
            ?: '—';
    }

    public function customerPhone(): string
    {
        return $this->order->customer_phone ?: '—';
    }

    public function customerEmail(): string
    {
        return $this->order->customer_email ?: '—';
    }

    public function address(): ?string
    {
        $address = $this->order->address
            ?: $this->order->workerOrders->first()?->customer_address;

        return $address && trim($address) !== '' ? trim($address) : null;
    }

    public function eventDate(): ?string
    {
        $date = $this->order->activity_date
            ?: $this->order->workerOrders->first()?->installation_date;

        return $date ? $this->formatDate($date) : null;
    }

    public function issueDate(): string
    {
        return $this->formatDate(now());
    }

    public function generatedAt(): string
    {
        return now()->format('Y-m-d H:i');
    }

    /**
     * @return array<int, array{name: string, quantity: int}>
     */
    public function productLines(): array
    {
        $quantities = $this->resolveQuantitiesByProduct();

        return $this->order->workerOrders
            ->map(function ($line) use ($quantities) {
                $key = $line->product_id ?? $line->product_name;

                return [
                    'name' => $line->product_name,
                    'quantity' => $quantities[$key] ?? 1,
                ];
            })
            ->values()
            ->all();
    }

    public function productsCount(): int
    {
        return count($this->productLines());
    }

    public function companyName(): string
    {
        return 'شركة عالم المغامرة للترفيه';
    }

    public function companyPhone(): string
    {
        return '0114101840 - 0559668015';
    }

    public function companyEmail(): string
    {
        return 'info@adventureksa.com';
    }

    /**
     * @return array<string|int, int>
     */
    private function resolveQuantitiesByProduct(): array
    {
        $quantities = [];

        if ($this->order->relationLoaded('products') && $this->order->products->isNotEmpty()) {
            foreach ($this->order->products as $product) {
                $quantities[$product->id] = (int) ($product->pivot->quantity ?? 1);
            }
        }

        if ($this->order->items) {
            foreach ($this->order->items as $item) {
                $name = $item['name'] ?? $item['product_name'] ?? null;
                if ($name) {
                    $quantities[$name] = (int) ($item['quantity'] ?? 1);
                }
            }
        }

        return $quantities;
    }

    private function formatDate(mixed $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('Y-m-d');
    }
}
