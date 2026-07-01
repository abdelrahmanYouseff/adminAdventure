<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;

class InvoicePdfData
{
    public function __construct(
        public Invoice $invoice,
        public ?Order $order = null,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        $invoice->load(['user', 'rental.product', 'order']);

        return new self($invoice, $invoice->order);
    }

    public function logoPath(): string
    {
        return public_path('assets/logo.png');
    }

    public function invoiceNumber(): string
    {
        return $this->invoice->invoice_number;
    }

    public function issueDate(): string
    {
        return $this->formatDate($this->invoice->issued_at ?? $this->invoice->created_at);
    }

    public function dueDate(): ?string
    {
        return $this->invoice->due_date
            ? $this->formatDate($this->invoice->due_date)
            : null;
    }

    public function statusLabel(): string
    {
        return match ($this->invoice->status) {
            'paid' => 'مدفوعة',
            'pending' => 'قيد الانتظار',
            'cancelled' => 'ملغاة',
            'overdue' => 'متأخرة',
            default => $this->invoice->status,
        };
    }

    public function statusClass(): string
    {
        return match ($this->invoice->status) {
            'paid' => 'status-paid',
            'pending' => 'status-pending',
            'cancelled' => 'status-cancelled',
            'overdue' => 'status-overdue',
            default => 'status-pending',
        };
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->invoice->payment_method) {
            'noon' => 'نون باي',
            'mock' => 'دفع تجريبي',
            'cash' => 'نقداً',
            'bank_transfer' => 'تحويل بنكي',
            default => $this->invoice->payment_method ?: '—',
        };
    }

    public function customerName(): string
    {
        if ($this->order?->customer_name) {
            return $this->order->customer_name;
        }

        $user = $this->invoice->user;

        return $user?->customer_name
            ?? $user?->name
            ?? '—';
    }

    public function customerEmail(): string
    {
        return $this->order?->customer_email
            ?? $this->invoice->user?->email
            ?? '—';
    }

    public function customerPhone(): string
    {
        return $this->order?->customer_phone
            ?? $this->invoice->user?->phone
            ?? '—';
    }

    public function activityDate(): ?string
    {
        if (! $this->order?->activity_date) {
            return null;
        }

        return $this->formatDate($this->order->activity_date);
    }

    public function address(): ?string
    {
        $address = $this->order?->address;

        return $address && trim($address) !== '' ? trim($address) : null;
    }

    /**
     * @return array<int, array{name: string, quantity: int, duration: ?int, unit_price: float, total: float}>
     */
    public function lineItems(): array
    {
        if ($this->order && ! empty($this->order->items)) {
            return collect($this->order->items)->map(function (array $item) {
                $quantity = (int) ($item['quantity'] ?? 1);
                $duration = isset($item['duration']) ? (int) $item['duration'] : null;
                $unitPrice = (float) ($item['price'] ?? 0);
                $total = (float) ($item['amount'] ?? ($unitPrice * $quantity * max(1, $duration ?? 1)));

                return [
                    'name' => $item['name'] ?? $item['product_name'] ?? 'منتج',
                    'quantity' => $quantity,
                    'duration' => $duration,
                    'unit_price' => $unitPrice,
                    'total' => $total,
                ];
            })->all();
        }

        if ($this->invoice->rental?->product) {
            $product = $this->invoice->rental->product;
            $amount = (float) $this->invoice->amount;

            return [[
                'name' => $product->product_name,
                'quantity' => 1,
                'duration' => null,
                'unit_price' => $amount,
                'total' => $amount,
            ]];
        }

        $amount = (float) $this->invoice->amount;

        return [[
            'name' => 'خدمة عالم المغامرة',
            'quantity' => 1,
            'duration' => null,
            'unit_price' => $amount,
            'total' => $amount,
        ]];
    }

    public function subtotal(): float
    {
        $items = $this->lineItems();

        if (count($items) > 0 && $this->order && ! empty($this->order->items)) {
            return round(collect($items)->sum('total'), 2);
        }

        return round((float) $this->invoice->amount / 1.15, 2);
    }

    public function vatAmount(): float
    {
        return round((float) $this->invoice->amount - $this->subtotal(), 2);
    }

    public function total(): float
    {
        return round((float) $this->invoice->amount, 2);
    }

    public function currencyLabel(): string
    {
        return 'ر.س';
    }

    public function notes(): ?string
    {
        $notes = $this->order?->notes ?? null;

        return $notes && trim($notes) !== '' ? trim($notes) : null;
    }

    public function generatedAt(): string
    {
        return now()->format('Y-m-d H:i');
    }

    private function formatDate(mixed $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('Y-m-d');
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2).' ر.س';
    }
}
