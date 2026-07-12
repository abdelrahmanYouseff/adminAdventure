<?php

namespace App\Support;

use App\Models\Quotation;
use Carbon\Carbon;

class QuotationPdfData
{
    private const VAT_RATE = 0.15;

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

    public function hasLogo(): bool
    {
        return file_exists($this->logoPath());
    }

    public function quotationNumber(): string
    {
        return $this->quotation->quotation_number;
    }

    public function issueDate(): string
    {
        return $this->formatDate($this->quotation->created_at);
    }

    public function issueDateLong(): string
    {
        return $this->formatDateLong($this->quotation->created_at);
    }

    public function validUntilDate(): string
    {
        return $this->formatDate($this->quotation->valid_until);
    }

    public function validUntilLong(): string
    {
        return $this->formatDateLong($this->quotation->valid_until);
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
        return $this->quotation->items->map(function ($item) {
            $taxable = round((float) $item->total_price, 2);
            $vat = round($taxable * self::VAT_RATE, 2);
            $unitEx = (float) $item->unit_price;

            return [
                'name' => $item->product_name,
                'description' => $item->description && trim($item->description) !== '' ? trim($item->description) : null,
                'quantity' => (int) $item->quantity,
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
        return '1010792791';
    }

    /**
     * @return array<int, string>
     */
    public function termsAndConditions(): array
    {
        $terms = [
            'You may request to cancel your booking for a full refund, up to 48 hours before the date of the event.',
            'Payment terms: 100% payment in advance to confirm the booking.',
            'The final Tax Invoice will be provided upon completion of the event.',
            'Kindly provide your confirmation within 48 hours of receiving this proposal.',
            'Non-advance payment: please note that in case of a last-minute cancellation, charges will apply according to the terms outlined in this proposal.',
        ];

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
