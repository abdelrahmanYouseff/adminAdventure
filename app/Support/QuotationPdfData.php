<?php

namespace App\Support;

use App\Models\Brand;
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
        $quotation->load(['user', 'items.product', 'brand']);

        return new self($quotation, in_array($locale, ['ar', 'en'], true) ? $locale : 'en');
    }

    public function isArabic(): bool
    {
        return $this->locale === 'ar';
    }

    public function isBilingual(): bool
    {
        return ! $this->isArabic();
    }

    /**
     * English PDF: "Arabic / English". Arabic PDF: Arabic only.
     */
    public function biLabel(string $en, string $ar): string
    {
        return $this->isArabic() ? $ar : $ar.' / '.$en;
    }

    /**
     * @return array{en: string, ar: string}
     */
    public function biPair(string $en, string $ar): array
    {
        return ['en' => $en, 'ar' => $ar];
    }

    /**
     * @return list<array{en: string, ar: string}>
     */
    public function bilingualTerms(): array
    {
        $pairs = $this->quotation->terms === null
            ? QuotationDefaultTerms::all()
            : QuotationDefaultTerms::sanitize($this->quotation->terms);

        if ($this->notes()) {
            $pairs[] = ['ar' => $this->notes(), 'en' => $this->notes()];
        }

        return $pairs;
    }

    public function companyAddressBilingual(): string
    {
        $ar = 'حي المروج - الرياض - المملكة العربية السعودية';
        $en = 'Al Muruj - Riyadh - Saudi Arabia';

        return $this->isArabic() ? $ar : $en.' / '.$ar;
    }

    public function bankNameBilingual(): string
    {
        return $this->isArabic() ? 'بنك الرياض' : 'بنك الرياض / Riyad Bank';
    }

    public function bankAccountNameBilingual(): string
    {
        return $this->isArabic()
            ? $this->companyLegalNameAr()
            : $this->companyLegalNameAr().' / '.$this->companyLegalNameEn();
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

        return \App\Support\MediaStorage::temporaryLocalPath($logo);
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
            $names = $this->bilingualProductName((string) $item->product_name);

            return [
                'name' => $item->product_name,
                'name_en' => $names['en'],
                'name_ar' => $names['ar'],
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

    /**
     * @return array{en: ?string, ar: ?string}
     */
    public function bilingualProductName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['en' => null, 'ar' => null];
        }

        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $name);
        $hasLatin = (bool) preg_match('/[A-Za-z]/', $name);

        if ($hasArabic && $hasLatin) {
            $parts = preg_split('/\s*[-|–—]\s*/u', $name) ?: [$name];
            $arParts = [];
            $enParts = [];

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $partHasArabic = (bool) preg_match('/\p{Arabic}/u', $part);
                $partHasLatin = (bool) preg_match('/[A-Za-z]{2,}/', $part);

                if ($partHasArabic && $partHasLatin && preg_match('/^(.*?\p{Arabic}.*?)\s+([A-Za-z].+)$/u', $part, $matches)) {
                    $arParts[] = trim($matches[1]);
                    $enParts[] = trim($matches[2]);
                } elseif ($partHasArabic && ! $partHasLatin) {
                    $arParts[] = $part;
                } elseif ($partHasLatin && ! $partHasArabic) {
                    $enParts[] = $part;
                } elseif ($partHasArabic) {
                    $arParts[] = $part;
                } else {
                    $enParts[] = $part;
                }
            }

            return [
                'en' => $enParts !== [] ? implode(' - ', $enParts) : null,
                'ar' => $arParts !== [] ? implode(' - ', $arParts) : null,
            ];
        }

        if ($hasArabic) {
            return ['en' => null, 'ar' => $name];
        }

        return [
            'en' => $name,
            'ar' => $this->englishProductTranslation($name),
        ];
    }

    private function englishProductTranslation(string $englishName): ?string
    {
        $map = [
            'Adventure Backpack' => 'حقيبة ظهر للمغامرات',
            'Camping Tent' => 'خيمة تخييم',
            'Hiking Boots' => 'أحذية المشي',
            'Sleeping Bag' => 'كيس نوم',
            'Flamingo Summer' => 'فلامنجو الصيف',
            'plate one' => 'بلات ون',
            'Ice Cream Roll' => 'آيس كريم رول',
            'VIP Cart' => 'عربة الـ VIP',
            'Photo booth' => 'فوتو بوث',
        ];

        if (isset($map[$englishName])) {
            return $map[$englishName];
        }

        foreach ($map as $en => $ar) {
            if (strcasecmp($en, $englishName) === 0) {
                return $ar;
            }
        }

        return null;
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

    public function hasOnlinePaymentSection(): bool
    {
        return (bool) $this->quotation->show_online_payment
            && $this->hasAmountDue()
            && filled($this->paymentUrl());
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
        return $this->companyAddressBilingual();
    }

    public function companyPhone(): string
    {
        $brand = $this->quotation->relationLoaded('brand')
            ? $this->quotation->brand
            : $this->quotation->brand()->first();

        return ($brand ?? Brand::default())->contactPhone();
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
        return $this->bankNameBilingual();
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
        return $this->bankAccountNameBilingual();
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
        if ($this->isArabic()) {
            return array_map(fn (array $pair) => $pair['ar'], $this->bilingualTerms());
        }

        return array_map(fn (array $pair) => $pair['en'], $this->bilingualTerms());
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
