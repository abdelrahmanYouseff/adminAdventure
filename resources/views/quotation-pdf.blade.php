@php
    /** @var \App\Support\QuotationPdfData $data */
    /** @var float $scale */
    /** @var float $bottomMargin page bottom margin in mm, reserved for the footer */
    $scale = $scale ?? 1.0;
    $bottomMargin = $bottomMargin ?? 16;
    $pt = fn (float $size) => round($size * $scale, 2).'pt';

    // Height of the pinned acknowledgment box, in unscaled pt.
    $ackReservedHeight = $data->hasOnlinePaymentSection() ? 110 : 100;

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

{{-- Top header: logo --}}
<table width="100%" cellpadding="0" cellspacing="0" dir="ltr" style="margin-bottom: {{ $pt(4) }};">
    <tr>
        <td align="left" valign="top">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="{{ $data->logoAlt() }}" height="{{ round(108 * $scale) }}" style="max-width: {{ round(250 * $scale) }}px;">
            @endif
        </td>
    </tr>
</table>

<div class="quotation-title bi-ar" dir="rtl">{{ $data->biLabel('Quotation', 'عرض سعر') }}</div>

{{-- Company info + date / quotation no --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(7) }};">
    <tr>
        <td width="58%" valign="top" class="company-block" style="text-align: left;" align="left" dir="ltr">
            <div style="font-weight: bold; font-family: xbriyaz, dejavusans, sans-serif;" dir="rtl">{{ $data->companyLegalNameAr() }}</div>
            <div style="margin-top: {{ $pt(2) }};">
                <span dir="ltr">Al Muruj - Riyadh - Saudi Arabia</span>
                / <span style="font-family: xbriyaz, dejavusans, sans-serif;">حي المروج - الرياض - المملكة العربية السعودية</span>
            </div>
            <div style="margin-top: {{ $pt(2) }};" dir="ltr">{{ $data->companyPhone() }}</div>
            <div style="margin-top: {{ $pt(2) }};" dir="ltr">{{ $data->companyEmail() }}</div>
            <div style="margin-top: {{ $pt(2) }};" dir="ltr">{{ $data->companyWebsite() }}</div>
            <div style="margin-top: {{ $pt(2) }};">
                <span class="meta-label">CR. No. / <span style="font-family: xbriyaz, dejavusans, sans-serif;">سجل تجاري</span>:</span>
                {{ $data->commercialRegister() }}
            </div>
            <div style="margin-top: {{ $pt(2) }};">
                <span class="meta-label">VAT Number / <span style="font-family: xbriyaz, dejavusans, sans-serif;">الرقم الضريبي</span>:</span>
                {{ $data->vatNumber() }}
            </div>
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top" align="right" class="meta-block" dir="rtl">
            <div>
                <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">التاريخ</span>
                / Date:
                <span dir="ltr">{{ $data->issueDateLong() }}</span>
            </div>
            <div>
                <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">رقم العرض</span>
                / Quotation No:
                <span dir="ltr">{{ $data->quotationNumber() }}</span>
            </div>
            <div>
                <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">صالح حتى</span>
                / Valid Until:
                <span dir="ltr">{{ $data->validUntilLong() }}</span>
            </div>
        </td>
    </tr>
</table>

{{-- BILL TO + QUOTATION DETAILS --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(7) }};">
    <tr>
        <td width="48%" valign="top" class="section-box" style="text-align: right;" align="right" dir="rtl">
            <div style="{{ $sectionTitle }}" dir="ltr" align="right">
                Bill To / <span style="font-family: xbriyaz, dejavusans, sans-serif;">بيانات العميل</span>
            </div>
            <div style="font-weight: bold; font-size: {{ $pt(8.5) }};">{{ $data->customerName() }}</div>
            @if($data->customerAddress())
                <div style="margin-top: {{ $pt(2) }};" dir="ltr">{{ $data->customerAddress() }}</div>
            @endif
            <div style="margin-top: {{ $pt(2) }}; font-size: {{ $pt(7) }};">
                <div>
                    <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">الجوال</span>
                    / Contact No:
                    <span dir="ltr">{{ $data->customerPhone() }}</span>
                </div>
                <div style="margin-top: {{ $pt(2) }};">
                    <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">البريد</span>
                    / Email:
                    <span dir="ltr">{{ $data->customerEmail() }}</span>
                </div>
                @if($data->companyTaxNumber())
                    <div style="margin-top: {{ $pt(2) }};">
                        <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">الرقم الضريبي</span>
                        / Tax ID:
                        <span dir="ltr">{{ $data->companyTaxNumber() }}</span>
                    </div>
                @endif
            </div>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top" class="section-box" style="text-align: right;" align="right" dir="rtl">
            <div style="{{ $sectionTitle }}">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">تفاصيل العرض</span>
                / Quotation Details
            </div>
            <div>
                <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">أعدّه</span>
                / Prepared By:
                <span dir="ltr">{{ $data->preparedBy() }}</span>
            </div>
            @if($data->activityAt())
                <div style="margin-top: {{ $pt(2) }};">
                    <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">تاريخ الفعالية</span>
                    / Event Date:
                    <span dir="ltr">{{ $data->activityAt() }}</span>
                </div>
            @endif
            @if($data->installationAt())
                <div style="margin-top: {{ $pt(2) }};">
                    <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">تاريخ التركيب</span>
                    / Installation:
                    <span dir="ltr">{{ $data->installationAt() }}</span>
                </div>
            @endif
            @if($data->dismantlingAt())
                <div style="margin-top: {{ $pt(2) }};">
                    <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">تاريخ الفك</span>
                    / Dismantling:
                    <span dir="ltr">{{ $data->dismantlingAt() }}</span>
                </div>
            @endif
        </td>
    </tr>
</table>

{{-- Line items + totals (RTL: description on the right) --}}
<table class="items-table" dir="rtl" style="margin-bottom: {{ $pt(7) }}; direction: rtl;">
    <thead>
        <tr>
            <th width="34%" align="center">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">الوصف</span><br><span class="th-en">Description</span>
            </th>
            <th width="8%">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">الكمية</span><br><span class="th-en">Qty</span>
            </th>
            <th width="14%">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">السعر</span><br><span class="th-en">Price</span>
            </th>
            <th width="12%">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">الخصم / الوحدة</span><br><span class="th-en">Discount / Unit</span>
            </th>
            <th width="16%">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">السعر (شامل الضريبة)</span><br><span class="th-en">Price (Incl. VAT)</span>
            </th>
            <th width="16%">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">الإجمالي</span><br><span class="th-en">Total</span>
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($data->lineItemRows() as $item)
            <tr>
                <td align="right" dir="rtl" style="font-weight: bold;">
                    @if(!empty($item['name_en']) && !empty($item['name_ar']))
                        <span style="font-family: xbriyaz, dejavusans, sans-serif;">{{ $item['name_ar'] }}</span>
                        /
                        <span dir="ltr">{{ $item['name_en'] }}</span>
                    @elseif(!empty($item['name_ar']))
                        <span style="font-family: xbriyaz, dejavusans, sans-serif;">{{ $item['name_ar'] }}</span>
                    @else
                        <span dir="ltr">{{ $item['name_en'] ?? $item['name'] }}</span>
                    @endif
                </td>
                <td align="center">{{ $item['quantity'] }}</td>
                <td align="center">{{ $data->formatMoney($item['unit_price'], 2) }}</td>
                <td align="center">{{ $item['discount_amount'] > 0 ? $data->formatMoney($item['discount_amount'], 2) : '-' }}</td>
                <td align="center">{{ $data->formatMoney($item['unit_price_incl_vat'], 2) }} SAR</td>
                <td align="center">SAR {{ $data->formatMoney($item['total'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5" align="right" dir="ltr" style="font-weight: bold; background-color: #f5f5f5; text-align: right;">
                <span class="total-en">SUBTOTAL / </span>
                <span class="total-label" style="font-family: xbriyaz, dejavusans, sans-serif;">الإجمالي الفرعي</span>
            </td>
            <td align="center" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->grossSubtotal(), 2) }}</td>
        </tr>
        <tr>
            <td colspan="5" align="right" dir="ltr" style="font-weight: bold; background-color: #f5f5f5; text-align: right;">
                <span class="total-en">DISCOUNT / </span>
                <span class="total-label" style="font-family: xbriyaz, dejavusans, sans-serif;">الخصم</span>
            </td>
            <td align="center" style="font-weight: bold; background-color: #f5f5f5;">
                {{ $data->discountTotal() > 0 ? '- '.$data->formatSar($data->discountTotal(), 2) : 'SAR -' }}
            </td>
        </tr>
        <tr>
            <td colspan="5" align="right" dir="ltr" style="font-weight: bold; background-color: #f5f5f5; text-align: right;">
                <span class="total-en">SUBTOTAL BEFORE VAT / </span>
                <span class="total-label" style="font-family: xbriyaz, dejavusans, sans-serif;">الإجمالي قبل الضريبة</span>
            </td>
            <td align="center" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->subtotal(), 2) }}</td>
        </tr>
        <tr>
            <td colspan="5" align="right" dir="ltr" style="font-weight: bold; background-color: #f5f5f5; text-align: right;">
                <span class="total-en">VAT / </span>
                <span class="total-label" style="font-family: xbriyaz, dejavusans, sans-serif;">ضريبة القيمة المضافة</span>
            </td>
            <td align="center" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->vatAmount(), 2) }}</td>
        </tr>
        @if($data->hasInsurance())
            <tr>
                <td colspan="5" align="right" dir="ltr" style="font-weight: bold; background-color: #f5f5f5; text-align: right;">
                    <span class="total-en">INSURANCE DEPOSIT / </span>
                    <span class="total-label" style="font-family: xbriyaz, dejavusans, sans-serif;">مبلغ التأمين</span>
                </td>
                <td align="center" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->insuranceAmount(), 2) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="5" align="right" dir="ltr" style="font-weight: bold; background-color: #333; color: #fff; text-align: right;">
                <span class="total-en" style="color: #ddd;">TOTAL / </span>
                <span class="total-label" style="font-family: xbriyaz, dejavusans, sans-serif; color: #fff;">الإجمالي النهائي</span>
            </td>
            <td align="center" style="font-weight: bold; background-color: #333; color: #fff;">{{ $data->formatSar($data->total(), 2) }}</td>
        </tr>
        @if($data->amountPaid() > 0)
            <tr>
                <td colspan="5" align="right" dir="ltr" style="font-weight: bold; background-color: #f5f5f5; text-align: right;">
                    <span class="total-en">AMOUNT PAID / </span>
                    <span class="total-label" style="font-family: xbriyaz, dejavusans, sans-serif;">المبلغ المدفوع</span>
                </td>
                <td align="center" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->amountPaid(), 2) }}</td>
            </tr>
        @endif
        @if($data->hasAmountDue())
            <tr>
                <td colspan="5" align="right" dir="ltr" style="font-weight: bold; background-color: #fff7ed; text-align: right;">
                    <span class="total-en">AMOUNT DUE / </span>
                    <span class="total-label" style="font-family: xbriyaz, dejavusans, sans-serif;">المبلغ المستحق</span>
                </td>
                <td align="center" style="font-weight: bold; background-color: #fff7ed;">{{ $data->formatSar($data->amountDue(), 2) }}</td>
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
    <table class="terms-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="term-no">#</th>
                <th class="term-en" align="left">Terms &amp; Conditions</th>
                <th class="term-ar" dir="rtl" align="right" style="font-family: xbriyaz, dejavusans, sans-serif;">
                    الشروط والأحكام
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
<div style="margin-bottom: {{ $pt(5) }}; text-align: right;" align="right" dir="rtl">
    <div style="{{ $sectionTitle }}">
        <span style="font-family: xbriyaz, dejavusans, sans-serif;">بيانات التحويل البنكي</span>
        / Bank Transfer Details
    </div>
    <div style="font-size: {{ $pt(7.5) }}; line-height: 1.45;">
        <div>
            <strong style="font-family: xbriyaz, dejavusans, sans-serif;">بنك الرياض</strong>
            / Riyad Bank
        </div>
        <div>
            <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">آيبان</span>
            / IBAN:
            <span dir="ltr">{{ $data->bankIban() }}</span>
        </div>
        <div>
            <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">رقم الحساب</span>
            / Account Number:
            <span dir="ltr">{{ $data->bankAccountNumber() }}</span>
        </div>
        <div>
            <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">اسم الحساب</span>
            / Account Name:
            <span style="font-family: xbriyaz, dejavusans, sans-serif;">{{ $data->companyLegalNameAr() }}</span>
            /
            <span dir="ltr">{{ $data->companyLegalNameEn() }}</span>
        </div>
    </div>

    @if($data->hasOnlinePaymentSection())
        <div style="{{ $sectionTitle }} margin-top: {{ $pt(7) }}; text-align: right;" align="right" dir="rtl">
            <span style="font-family: xbriyaz, dejavusans, sans-serif;">الدفع الإلكتروني</span>
            / Online Payment
        </div>
        <div style="font-size: {{ $pt(7.5) }}; line-height: 1.45; padding: {{ $pt(5) }}; border: 1px solid #333; background-color: #f8fafc;">
            <div dir="rtl" align="right" style="text-align: right;">
                <span class="meta-label" style="font-family: xbriyaz, dejavusans, sans-serif;">ادفع المبلغ المستحق إلكترونياً</span>
                / Pay the due amount online:
                <span dir="ltr">{{ $data->formatSar($data->amountDue(), 2) }}</span>
            </div>
            {{-- Keep the <a> LTR/ASCII-only: mixed Arabic inside anchors breaks mPDF link hit-boxes. --}}
            <div dir="ltr" align="left" style="margin-top: {{ $pt(4) }}; text-align: left;">
                <a href="{{ $data->paymentUrl() }}" style="color: #1d4ed8; font-weight: bold; text-decoration: underline;">
                    Payment Link
                </a>
            </div>
        </div>
    @endif
</div>

{{-- Reserves the flow space taken by the acknowledgment box, which is pinned
     to the bottom of the page and therefore no longer part of the flow. --}}
<div style="height: {{ $pt($ackReservedHeight) }};"></div>

{{-- Client Acknowledgment --}}
<div class="ack-box" dir="rtl" align="right" style="text-align: right;">
    <div style="font-weight: bold; font-size: {{ $pt(8.5) }}; margin-bottom: {{ $pt(4) }}; text-align: right;" dir="ltr" align="right">
        Client Acknowledgment /
        <span style="font-family: xbriyaz, dejavusans, sans-serif;">إقرار العميل</span>
    </div>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: {{ $pt(7.5) }};" dir="rtl">
        <tr>
            <td valign="bottom" align="right" style="text-align: right;">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">اسم الشركة / العميل</span>
                / Company / Client Name:
                <div class="ack-line"></div>
            </td>
        </tr>
        <tr>
            <td valign="bottom" align="right" style="text-align: right; padding-top: {{ $pt(6) }};">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">وسيلة التواصل</span>
                / Contact Method:
                <div class="ack-line"></div>
            </td>
        </tr>
        <tr>
            <td valign="bottom" align="right" style="text-align: right; padding-top: {{ $pt(6) }};">
                <span style="font-family: xbriyaz, dejavusans, sans-serif;">التوقيع</span>
                / Signature:
                <div class="ack-line" style="width: 60%; margin-right: 0; margin-left: auto;"></div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
