<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;

class InvoicePdfData
{
    private const VAT_RATE = 0.15;

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

    public function hasLogo(): bool
    {
        return file_exists($this->logoPath());
    }

    public function invoiceNumber(): string
    {
        return $this->invoice->invoice_number;
    }

    public function issueDate(): string
    {
        return $this->formatDate($this->invoice->issued_at ?? $this->invoice->created_at);
    }

    public function issueDateLong(): string
    {
        return $this->formatDateLong($this->invoice->issued_at ?? $this->invoice->created_at);
    }

    public function dueDate(): ?string
    {
        return $this->invoice->due_date
            ? $this->formatDate($this->invoice->due_date)
            : null;
    }

    public function dueDateLong(): ?string
    {
        return $this->invoice->due_date
            ? $this->formatDateLong($this->invoice->due_date)
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

    public function statusLabelEn(): string
    {
        return match ($this->invoice->status) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
            'overdue' => 'Overdue',
            default => ucfirst((string) $this->invoice->status),
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

    public function paymentMethodLabelEn(): string
    {
        return match ($this->invoice->payment_method) {
            'noon' => 'Noon Pay',
            'mock' => 'Test Payment',
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
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

    public function activityDateLong(): ?string
    {
        if (! $this->order?->activity_date) {
            return null;
        }

        return $this->formatDateLong($this->order->activity_date);
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
                    'name' => $item['name'] ?? $item['product_name'] ?? 'Product',
                    'quantity' => $quantity,
                    'duration' => $duration,
                    'unit_price' => $unitPrice,
                    'total' => $total,
                ];
            })->all();
        }

        if ($this->invoice->rental?->product) {
            $product = $this->invoice->rental->product;
            $amount = round((float) $this->invoice->amount / 1.15, 2);

            return [[
                'name' => $product->product_name,
                'quantity' => 1,
                'duration' => null,
                'unit_price' => $amount,
                'total' => $amount,
            ]];
        }

        $amount = round((float) $this->invoice->amount / 1.15, 2);

        return [[
            'name' => 'Adventure World Service',
            'quantity' => 1,
            'duration' => null,
            'unit_price' => $amount,
            'total' => $amount,
        ]];
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     description: ?string,
     *     quantity: int,
     *     unit_price: float,
     *     unit_price_incl_vat: float,
     *     taxable_value: float,
     *     vat_percent: string,
     *     vat_amount: float,
     *     total: float
     * }>
     */
    public function lineItemRows(): array
    {
        return collect($this->lineItems())->map(function (array $item) {
            $taxable = round((float) $item['total'], 2);
            $vat = round($taxable * self::VAT_RATE, 2);
            $unitEx = (float) $item['unit_price'];
            $description = null;

            if (! empty($item['duration']) && $item['duration'] > 1) {
                $description = 'Rental duration: '.$item['duration'].' day(s)';
            }

            return [
                'name' => $item['name'],
                'description' => $description,
                'quantity' => (int) $item['quantity'],
                'unit_price' => $unitEx,
                'unit_price_incl_vat' => round($unitEx * (1 + self::VAT_RATE), 4),
                'taxable_value' => $taxable,
                'vat_percent' => '15%',
                'vat_amount' => $vat,
                'total' => round($taxable + $vat, 2),
            ];
        })->all();
    }

    public function subtotal(): float
    {
        $items = $this->lineItems();

        if (count($items) > 0 && $this->order && ! empty($this->order->items)) {
            return round(collect($items)->sum('total'), 2);
        }

        $insurance = $this->insuranceAmount();
        $gross = round((float) $this->invoice->amount - $insurance, 2);

        return round(max(0, $gross) / (1 + self::VAT_RATE), 2);
    }

    /**
     * مبلغ التأمين (بدون ضريبة) — مسترد عند الاستلام.
     */
    public function insuranceAmount(): float
    {
        if ($this->order && (float) $this->order->insurance_amount > 0) {
            return round((float) $this->order->insurance_amount, 2);
        }

        if ($this->order && ! empty($this->order->items)) {
            $fromItems = collect($this->order->items)->sum(function (array $item) {
                $unit = (float) ($item['insurance_amount'] ?? 0);
                $qty = max(1, (int) ($item['quantity'] ?? 1));

                return $unit * $qty;
            });

            return round((float) $fromItems, 2);
        }

        return 0.0;
    }

    public function hasInsurance(): bool
    {
        return $this->insuranceAmount() > 0;
    }

    public function vatAmount(): float
    {
        $derived = round((float) $this->invoice->amount - $this->subtotal() - $this->insuranceAmount(), 2);

        if ($derived >= -0.05) {
            return max(0.0, $derived);
        }

        return round($this->subtotal() * self::VAT_RATE, 2);
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

    public function insuranceNoteEn(): string
    {
        return 'Insurance deposit is refundable upon product pickup/collection after the event.';
    }

    public function insuranceNoteAr(): string
    {
        return 'مبلغ التأمين مسترد عند استلام المنتجات بعد انتهاء الفعالية.';
    }

    public function generatedAt(): string
    {
        return now()->format('Y-m-d H:i');
    }

    public function formatMoney(float $amount, int $decimals = 2): string
    {
        return number_format($amount, $decimals);
    }

    public function formatSar(float $amount, int $decimals = 2): string
    {
        return 'SAR '.number_format($amount, $decimals);
    }

    public function companyLegalNameAr(): string
    {
        return 'شركة عالم المغامرة للترفيه';
    }

    public function companyLegalNameEn(): string
    {
        return 'Adventure World Entertainment Company';
    }

    public function companyAddress(): string
    {
        return 'Al Muruj - Riyadh - Saudi Arabia';
    }

    public function companyPhone(): string
    {
        return '0114101840 - 0559668015';
    }

    public function companyEmail(): string
    {
        return 'info@adventureksa.com';
    }

    public function companyWebsite(): string
    {
        return 'www.adventureksa.com';
    }

    public function bankName(): string
    {
        return 'Riyad Bank / بنك الرياض';
    }

    public function bankAccountNumber(): string
    {
        return '2022273529940';
    }

    public function bankIban(): string
    {
        return 'SA7820000002022273529940';
    }

    public function bankAccountName(): string
    {
        return $this->companyLegalNameEn();
    }

    public function vatNumber(): string
    {
        return '311691903100003';
    }

    public function commercialRegister(): string
    {
        return '10101292911191';
    }

    /**
     * @return array<int, string>
     */
    public function termsAndConditions(): array
    {
        $terms = [
            'This is an Invoice issued in accordance with ZATCA regulations.',
            'VAT is calculated at 15% on the taxable value of the services.',
            'Payment is due according to the due date stated on this invoice.',
            'Please include the invoice number as a reference for bank transfers.',
            'For any inquiries regarding this invoice, contact '.$this->companyEmail().'.',
        ];

        if ($this->hasInsurance()) {
            $terms[] = $this->insuranceNoteEn().' / '.$this->insuranceNoteAr();
        }

        if ($this->invoice->status === 'paid') {
            array_unshift($terms, 'Payment for this invoice has been received in full. Thank you.');
        }

        if ($this->notes()) {
            $terms[] = $this->notes();
        }

        return $terms;
    }

    private function formatDate(mixed $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('Y-m-d');
    }

    private function formatDateLong(mixed $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('d/F/Y');
    }
}
