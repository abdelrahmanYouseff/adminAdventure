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
    $sectionTitle = 'font-size: '.$pt(8).'; font-weight: bold; color: #1a1a1a; margin: 0 0 '.$pt(4).' 0; text-transform: uppercase; letter-spacing: 0.3px;';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Quotation {{ $data->quotationNumber() }}</title>
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
            font-size: {{ $pt(15) }};
            font-weight: bold;
            text-align: center;
            margin: {{ $pt(6) }} 0 {{ $pt(8) }} 0;
            letter-spacing: 0.8px;
        }
        .meta-label {
            font-weight: bold;
            color: #333;
        }
        .items-table {
            border-collapse: collapse;
            width: 100%;
            font-size: {{ $pt(6.5) }};
        }
        .items-table th,
        .items-table td {
            border: 1px solid #333;
            padding: {{ $pt(3.5) }} {{ $pt(4) }};
            line-height: 1.25;
        }
        .items-table th {
            background-color: #e8e8e8;
            font-weight: bold;
            text-align: center;
        }
        .terms-list {
            margin: 0;
            padding-left: {{ $pt(10) }};
            font-size: {{ $pt(7) }};
            line-height: 1.35;
        }
        .terms-list li {
            margin-bottom: {{ $pt(2.5) }};
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
    </style>
</head>
<body>

{{-- Top header: company + logo --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(4) }};">
    <tr>
        <td width="65%" valign="top">
            <div class="company-name">{{ $data->companyLegalNameEn() }}</div>
            <div style="font-size: {{ $pt(7) }}; margin-top: {{ $pt(2) }};">
                CR. No. {{ $data->commercialRegister() }}
                <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif;"> سجل تجاري</span>
            </div>
        </td>
        <td width="35%" align="right" valign="top">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="{{ $data->logoAlt() }}" height="{{ round(78 * $scale) }}" style="max-width: {{ round(180 * $scale) }}px;">
            @endif
        </td>
    </tr>
</table>

<div class="quotation-title">Quotation</div>

{{-- Company info + date / quotation no --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(7) }};">
    <tr>
        <td width="58%" valign="top" class="company-block">
            <div dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif; font-weight: bold;">{{ $data->companyLegalNameAr() }}</div>
            <div>{{ $data->companyAddress() }}</div>
            <div>Tel: {{ $data->companyPhone() }} &nbsp;|&nbsp; Email: {{ $data->companyEmail() }}</div>
            <div>Website: {{ $data->companyWebsite() }} &nbsp;|&nbsp; VAT Number: {{ $data->vatNumber() }}</div>
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top" align="right" class="meta-block">
            <div><span class="meta-label">Date:</span> {{ $data->issueDateLong() }}</div>
            <div><span class="meta-label">Quotation No:</span> {{ $data->quotationNumber() }}</div>
            <div><span class="meta-label">Valid Until:</span> {{ $data->validUntilLong() }}</div>
        </td>
    </tr>
</table>

{{-- BILL TO + QUOTATION DETAILS --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $pt(7) }};">
    <tr>
        <td width="48%" valign="top" class="section-box">
            <div style="{{ $sectionTitle }}">Bill To</div>
            <div style="font-weight: bold; font-size: {{ $pt(8.5) }};">{{ $data->customerName() }}</div>
            @if($data->customerAddress())
                <div style="margin-top: {{ $pt(2) }};">{{ $data->customerAddress() }}</div>
            @endif
            @if($data->companyTaxNumber())
                <div style="margin-top: {{ $pt(2) }};"><span class="meta-label">VAT No:</span> {{ $data->companyTaxNumber() }}</div>
            @endif
            <div style="margin-top: {{ $pt(2) }}; font-size: {{ $pt(7) }};">
                Email / Contact No: {{ $data->customerEmail() }} / {{ $data->customerPhone() }}
            </div>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top" class="section-box">
            <div style="{{ $sectionTitle }}">Quotation Details</div>
            <div><span class="meta-label">Prepared By:</span> {{ $data->preparedBy() }}</div>
            @if($data->activityAt())
                <div style="margin-top: {{ $pt(2) }};"><span class="meta-label">Event Date:</span> {{ $data->activityAt() }}</div>
            @endif
            @if($data->installationAt())
                <div style="margin-top: {{ $pt(2) }};"><span class="meta-label">Installation:</span> {{ $data->installationAt() }}</div>
            @endif
            @if($data->dismantlingAt())
                <div style="margin-top: {{ $pt(2) }};"><span class="meta-label">Dismantling:</span> {{ $data->dismantlingAt() }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- Line items + totals --}}
<table class="items-table" style="margin-bottom: {{ $pt(7) }};">
    <thead>
        <tr>
            <th width="23%" align="left">Description</th>
            <th width="6%">Qty</th>
            <th width="10%">Price</th>
            <th width="9%">Discount<br>/ Unit</th>
            <th width="11%">Price<br>(Incl. VAT)</th>
            <th width="11%">Taxable<br>Value</th>
            <th width="6%">VAT%</th>
            <th width="11%">VAT<br>Amount</th>
            <th width="13%">Total</th>
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
                <td align="right">{{ $data->formatMoney($item['taxable_value'], 0) }}</td>
                <td align="center">{{ $item['vat_percent'] }}</td>
                <td align="right">SAR {{ $data->formatMoney($item['vat_amount'], 0) }}</td>
                <td align="right">SAR {{ $data->formatMoney($item['total'], 0) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="8" align="left" style="font-weight: bold; background-color: #f5f5f5;">SUBTOTAL</td>
            <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->grossSubtotal(), 0) }}</td>
        </tr>
        <tr>
            <td colspan="8" align="left" style="font-weight: bold; background-color: #f5f5f5;">DISCOUNT</td>
            <td align="left" style="font-weight: bold; background-color: #f5f5f5;">
                {{ $data->discountTotal() > 0 ? '- '.$data->formatSar($data->discountTotal(), 2) : 'SAR -' }}
            </td>
        </tr>
        <tr>
            <td colspan="8" align="left" style="font-weight: bold; background-color: #f5f5f5;">SUBTOTAL BEFORE VAT</td>
            <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->subtotal(), 0) }}</td>
        </tr>
        <tr>
            <td colspan="8" align="left" style="font-weight: bold; background-color: #f5f5f5;">VAT</td>
            <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->vatAmount(), 0) }}</td>
        </tr>
        @if($data->hasInsurance())
            <tr>
                <td colspan="8" align="left" style="font-weight: bold; background-color: #f5f5f5;">INSURANCE DEPOSIT</td>
                <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->insuranceAmount(), 2) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="8" align="left" style="font-weight: bold; background-color: #333; color: #fff;">TOTAL</td>
            <td align="left" style="font-weight: bold; background-color: #333; color: #fff;">{{ $data->formatSar($data->total(), 2) }}</td>
        </tr>
        @if($data->amountPaid() > 0)
            <tr>
                <td colspan="8" align="left" style="font-weight: bold; background-color: #f5f5f5;">AMOUNT PAID</td>
                <td align="left" style="font-weight: bold; background-color: #f5f5f5;">{{ $data->formatSar($data->amountPaid(), 2) }}</td>
            </tr>
        @endif
        @if($data->hasAmountDue())
            <tr>
                <td colspan="8" align="left" style="font-weight: bold; background-color: #fff7ed;">AMOUNT DUE</td>
                <td align="left" style="font-weight: bold; background-color: #fff7ed;">{{ $data->formatSar($data->amountDue(), 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>

@if($data->hasInsurance())
    <div style="margin: -{{ $pt(4) }} 0 {{ $pt(6) }} 0; font-size: {{ $pt(6.5) }}; color: #444; line-height: 1.3;">
        {{ $data->insuranceNoteEn() }}
        <br>
        <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif;">{{ $data->insuranceNoteAr() }}</span>
    </div>
@endif

{{-- Terms --}}
<div style="margin-bottom: {{ $pt(6) }};">
    <div style="{{ $sectionTitle }}">Terms &amp; Conditions</div>
    <ul class="terms-list">
        @foreach($data->termsAndConditions() as $term)
            <li>{{ $term }}</li>
        @endforeach
    </ul>
</div>

{{-- Bank + Payment --}}
<div style="margin-bottom: {{ $pt(5) }};">
    <div style="{{ $sectionTitle }}">Bank Details</div>
    <div style="font-size: {{ $pt(7.5) }}; line-height: 1.45;">
        <strong>{{ $data->bankName() }}</strong><br>
        IBAN: {{ $data->bankIban() }}<br>
        ACCT NUMBER: {{ $data->bankAccountNumber() }}<br>
        Account Name: {{ $data->bankAccountName() }}
    </div>

    @if($data->hasAmountDue() && $data->paymentUrl())
        <div style="{{ $sectionTitle }} margin-top: {{ $pt(7) }};">Online Payment</div>
        <div style="font-size: {{ $pt(7.5) }}; line-height: 1.45; padding: {{ $pt(5) }}; border: 1px solid #333; background-color: #f8fafc;">
            <strong>Pay the due amount online:</strong>
            {{ $data->formatSar($data->amountDue(), 2) }}
            <br>
            <a href="{{ $data->paymentUrl() }}" style="color: #1d4ed8; font-weight: bold;">
                Payment Link
            </a>
        </div>
    @endif
</div>

{{-- Reserves the flow space taken by the acknowledgment box, which is pinned
     to the bottom of the page and therefore no longer part of the flow. --}}
<div style="height: {{ $pt($ackReservedHeight) }};"></div>

{{-- Client Acknowledgment --}}
<div class="ack-box">
    <div style="font-weight: bold; font-size: {{ $pt(8.5) }}; margin-bottom: {{ $pt(4) }};">Client Acknowledgment</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: {{ $pt(7.5) }};">
        <tr>
            <td width="50%" valign="bottom">
                Company &amp; Client Name:
                <div class="ack-line"></div>
            </td>
            <td width="50%" valign="bottom" style="padding-left: {{ $pt(10) }};">
                Contact:
                <div class="ack-line"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" valign="bottom" style="padding-top: {{ $pt(6) }};">
                Signature:
                <div class="ack-line" style="width: 60%;"></div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
