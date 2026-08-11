<?php

namespace App\Support;

use App\Models\Quotation;
use Carbon\Carbon;

class QuotationPdfData
{
    private const VAT_RATE = 0.15;

    public function __construct(
        public Quotation $quotation,
        public string $locale = 'en',
    ) {}

    public static function fromQuotation(Quotation $quotation, string $locale = 'en'): self
    {
        $quotation->load(['user', 'items', 'brand']);

        return new self($quotation, in_array($locale, ['ar', 'en'], true) ? $locale : 'en');
    }

    public function isArabic(): bool
    {
        return $this->locale === 'ar';
    }

    public function logoPath(): string
    {
        $brandLogo = $this->brandLogoAbsolutePath();

        if ($brandLogo) {
            return $brandLogo;
        }

        return public_path('assets/logo.png');
    }

    public function hasLogo(): bool
    {
        return file_exists($this->logoPath());
    }

    public function logoAlt(): string
    {
        return $this->quotation->brand?->name ?: ($this->isArabic() ? 'عالم المغامرة' : 'Adventure World');
    }

    private function brandLogoAbsolutePath(): ?string
    {
        $logo = $this->quotation->brand?->logo;

        if (! $logo) {
            return null;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return null;
        }

        $relative = ltrim($logo, '/');
        $candidates = [
            storage_path('app/public/'.$relative),
            public_path('storage/'.$relative),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
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

    public function activityAt(): ?string
    {
        return $this->formatDateTime($this->quotation->activity_at);
    }

    public function installationAt(): ?string
    {
        return $this->formatDateTime($this->quotation->installation_at);
    }

    public function dismantlingAt(): ?string
    {
        return $this->formatDateTime($this->quotation->dismantling_at);
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

    public function companyTaxNumber(): ?string
    {
        $tax = $this->quotation->company_tax_number;

        return $tax && trim($tax) !== '' ? trim($tax) : null;
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
     *     discount_amount: float,
     *     net_unit_price: float,
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
            $discount = (float) ($item->discount_amount ?? 0);
            $netUnitPrice = max(0, $unitEx - $discount);

            return [
                'name' => $item->product_name,
                'description' => $item->description && trim($item->description) !== '' ? trim($item->description) : null,
                'statement' => $item->statement && trim((string) $item->statement) !== '' ? trim((string) $item->statement) : null,
                'quantity' => (int) $item->quantity,
                'unit_price' => $unitEx,
                'discount_amount' => $discount,
                'net_unit_price' => $netUnitPrice,
                'unit_price_incl_vat' => round($netUnitPrice * (1 + self::VAT_RATE), 4),
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

    public function discountTotal(): float
    {
        return round((float) ($this->quotation->discount_total ?? 0), 2);
    }

    public function grossSubtotal(): float
    {
        return round($this->subtotal() + $this->discountTotal(), 2);
    }

    public function vatAmount(): float
    {
        return round((float) $this->quotation->tax_amount, 2);
    }

    public function insuranceAmount(): float
    {
        return round((float) ($this->quotation->insurance_amount ?? 0), 2);
    }

    public function hasInsurance(): bool
    {
        return $this->insuranceAmount() > 0;
    }

    public function total(): float
    {
        return round((float) $this->quotation->total_amount, 2);
    }

    public function amountPaid(): float
    {
        return round((float) ($this->quotation->amount_paid ?? 0), 2);
    }

    public function amountDue(): float
    {
        return $this->quotation->amountDue();
    }

    public function hasAmountDue(): bool
    {
        return $this->amountDue() > 0.009;
    }

    public function paymentUrl(): ?string
    {
        return $this->quotation->paymentUrl();
    }

    public function insuranceNoteEn(): string
    {
        return 'Insurance deposit is refundable upon product pickup/collection after the event.';
    }

    public function insuranceNoteAr(): string
    {
        return 'مبلغ التأمين مسترد عند استلام المنتجات بعد انتهاء الفعالية.';
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
        $formatted = number_format($amount, $decimals);

        return $this->isArabic()
            ? $formatted.' ر.س'
            : 'SAR '.$formatted;
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
        return $this->isArabic()
            ? 'حي المروج - الرياض - المملكة العربية السعودية'
            : 'Al Muruj - Riyadh - Saudi Arabia';
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
        return $this->isArabic() ? 'بنك الرياض' : 'Riyad Bank';
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
        return $this->isArabic()
            ? $this->companyLegalNameAr()
            : $this->companyLegalNameEn();
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
        $terms = $this->isArabic()
            ? [
                'يُسدَّد 50٪ من المبلغ عند الموافقة، مع إرفاق إيصال التحويل.',
                'يحوّل العميل مبلغ تأمين مسترد بنسبة 40٪ من قيمة الطلب بموجب إيصال منفصل، ويُسترد بعد التأكد من سلامة جميع الألعاب المسلَّمة.',
                'يتم التوريد والتركيب بعد تحويل مبلغ التأمين.',
                'في حال تأخر العميل عن إعادة الألعاب، تُحتسب غرامة بنسبة 120٪ عن كل يوم تأخير في التسليم.',
                'في حال إلغاء الفعالية من قبل العميل لا يُسترد المبلغ، ويُسجَّل رصيدًا دائنًا لدى الشركة يمكن استخدامه خارج المواسم والإجازات الرسمية.',
                'أي عطل فني ناتج عن سوء استخدام الألعاب بعد تسليمها من الشركة يقع كاملًا على مسؤولية العميل.',
                'يتحمل العميل كامل التكلفة والمسؤولية إذا اختلفت تفاصيل الموقع عن الوصف الفعلي (بما في ذلك ما يتعلق بالتركيب).',
            ]
            : [
                '50% of the amount is payable upon approval, and the transfer receipt must be attached.',
                'A refundable security deposit of 40% of the order value is to be transferred by the client through a separate receipt, and it is refunded after confirming that all games delivered to the client are undamaged.',
                'Supply and installation of the games take place after the security deposit has been transferred.',
                'If the client delays the return of the games, a penalty of 120% is charged for each day of delay in delivery.',
                'If the client cancels the event, the amount is not refunded; it is recorded as a credit balance with the company, and the client may use it outside of seasons and official holidays.',
                'Any technical malfunction resulting from misuse of the games after their delivery by the company is the full responsibility of the client.',
                'The client bears the full cost and responsibility if the site details differ from the actual description (including with regard to installation).',
            ];

        if ($this->notes()) {
            $terms[] = $this->notes();
        }

        return $terms;
    }

    private function formatDate(mixed $date): string
    {
        if (! $date) {
            return '—';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('Y-m-d');
    }

    private function formatDateLong(mixed $date): string
    {
        if (! $date) {
            return '—';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('d/F/Y');
    }

    private function formatDateTime(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('d/m/Y H:i');
    }
}
