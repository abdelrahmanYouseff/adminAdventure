@php
    /** @var \App\Support\QuotationPdfData $data */
    /** @var float $scale */
    /** @var float $bottomMargin page bottom margin in mm, reserved for the footer */
    $scale = $scale ?? 1.0;
    $bottomMargin = $bottomMargin ?? 16;
    $pt = fn (float $size) => round($size * $scale, 2).'pt';

    $ackReservedHeight = 100;

    $border = 'border: 1px solid #333;';
    $sectionTitle = 'font-size: '.$pt(8).'; font-weight: bold; color: #1a1a1a; margin: 0 0 '.$pt(4).' 0;';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>عرض سعر {{ $data->quotationNumber() }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: {{ $pt(7.5) }};
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            line-height: 1.45;
            direction: rtl;
            text-align: right;
        }
        .company-name {
            font-size: {{ $pt(11) }};
            font-weight: bold;
        }
        .quotation-title {
            font-size: {{ $pt(15) }};
            font-weight: bold;
            text-align: center;
            margin: {{ $pt(6) }} 0 {{ $pt(8) }} 0;
        }
        .meta-label {
            font-weight: bold;
            color: #333;
        }
        .items-table {
            border-collapse: collapse;
            width: 100%;
            font-size: {{ $pt(6.5) }};
            direction: rtl;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #333;
            padding: {{ $pt(3.5) }} {{ $pt(4) }};
            line-height: 1.35;
        }
        .items-table th {
            background-color: #e8e8e8;
            font-weight: bold;
            text-align: center;
        }
        .terms-list {
            margin: 0;
            padding-right: {{ $pt(14) }};
            padding-left: 0;
            font-size: {{ $pt(7) }};
            line-height: 1.45;
            list-style-position: outside;
        }
        .terms-list li {
            margin-bottom: {{ $pt(3) }};
            text-align: right;
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
            direction: rtl;
            text-align: right;
        }
        .ack-line {
            border-bottom: 1px solid #999;
            height: {{ $pt(13) }};
            margin-top: {{ $pt(4) }};
        }
        .section-box {
            {{ $border }}
            padding: {{ $pt(6) }} {{ $pt(9) }};
            text-align: right;
        }
        .company-block,
        .meta-block {
            font-size: {{ $pt(7.5) }};
            line-height: 1.45;
            text-align: right;
        }
        .ltr {
            direction: ltr;
            unicode-bidi: embed;
            text-align: left;
        }
    </style>
</head>
<body>

<table width="100%" cellpadding="0" cellspacing="0" dir="ltr" style="margin-bottom: {{ $pt(4) }};">
    <tr>
        <td align="left" valign="top">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="{{ $data->logoAlt() }}" height="{{ round(108 * $scale) }}" style="max-width: {{ round(250 * $scale) }}px;">
            @endif
        </td>
    </tr>
</table>

<div class="quotation-title">عرض سعر</div>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(7) }};">
    <tr>
        <td width="58%" valign="top" class="company-block">
            <div style="font-weight: bold;">{{ $data->companyLegalNameAr() }}</div>
            <div>{{ $data->companyAddress() }}</div>
            <div>هاتف: <span class="ltr">{{ $data->companyPhone() }}</span> &nbsp;|&nbsp; البريد: <span class="ltr">{{ $data->companyEmail() }}</span></div>
            <div>الموقع: <span class="ltr">{{ $data->companyWebsite() }}</span></div>
            <div>سجل تجاري: <span class="ltr">{{ $data->commercialRegister() }}</span></div>
            <div>الرقم الضريبي: <span class="ltr">{{ $data->vatNumber() }}</span></div>
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top" class="meta-block">
            <div><span class="meta-label">التاريخ:</span> <span class="ltr">{{ $data->issueDateLong() }}</span></div>
            <div><span class="meta-label">رقم العرض:</span> <span class="ltr">{{ $data->quotationNumber() }}</span></div>
            <div><span class="meta-label">صالح حتى:</span> <span class="ltr">{{ $data->validUntilLong() }}</span></div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(7) }};">
    <tr>
        <td width="48%" valign="top" class="section-box">
            <div style="{{ $sectionTitle }}">بيانات العميل</div>
            <div style="font-weight: bold; font-size: {{ $pt(8.5) }};">{{ $data->customerName() }}</div>
            @if($data->customerAddress())
                <div style="margin-top: {{ $pt(2) }};">{{ $data->customerAddress() }}</div>
            @endif
            @if($data->companyTaxNumber())
                <div style="margin-top: {{ $pt(2) }};"><span class="meta-label">الرقم الضريبي:</span> <span class="ltr">{{ $data->companyTaxNumber() }}</span></div>
            @endif
            <div style="margin-top: {{ $pt(2) }}; font-size: {{ $pt(7) }};">
                البريد / الجوال:
                <span class="ltr">{{ $data->customerEmail() }} / {{ $data->customerPhone() }}</span>
            </div>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top" class="section-box">
            <div style="{{ $sectionTitle }}">تفاصيل العرض</div>
            <div><span class="meta-label">أعدّه:</span> {{ $data->preparedBy() }}</div>
            @if($data->activityAt())
                <div style="margin-top: {{ $pt(2) }};"><span class="meta-label">تاريخ الفعالية:</span> <span class="ltr">{{ $data->activityAt() }}</span></div>
            @endif
            @if($data->installationAt())
                <div style="margin-top: {{ $pt(2) }};"><span class="meta-label">تاريخ التركيب:</span> <span class="ltr">{{ $data->installationAt() }}</span></div>
            @endif
            @if($data->dismantlingAt())
                <div style="margin-top: {{ $pt(2) }};"><span class="meta-label">تاريخ الفك:</span> <span class="ltr">{{ $data->dismantlingAt() }}</span></div>
            @endif
        </td>
    </tr>
</table>

<table class="items-table" style="margin-bottom: {{ $pt(7) }};">
    <thead>
        <tr>
            <th width="34%">الوصف</th>
            <th width="8%">الكمية</th>
            <th width="14%">السعر</th>
            <th width="12%">الخصم<br>/ الوحدة</th>
            <th width="16%">السعر<br>(شامل الضريبة)</th>
            <th width="16%">الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data->lineItemRows() as $item)
            <tr>
                <td align="right">
                    <strong>{{ $item['name'] }}</strong>
                </td>
                <td align="center"><span class="ltr">{{ $item['quantity'] }}</span></td>
                <td align="center"><span class="ltr">{{ $data->formatMoney($item['unit_price'], 2) }}</span></td>
                <td align="center"><span class="ltr">{{ $item['discount_amount'] > 0 ? $data->formatMoney($item['discount_amount'], 2) : '-' }}</span></td>
                <td align="center"><span class="ltr">{{ $data->formatMoney($item['unit_price_incl_vat'], 2) }} ر.س</span></td>
                <td align="center"><span class="ltr">{{ $data->formatMoney($item['total'], 2) }} ر.س</span></td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5" align="right" style="font-weight: bold; background-color: #f5f5f5;">الإجمالي الفرعي</td>
            <td align="center" style="font-weight: bold; background-color: #f5f5f5;"><span class="ltr">{{ $data->formatSar($data->grossSubtotal(), 2) }}</span></td>
        </tr>
        <tr>
            <td colspan="5" align="right" style="font-weight: bold; background-color: #f5f5f5;">الخصم</td>
            <td align="center" style="font-weight: bold; background-color: #f5f5f5;">
                <span class="ltr">{{ $data->discountTotal() > 0 ? '- '.$data->formatSar($data->discountTotal(), 2) : '— ر.س' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="5" align="right" style="font-weight: bold; background-color: #f5f5f5;">الإجمالي قبل الضريبة</td>
            <td align="center" style="font-weight: bold; background-color: #f5f5f5;"><span class="ltr">{{ $data->formatSar($data->subtotal(), 2) }}</span></td>
        </tr>
        <tr>
            <td colspan="5" align="right" style="font-weight: bold; background-color: #f5f5f5;">ضريبة القيمة المضافة</td>
            <td align="center" style="font-weight: bold; background-color: #f5f5f5;"><span class="ltr">{{ $data->formatSar($data->vatAmount(), 2) }}</span></td>
        </tr>
        @if($data->hasInsurance())
            <tr>
                <td colspan="5" align="right" style="font-weight: bold; background-color: #f5f5f5;">مبلغ التأمين</td>
                <td align="center" style="font-weight: bold; background-color: #f5f5f5;"><span class="ltr">{{ $data->formatSar($data->insuranceAmount(), 2) }}</span></td>
            </tr>
        @endif
        <tr>
            <td colspan="5" align="right" style="font-weight: bold; background-color: #333; color: #fff;">الإجمالي النهائي</td>
            <td align="center" style="font-weight: bold; background-color: #333; color: #fff;"><span class="ltr">{{ $data->formatSar($data->total(), 2) }}</span></td>
        </tr>
        @if($data->amountPaid() > 0)
            <tr>
                <td colspan="5" align="right" style="font-weight: bold; background-color: #f5f5f5;">المبلغ المدفوع</td>
                <td align="center" style="font-weight: bold; background-color: #f5f5f5;"><span class="ltr">{{ $data->formatSar($data->amountPaid(), 2) }}</span></td>
            </tr>
        @endif
        @if($data->hasAmountDue())
            <tr>
                <td colspan="5" align="right" style="font-weight: bold; background-color: #fff7ed;">المبلغ المستحق</td>
                <td align="center" style="font-weight: bold; background-color: #fff7ed;"><span class="ltr">{{ $data->formatSar($data->amountDue(), 2) }}</span></td>
            </tr>
        @endif
    </tbody>
</table>

@if($data->hasInsurance())
    <div style="margin: -{{ $pt(4) }} 0 {{ $pt(6) }} 0; font-size: {{ $pt(6.5) }}; color: #444; line-height: 1.4; text-align: right;">
        {{ $data->insuranceNoteAr() }}
    </div>
@endif

<div style="margin-bottom: {{ $pt(6) }};">
    <div style="{{ $sectionTitle }}">الشروط والأحكام</div>
    <ol class="terms-list">
        @foreach($data->termsAndConditions() as $term)
            <li>{{ $term }}</li>
        @endforeach
    </ol>
</div>

<div style="margin-bottom: {{ $pt(5) }};">
    <div style="{{ $sectionTitle }}">بيانات التحويل البنكي</div>
    <div style="font-size: {{ $pt(7.5) }}; line-height: 1.5; text-align: right;">
        <strong>{{ $data->bankName() }}</strong><br>
        الآيبان: <span class="ltr">{{ $data->bankIban() }}</span><br>
        رقم الحساب: <span class="ltr">{{ $data->bankAccountNumber() }}</span><br>
        اسم الحساب: {{ $data->bankAccountName() }}
    </div>

    @if($data->hasOnlinePaymentSection())
        <div style="{{ $sectionTitle }} margin-top: {{ $pt(7) }};">الدفع الإلكتروني</div>
        <div style="font-size: {{ $pt(7.5) }}; line-height: 1.45; padding: {{ $pt(5) }}; border: 1px solid #333; background-color: #f8fafc; text-align: right;">
            <strong>ادفع المبلغ المستحق إلكترونيًا:</strong>
            <span class="ltr">{{ $data->formatSar($data->amountDue(), 2) }}</span>
            <br>
            <a href="{{ $data->paymentUrl() }}" style="color: #1d4ed8; font-weight: bold;">
                رابط الدفع
            </a>
        </div>
    @endif
</div>

<div style="height: {{ $pt($ackReservedHeight) }};"></div>

<div class="ack-box">
    <div style="font-weight: bold; font-size: {{ $pt(8.5) }}; margin-bottom: {{ $pt(4) }};">إقرار العميل</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: {{ $pt(7.5) }};">
        <tr>
            <td width="50%" valign="bottom">
                اسم الشركة / العميل:
                <div class="ack-line"></div>
            </td>
            <td width="50%" valign="bottom" style="padding-right: {{ $pt(10) }};">
                وسيلة التواصل:
                <div class="ack-line"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" valign="bottom" style="padding-top: {{ $pt(6) }};">
                التوقيع:
                <div class="ack-line" style="width: 60%;"></div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
