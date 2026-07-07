<?php

namespace App\Support;

use App\Models\Quotation;
use Carbon\Carbon;

class QuotationPdfData
{
    public function __construct(public Quotation $quotation) {}

    public static function fromQuotation(Quotation $quotation): self
    {
        $quotation->load(['user', 'items']);

        return new self($quotation);
    }

    public function logoPath(): string
    {
        return public_path('assets/logo.png');
    }

    public function quotationNumber(): string
    {
        return $this->quotation->quotation_number;
    }

    public function issueDate(): string
    {
        return $this->formatDate($this->quotation->created_at);
    }

    public function validUntilDate(): string
    {
        return $this->formatDate($this->quotation->valid_until);
    }

    public function statusLabel(): string
    {
        return match ($this->quotation->status) {
            'draft' => 'مسودة',
            'sent' => 'مرسل',
            'accepted' => 'مقبول',
            'rejected' => 'مرفوض',
            'expired' => 'منتهي الصلاحية',
            default => $this->quotation->status,
        };
    }

    public function statusClass(): string
    {
        return match ($this->quotation->status) {
            'draft' => 'status-draft',
            'sent' => 'status-sent',
            'accepted' => 'status-accepted',
            'rejected' => 'status-rejected',
            'expired' => 'status-expired',
            default => 'status-draft',
        };
    }

    public function customerName(): string
    {
        return $this->quotation->customer_name ?: '—';
    }

    public function customerEmail(): string
    {
        return $this->quotation->customer_email ?: '—';
    }

    public function customerPhone(): string
    {
        return $this->quotation->customer_phone ?: '—';
    }

    public function customerAddress(): ?string
    {
        $address = $this->quotation->customer_address;

        return $address && trim($address) !== '' ? trim($address) : null;
    }

    public function preparedBy(): string
    {
        return $this->quotation->user?->name ?? '—';
    }

    /**
     * @return array<int, array{name: string, description: ?string, quantity: int, unit_price: float, total: float}>
     */
    public function lineItems(): array
    {
        return $this->quotation->items->map(fn ($item) => [
            'name' => $item->product_name,
            'description' => $item->description && trim($item->description) !== '' ? trim($item->description) : null,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total' => (float) $item->total_price,
        ])->all();
    }

    public function subtotal(): float
    {
        return round((float) $this->quotation->subtotal, 2);
    }

    public function vatAmount(): float
    {
        return round((float) $this->quotation->tax_amount, 2);
    }

    public function total(): float
    {
        return round((float) $this->quotation->total_amount, 2);
    }

    public function notes(): ?string
    {
        $notes = $this->quotation->notes;

        return $notes && trim($notes) !== '' ? trim($notes) : null;
    }

    public function generatedAt(): string
    {
        return now()->format('Y-m-d H:i');
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2).' ر.س';
    }

    public function companyLegalName(): string
    {
        return 'شركة عالم المغامرة للترفيه';
    }

    public function bankName(): string
    {
        return 'بنك الرياض';
    }

    public function bankAccountNumber(): string
    {
        return '2022273529940';
    }

    public function bankIban(): string
    {
        return 'SA7820000002022273529940';
    }

    public function vatNumber(): string
    {
        return '311691903100003';
    }

    public function commercialRegister(): string
    {
        return '1010792791';
    }

    /**
     * @return array<int, string>
     */
    public function paymentMethods(): array
    {
        return [
            'تحويل بنكي',
            'تابي (Tabby)',
            'تمارا (Tamara)',
            'رابط دفع إلكتروني',
        ];
    }

    private function formatDate(mixed $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('Y-m-d');
    }
}
