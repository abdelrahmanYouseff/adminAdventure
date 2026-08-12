@php
    /** @var \App\Support\QuotationPdfData $data */
    /** @var float $scale */
    /** @var float $bottomMargin page bottom margin in mm, reserved for the footer */
    $scale = $scale ?? 1.0;
    $bottomMargin = $bottomMargin ?? 16;
    $pt = fn (float $size) => round($size * $scale, 2).'pt';

    // Height of the pinned acknowledgment box, in unscaled pt.
    $ackReservedHeight = 100;

    $border = 'border: 1px solid #333;';
    $sectionTitle = 'font-size: '.$pt(7.5).'; font-weight: bold; color: #1a1a1a; margin: 0 0 '.$pt(4).' 0; letter-spacing: 0.2px; line-height: 1.35;';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>{{ $data->biLabel('Quotation', 'عرض سعر') }} {{ $data->quotationNumber() }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: {{ $pt(7.5) }};
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }
        .company-name {
            font-size: {{ $pt(11) }};
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .quotation-title {
            font-size: {{ $pt(14) }};
            font-weight: bold;
            text-align: center;
            margin: {{ $pt(6) }} 0 {{ $pt(8) }} 0;
            letter-spacing: 0.4px;
            line-height: 1.35;
        }
        .meta-label {
            font-weight: bold;
            color: #333;
        }
        .bi-ar {
            font-family: xbriyaz, dejavusans, sans-serif;
            font-weight: normal;
        }
        .items-table {
            border-collapse: collapse;
            width: 100%;
            font-size: {{ $pt(6.2) }};
        }
        .items-table th,
        .items-table td {
            border: 1px solid #333;
            padding: {{ $pt(3) }} {{ $pt(3.5) }};
            line-height: 1.25;
        }
        .items-table th {
            background-color: #e8e8e8;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }
        .th-en {
            font-size: {{ $pt(5.8) }};
            color: #444;
            font-weight: normal;
        }
        .terms-table {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ $pt(6.5) }};
            line-height: 1.4;
            table-layout: fixed;
        }
        .terms-table th,
        .terms-table td {
            border: 1px solid #333;
            vertical-align: top;
            padding: {{ $pt(4) }} {{ $pt(5) }};
        }
        .terms-table th {
            background-color: #e8e8e8;
            font-weight: bold;
            text-align: center;
        }
        .terms-table .term-no {
            width: 5%;
            text-align: center;
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .terms-table .term-en {
            width: 47.5%;
            color: #333;
            text-align: left;
        }
        .terms-table .term-ar {
            width: 47.5%;
            font-family: xbriyaz, dejavusans, sans-serif;
            direction: rtl;
            text-align: right;
        }
        .terms-head-en {
            font-size: {{ $pt(6.2) }};
            color: #444;
            font-weight: normal;
        }
        .ack-box {
            position: absolute;
            bottom: {{ $bottomMargin }}mm;
            left: 0;
            right: 0;
            border: 1px solid #333;
            padding: {{ $pt(6) }} {{ $pt(9) }};
            font-size: {{ $pt(7.5) }};
            background-color: #fff;
        }
        .ack-line {
            border-bottom: 1px solid #999;
            height: {{ $pt(13) }};
            margin-top: {{ $pt(4) }};
        }
        .section-box {
            {{ $border }}
            padding: {{ $pt(6) }} {{ $pt(9) }};
        }
        .company-block {
            font-size: {{ $pt(7.5) }};
            line-height: 1.4;
        }
        .meta-block {
            font-size: {{ $pt(7.5) }};
            line-height: 1.45;
        }
        .total-label {
            font-size: {{ $pt(6.2) }};
            line-height: 1.3;
        }
        .total-en {
            font-size: {{ $pt(5.8) }};
            color: #555;
            font-weight: normal;
        }
    </style>
</head>
<body>

{{-- Top header: company + logo --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(4) }};">
    <tr>
        <td width="65%" valign="top">
            <div class="company-name">{{ $data->companyLegalNameEn() }}</div>
            <div style="font-size: {{ $pt(7) }}; margin-top: {{ $pt(2) }};">
                <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif; font-weight: bold;">{{ $data->biLabel('CR. No.', 'سجل تجاري') }}:</span>
                {{ $data->commercialRegister() }}
            </div>
        </td>
        <td width="35%" align="right" valign="top">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="{{ $data->logoAlt() }}" height="{{ round(78 * $scale) }}" style="max-width: {{ round(180 * $scale) }}px;">
            @endif
        </td>
    </tr>
</table>

<div class="quotation-title bi-ar" dir="rtl">{{ $data->biLabel('Quotation', 'عرض سعر') }}</div>

{{-- Company info + date / quotation no --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(7) }};">
    <tr>
        <td width="58%" valign="top" class="company-block">
            <div class="bi-ar" dir="rtl" style="font-weight: bold;">{{ $data->companyLegalNameAr() }}</div>
            <div class="bi-ar" dir="rtl">{{ $data->companyAddress() }}</div>
            <div>
                <span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Tel', 'هاتف') }}:</span> {{ $data->companyPhone() }}
                &nbsp;|&nbsp;
                <span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Email', 'البريد') }}:</span> {{ $data->companyEmail() }}
            </div>
            <div>
                <span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Website', 'الموقع') }}:</span> {{ $data->companyWebsite() }}
                &nbsp;|&nbsp;
                <span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('VAT Number', 'الرقم الضريبي') }}:</span> {{ $data->vatNumber() }}
            </div>
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top" align="right" class="meta-block">
            <div><span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Date', 'التاريخ') }}:</span> {{ $data->issueDateLong() }}</div>
            <div><span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Quotation No', 'رقم العرض') }}:</span> {{ $data->quotationNumber() }}</div>
            <div><span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Valid Until', 'صالح حتى') }}:</span> {{ $data->validUntilLong() }}</div>
        </td>
    </tr>
</table>

{{-- BILL TO + QUOTATION DETAILS --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(7) }};">
    <tr>
        <td width="48%" valign="top" class="section-box">
            <div style="{{ $sectionTitle }}" class="bi-ar" dir="rtl">{{ $data->biLabel('Bill To', 'بيانات العميل') }}</div>
            <div style="font-weight: bold; font-size: {{ $pt(8.5) }};">{{ $data->customerName() }}</div>
            @if($data->customerAddress())
                <div style="margin-top: {{ $pt(2) }};">{{ $data->customerAddress() }}</div>
            @endif
            @if($data->companyTaxNumber())
                <div style="margin-top: {{ $pt(2) }};">
                    <span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('VAT No', 'الرقم الضريبي') }}:</span>
                    {{ $data->companyTaxNumber() }}
                </div>
            @endif
            <div style="margin-top: {{ $pt(2) }}; font-size: {{ $pt(7) }};">
                <span class="bi-ar" dir="rtl">{{ $data->biLabel('Email / Contact No', 'البريد / الجوال') }}:</span>
                {{ $data->customerEmail() }} / {{ $data->customerPhone() }}
            </div>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top" class="section-box">
            <div style="{{ $sectionTitle }}" class="bi-ar" dir="rtl">{{ $data->biLabel('Quotation Details', 'تفاصيل العرض') }}</div>
            <div><span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Prepared By', 'أعدّه') }}:</span> {{ $data->preparedBy() }}</div>
            @if($data->activityAt())
                <div style="margin-top: {{ $pt(2) }};">
                    <span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Event Date', 'تاريخ الفعالية') }}:</span>
                    {{ $data->activityAt() }}
                </div>
            @endif
            @if($data->installationAt())
                <div style="margin-top: {{ $pt(2) }};">
                    <span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Installation', 'تاريخ التركيب') }}:</span>
                    {{ $data->installationAt() }}
                </div>
            @endif
            @if($data->dismantlingAt())
                <div style="margin-top: {{ $pt(2) }};">
                    <span class="meta-label bi-ar" dir="rtl">{{ $data->biLabel('Dismantling', 'تاريخ الفك') }}:</span>
                    {{ $data->dismantlingAt() }}
                </div>
            @endif
        </td>
    </tr>
</table>

{{-- Line items + totals --}}
<table class="items-table" style="margin-bottom: {{ $pt(7) }};">
    <thead>
        <tr>
            <th width="34%" align="center">
                <span class="bi-ar" dir="rtl">الوصف</span><br><span class="th-en">Description</span>
            </th>
            <th width="8%">
                <span class="bi-ar" dir="rtl">الكمية</span><br><span class="th-en">Qty</span>
            </th>
            <th width="14%">
                <span class="bi-ar" dir="rtl">السعر</span><br><span class="th-en">Price</span>
            </th>
            <th width="12%">
                <span class="bi-ar" dir="rtl">الخصم / الوحدة</span><br><span class="th-en">Discount / Unit</span>
            </th>
            <th width="16%">
                <span class="bi-ar" dir="rtl">السعر (شامل الضريبة)</span><br><span class="th-en">Price (Incl. VAT)</span>
            </th>
            <th width="16%">
                <span class="bi-ar" dir="rtl">الإجمالي</span><br><span class="th-en">Total</span>
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($data->lineItemRows() as $item)
            <tr>
                <td align="left">
                    <strong>{{ $item['name'] }}</strong>
                </td>
                <td align="center">{{ $item['quantity'] }}</td>
                <td align="right">{{ $data->formatMoney($item['unit_price'], 4) }}</td>
                <td align="right">{{ $item['discount_amount'] > 0 ? $data->formatMoney($item['discount_amount'], 2) : '-' }}</td>
                <td align="right">{{ $data->formatMoney($item['unit_price_incl_vat'], 4) }} SAR</td>
                <td align="right">SAR {{ $data->formatMoney($item['total'], 0) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5" align="left" style="font-weight: bold; background-color: #f5f5f5;">
                <span class="total-label bi-ar" dir="rtl">الإجمالي الفرعي</span>
                <span class="total-en"> / SUBTOTAL</span>
            </td>
            <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->grossSubtotal(), 0) }}</td>
        </tr>
        <tr>
            <td colspan="5" align="left" style="font-weight: bold; background-color: #f5f5f5;">
                <span class="total-label bi-ar" dir="rtl">الخصم</span>
                <span class="total-en"> / DISCOUNT</span>
            </td>
            <td align="left" style="font-weight: bold; background-color: #f5f5f5;">
                {{ $data->discountTotal() > 0 ? '- '.$data->formatSar($data->discountTotal(), 2) : 'SAR -' }}
            </td>
        </tr>
        <tr>
            <td colspan="5" align="left" style="font-weight: bold; background-color: #f5f5f5;">
                <span class="total-label bi-ar" dir="rtl">الإجمالي قبل الضريبة</span>
                <span class="total-en"> / SUBTOTAL BEFORE VAT</span>
            </td>
            <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->subtotal(), 0) }}</td>
        </tr>
        <tr>
            <td colspan="5" align="left" style="font-weight: bold; background-color: #f5f5f5;">
                <span class="total-label bi-ar" dir="rtl">ضريبة القيمة المضافة</span>
                <span class="total-en"> / VAT</span>
            </td>
            <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->vatAmount(), 0) }}</td>
        </tr>
        @if($data->hasInsurance())
            <tr>
                <td colspan="5" align="left" style="font-weight: bold; background-color: #f5f5f5;">
                    <span class="total-label bi-ar" dir="rtl">مبلغ التأمين</span>
                    <span class="total-en"> / INSURANCE DEPOSIT</span>
                </td>
                <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->insuranceAmount(), 2) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="5" align="left" style="font-weight: bold; background-color: #333; color: #fff;">
                <span class="total-label bi-ar" dir="rtl" style="color: #fff;">الإجمالي النهائي</span>
                <span class="total-en" style="color: #ddd;"> / TOTAL</span>
            </td>
            <td align="left" style="font-weight: bold; background-color: #333; color: #fff;">{{ $data->formatSar($data->total(), 2) }}</td>
        </tr>
        @if($data->amountPaid() > 0)
            <tr>
                <td colspan="5" align="left" style="font-weight: bold; background-color: #f5f5f5;">
                    <span class="total-label bi-ar" dir="rtl">المبلغ المدفوع</span>
                    <span class="total-en"> / AMOUNT PAID</span>
                </td>
                <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->amountPaid(), 2) }}</td>
            </tr>
        @endif
        @if($data->hasAmountDue())
            <tr>
                <td colspan="5" align="left" style="font-weight: bold; background-color: #fff7ed;">
                    <span class="total-label bi-ar" dir="rtl">المبلغ المستحق</span>
                    <span class="total-en"> / AMOUNT DUE</span>
                </td>
                <td align="left" style="font-weight: bold; background-color: #fff7ed;">{{ $data->formatSar($data->amountDue(), 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>

@if($data->hasInsurance())
    <div style="margin: -{{ $pt(4) }} 0 {{ $pt(6) }} 0; font-size: {{ $pt(6.5) }}; color: #444; line-height: 1.35;">
        <div class="bi-ar" dir="rtl">{{ $data->insuranceNoteAr() }}</div>
        <div style="margin-top: {{ $pt(1.5) }};">{{ $data->insuranceNoteEn() }}</div>
    </div>
@endif

{{-- Terms --}}
<div style="margin-bottom: {{ $pt(6) }};">
    <div style="{{ $sectionTitle }}" class="bi-ar" dir="rtl">{{ $data->biLabel('Terms & Conditions', 'الشروط والأحكام') }}</div>
    <table class="terms-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="term-no">#</th>
                <th class="term-en" align="left">English</th>
                <th class="term-ar" dir="rtl" align="right">
                    العربية<br><span class="terms-head-en">Arabic</span>
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->bilingualTerms() as $index => $term)
                <tr>
                    <td class="term-no">{{ $index + 1 }}</td>
                    <td class="term-en">{{ $term['en'] }}</td>
                    <td class="term-ar" dir="rtl">{{ $term['ar'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Bank + Payment --}}
<div style="margin-bottom: {{ $pt(5) }};">
    <div style="{{ $sectionTitle }}" class="bi-ar" dir="rtl">{{ $data->biLabel('Bank Transfer Details', 'بيانات التحويل البنكي') }}</div>
    <div style="font-size: {{ $pt(7.5) }}; line-height: 1.45;">
        <strong class="bi-ar" dir="rtl">{{ $data->bankName() }}</strong><br>
        <span class="bi-ar" dir="rtl">{{ $data->biLabel('IBAN', 'آيبان') }}:</span> {{ $data->bankIban() }}<br>
        <span class="bi-ar" dir="rtl">{{ $data->biLabel('Account Number', 'رقم الحساب') }}:</span> {{ $data->bankAccountNumber() }}<br>
        <span class="bi-ar" dir="rtl">{{ $data->biLabel('Account Name', 'اسم الحساب') }}:</span> {{ $data->bankAccountName() }}
    </div>

    @if($data->hasAmountDue() && $data->paymentUrl())
        <div style="{{ $sectionTitle }} margin-top: {{ $pt(7) }};" class="bi-ar" dir="rtl">{{ $data->biLabel('Online Payment', 'الدفع الإلكتروني') }}</div>
        <div style="font-size: {{ $pt(7.5) }}; line-height: 1.45; padding: {{ $pt(5) }}; border: 1px solid #333; background-color: #f8fafc;">
            <span class="bi-ar" dir="rtl">{{ $data->biLabel('Pay the due amount online', 'ادفع المبلغ المستحق إلكترونياً') }}:</span>
            {{ $data->formatSar($data->amountDue(), 2) }}
            <br>
            <a href="{{ $data->paymentUrl() }}" style="color: #1d4ed8; font-weight: bold;">
                {{ $data->biLabel('Payment Link', 'رابط الدفع') }}
            </a>
        </div>
    @endif
</div>

{{-- Reserves the flow space taken by the acknowledgment box, which is pinned
     to the bottom of the page and therefore no longer part of the flow. --}}
<div style="height: {{ $pt($ackReservedHeight) }};"></div>

{{-- Client Acknowledgment --}}
<div class="ack-box">
    <div style="font-weight: bold; font-size: {{ $pt(8.5) }}; margin-bottom: {{ $pt(4) }};" class="bi-ar" dir="rtl">
        {{ $data->biLabel('Client Acknowledgment', 'إقرار العميل') }}
    </div>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: {{ $pt(7.5) }};">
        <tr>
            <td width="50%" valign="bottom">
                <span class="bi-ar" dir="rtl">{{ $data->biLabel('Company / Client Name', 'اسم الشركة / العميل') }}:</span>
                <div class="ack-line"></div>
            </td>
            <td width="50%" valign="bottom" style="padding-left: {{ $pt(10) }};">
                <span class="bi-ar" dir="rtl">{{ $data->biLabel('Contact Method', 'وسيلة التواصل') }}:</span>
                <div class="ack-line"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" valign="bottom" style="padding-top: {{ $pt(6) }};">
                <span class="bi-ar" dir="rtl">{{ $data->biLabel('Signature', 'التوقيع') }}:</span>
                <div class="ack-line" style="width: 60%;"></div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
