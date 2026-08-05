@php
    /** @var \App\Support\QuotationPdfData $data */
    $border = 'border: 1px solid #333;';
    $th = $border.' padding: 7px 6px; background-color: #f0f0f0; font-weight: bold; font-size: 6.5pt;';
    $td = $border.' padding: 7px 6px; font-size: 6.5pt; vertical-align: top;';
    $sectionTitle = 'font-size: 8pt; font-weight: bold; color: #1a1a1a; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.3px;';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Quotation {{ $data->quotationNumber() }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 7.5pt;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            line-height: 1.55;
        }
        .company-name {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .quotation-title {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin: 16px 0 20px 0;
            letter-spacing: 0.8px;
        }
        .meta-label {
            font-weight: bold;
            color: #333;
        }
        .items-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 6.5pt;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #333;
            padding: 7px 6px;
            line-height: 1.45;
        }
        .items-table th {
            background-color: #e8e8e8;
            font-weight: bold;
            text-align: center;
        }
        .totals-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 7.5pt;
        }
        .totals-table td {
            padding: 7px 10px;
            border: 1px solid #333;
            line-height: 1.5;
        }
        .totals-table .label {
            font-weight: bold;
            background-color: #f5f5f5;
            text-align: left;
            width: 65%;
        }
        .totals-table .value {
            text-align: left;
            font-weight: bold;
            width: 35%;
        }
        .totals-table .total-row td {
            background-color: #333;
            color: #fff;
            font-size: 8pt;
        }
        .terms-list {
            margin: 0;
            padding-left: 14px;
            font-size: 7pt;
            line-height: 1.65;
        }
        .terms-list li {
            margin-bottom: 6px;
        }
        .ack-box {
            border: 1px solid #333;
            padding: 12px 14px;
            margin-top: 14px;
            font-size: 7.5pt;
        }
        .ack-line {
            border-bottom: 1px solid #999;
            height: 20px;
            margin-top: 8px;
        }
        .section-box {
            {{ $border }}
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .company-block {
            font-size: 7.5pt;
            line-height: 1.65;
        }
        .company-block .name {
            font-weight: bold;
            font-size: 8.5pt;
            margin-bottom: 6px;
        }
        .meta-block {
            font-size: 7.5pt;
            line-height: 1.75;
        }
        .meta-block div {
            margin-bottom: 3px;
        }
    </style>
</head>
<body>

{{-- Top header: company + logo --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
    <tr>
        <td width="65%" valign="top">
            <div class="company-name">{{ $data->companyLegalNameEn() }}</div>
            <div style="font-size: 7pt; margin-top: 5px; line-height: 1.5;">
                CR. No. {{ $data->commercialRegister() }}
                <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif;"> سجل تجاري</span>
            </div>
        </td>
        <td width="35%" align="right" valign="top">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="{{ $data->logoAlt() }}" height="46" style="max-width: 110px;">
            @endif
        </td>
    </tr>
</table>

<div class="quotation-title">Quotation</div>

{{-- Company info + date / quotation no --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px;">
    <tr>
        <td width="58%" valign="top" class="company-block">
            <div class="name">{{ $data->companyLegalNameEn() }}</div>
            <div dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif; margin-bottom: 6px;">{{ $data->companyLegalNameAr() }}</div>
            <div>{{ $data->companyAddress() }}</div>
            <div>Tel: {{ $data->companyPhone() }}</div>
            <div>Email: {{ $data->companyEmail() }}</div>
            <div>Website: {{ $data->companyWebsite() }}</div>
            <div>VAT Number: {{ $data->vatNumber() }}</div>
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top" align="right" class="meta-block">
            <div><span class="meta-label">Date:</span> {{ $data->issueDateLong() }}</div>
            <div><span class="meta-label">Quotation No:</span> {{ $data->quotationNumber() }}</div>
            <div><span class="meta-label">Valid Until:</span> {{ $data->validUntilLong() }}</div>
        </td>
    </tr>
</table>

{{-- BILL TO --}}
<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="section-box">
            <div style="{{ $sectionTitle }}">Bill To</div>
            <div style="font-weight: bold; font-size: 8.5pt; margin-bottom: 4px;">{{ $data->customerName() }}</div>
            @if($data->customerAddress())
                <div style="margin-top: 5px;">{{ $data->customerAddress() }}</div>
            @endif
            @if($data->companyTaxNumber())
                <div style="margin-top: 5px;"><span class="meta-label">VAT No:</span> {{ $data->companyTaxNumber() }}</div>
                <div style="margin-top: 2px;" dir="rtl"><span class="meta-label">الرقم الضريبي:</span> {{ $data->companyTaxNumber() }}</div>
            @endif
            <div style="margin-top: 6px; font-size: 7pt;">
                Email / Contact No: {{ $data->customerEmail() }} / {{ $data->customerPhone() }}
            </div>
        </td>
    </tr>
</table>

{{-- QUOTATION DETAILS --}}
<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="section-box">
            <div style="{{ $sectionTitle }}">Quotation Details</div>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 7.5pt; line-height: 1.65;">
                <tr>
                    <td width="50%" valign="top">
                        <div><span class="meta-label">Prepared By:</span> {{ $data->preparedBy() }}</div>
                        <div style="margin-top: 5px;"><span class="meta-label">Valid Until:</span> {{ $data->validUntilLong() }}</div>
                        @if($data->activityAt())
                            <div style="margin-top: 5px;"><span class="meta-label">Event Date:</span> {{ $data->activityAt() }}</div>
                        @endif
                        @if($data->installationAt())
                            <div style="margin-top: 5px;"><span class="meta-label">Installation:</span> {{ $data->installationAt() }}</div>
                        @endif
                        @if($data->dismantlingAt())
                            <div style="margin-top: 5px;"><span class="meta-label">Dismantling:</span> {{ $data->dismantlingAt() }}</div>
                        @endif
                    </td>
                    <td width="50%" valign="top">
                        @if($data->customerAddress())
                            <div><span class="meta-label">Location:</span> {{ $data->customerAddress() }}</div>
                        @endif
                        @if($data->activityAt())
                            <div style="margin-top: 5px;" dir="rtl"><span class="meta-label">تاريخ الفعالية:</span> {{ $data->activityAt() }}</div>
                        @endif
                        @if($data->installationAt())
                            <div style="margin-top: 5px;" dir="rtl"><span class="meta-label">تاريخ التركيب:</span> {{ $data->installationAt() }}</div>
                        @endif
                        @if($data->dismantlingAt())
                            <div style="margin-top: 5px;" dir="rtl"><span class="meta-label">تاريخ الفك:</span> {{ $data->dismantlingAt() }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Line items + totals --}}
<table class="items-table" style="margin-bottom: 16px;">
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
                    @if(!empty($item['statement']))
                        <br><span style="font-size: 6pt; color: #666; line-height: 1.5;">البيان: {{ $item['statement'] }}</span>
                    @endif
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
    <div style="margin: -8px 0 14px 0; font-size: 6.5pt; color: #444; line-height: 1.45;">
        {{ $data->insuranceNoteEn() }}
        <br>
        <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif;">{{ $data->insuranceNoteAr() }}</span>
    </div>
@endif

{{-- Terms + Bank --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
    <tr>
        <td width="100%" valign="top">
            <div style="{{ $sectionTitle }}">Terms &amp; Conditions</div>
            <ul class="terms-list">
                @foreach($data->termsAndConditions() as $term)
                    <li>-: {{ $term }}</li>
                @endforeach
            </ul>

            <div style="{{ $sectionTitle }} margin-top: 16px;">Bank details:-</div>
            <div style="font-size: 7.5pt; line-height: 1.7;">
                <strong>{{ $data->bankName() }}</strong><br>
                IBAN: {{ $data->bankIban() }}<br>
                ACCT NUMBER: {{ $data->bankAccountNumber() }}<br>
                Account Name: {{ $data->bankAccountName() }}
            </div>

            @if($data->hasAmountDue() && $data->paymentUrl())
                <div style="{{ $sectionTitle }} margin-top: 16px;">Online Payment:-</div>
                <div style="font-size: 7.5pt; line-height: 1.7; padding: 10px; border: 1px solid #333; background-color: #f8fafc;">
                    <strong>Pay the due amount online:</strong>
                    {{ $data->formatSar($data->amountDue(), 2) }}
                    <br>
                    <a href="{{ $data->paymentUrl() }}" style="color: #1d4ed8; font-weight: bold;">
                        Payment Link
                    </a>
                </div>
            @endif
        </td>
    </tr>
</table>

{{-- Client Acknowledgment --}}
<div class="ack-box">
    <div style="font-weight: bold; font-size: 8.5pt; margin-bottom: 10px;">Client Acknowledgment</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 7.5pt;">
        <tr>
            <td width="50%" valign="bottom">
                Company &amp; Client Name:
                <div class="ack-line"></div>
            </td>
            <td width="50%" valign="bottom" style="padding-left: 16px;">
                Contact:
                <div class="ack-line"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" valign="bottom" style="padding-top: 12px;">
                Signature:
                <div class="ack-line" style="width: 60%;"></div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
