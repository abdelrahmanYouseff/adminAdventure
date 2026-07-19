@php
    /** @var \App\Support\InvoicePdfData $data */
    $border = 'border: 1px solid #333;';
    $sectionTitle = 'font-size: 8pt; font-weight: bold; color: #1a1a1a; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.3px;';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $data->invoiceNumber() }}</title>
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
        .invoice-title {
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
            text-align: right;
            width: 65%;
        }
        .totals-table .value {
            text-align: right;
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
        .status-pill {
            display: inline-block;
            border: 1px solid #333;
            padding: 2px 8px;
            font-weight: bold;
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
    </style>
</head>
<body>

{{-- Top header: company + logo --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
    <tr>
        <td width="70%" valign="top">
            <div class="company-name">{{ $data->companyLegalNameEn() }}</div>
            <div style="font-size: 7pt; margin-top: 5px; line-height: 1.5;">
                CR. No. {{ $data->commercialRegister() }}
                <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif;"> سجل تجاري</span>
            </div>
        </td>
        <td width="30%" align="right" valign="top">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="Adventure World" height="46" style="max-width: 110px;">
            @endif
        </td>
    </tr>
</table>

{{-- Title --}}
<div style="text-align: center; margin: 10px 0 18px 0;">
    <div class="invoice-title" style="margin: 0 0 6px 0;">Invoice</div>
    <div style="font-size: 9pt; font-weight: bold; letter-spacing: 0.3px;">
        {{ $data->invoiceNumber() }}
    </div>
</div>

{{-- Company info + date / invoice no --}}
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
            <div><span class="meta-label">Invoice No:</span> {{ $data->invoiceNumber() }}</div>
            @if($data->dueDateLong())
                <div><span class="meta-label">Due Date:</span> {{ $data->dueDateLong() }}</div>
            @endif
            <div style="margin-top: 6px;">
                <span class="meta-label">Status:</span>
                <span class="status-pill">{{ $data->statusLabelEn() }}</span>
            </div>
        </td>
    </tr>
</table>

{{-- BILL TO + INVOICE DETAILS side by side --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
    <tr>
        <td width="48%" valign="top" style="border: 1px solid #333; padding: 12px 14px;">
            <div style="{{ $sectionTitle }}">Bill To</div>
            <div style="font-weight: bold; font-size: 8.5pt; margin-bottom: 4px;">{{ $data->customerName() }}</div>
            <div style="margin-top: 6px; font-size: 7pt;">
                Email / Contact No: {{ $data->customerEmail() }} / {{ $data->customerPhone() }}
            </div>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top" style="border: 1px solid #333; padding: 12px 14px;">
            <div style="{{ $sectionTitle }}">Invoice Details</div>
            <div style="font-size: 7.5pt; line-height: 1.65;">
                <div><span class="meta-label">Payment Method:</span> {{ $data->paymentMethodLabelEn() }}</div>
                <div style="margin-top: 5px;"><span class="meta-label">Status:</span> {{ $data->statusLabelEn() }}</div>
                @if($data->dueDateLong())
                    <div style="margin-top: 5px;"><span class="meta-label">Due Date:</span> {{ $data->dueDateLong() }}</div>
                @endif
                @if($data->activityDateLong())
                    <div style="margin-top: 5px;"><span class="meta-label">Event Date:</span> {{ $data->activityDateLong() }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- Line items table --}}
<table class="items-table" style="margin-bottom: 16px;">
    <thead>
        <tr>
            <th width="28%" align="left">Description</th>
            <th width="7%">Qty</th>
            <th width="11%">Price</th>
            <th width="12%">Price<br>(Incl. VAT)</th>
            <th width="12%">Taxable<br>Value</th>
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
                    @if($item['description'])
                        <br><span style="font-size: 6pt; color: #444; line-height: 1.5;">{{ $item['description'] }}</span>
                    @endif
                </td>
                <td align="center">{{ $item['quantity'] }}</td>
                <td align="right">{{ $data->formatMoney($item['unit_price'], 4) }}</td>
                <td align="right">{{ $data->formatMoney($item['unit_price_incl_vat'], 4) }} SAR</td>
                <td align="right">{{ $data->formatMoney($item['taxable_value'], 0) }}</td>
                <td align="center">{{ $item['vat_percent'] }}</td>
                <td align="right">SAR {{ $data->formatMoney($item['vat_amount'], 0) }}</td>
                <td align="right">SAR {{ $data->formatMoney($item['total'], 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Terms + Bank + Totals --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
    <tr>
        <td width="58%" valign="top">
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
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top">
            <table class="totals-table">
                <tr>
                    <td class="label">SUBTOTAL</td>
                    <td class="value">{{ $data->formatSar($data->subtotal(), 0) }}</td>
                </tr>
                <tr>
                    <td class="label">DISCOUNT</td>
                    <td class="value">SAR -</td>
                </tr>
                <tr>
                    <td class="label">SUBTOTAL BEFORE VAT</td>
                    <td class="value">{{ $data->formatSar($data->subtotal(), 0) }}</td>
                </tr>
                <tr>
                    <td class="label">VAT</td>
                    <td class="value">{{ $data->formatSar($data->vatAmount(), 0) }}</td>
                </tr>
                @if($data->hasInsurance())
                    <tr>
                        <td class="label" style="background-color: #fff8e6;">
                            INSURANCE DEPOSIT
                            <div style="font-size: 6pt; font-weight: normal; color: #664d00; margin-top: 2px;">
                                Refundable upon pickup / مسترد عند الاستلام
                            </div>
                        </td>
                        <td class="value" style="background-color: #fff8e6;">{{ $data->formatSar($data->insuranceAmount(), 2) }}</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td class="label" style="background-color: #333; color: #fff;">TOTAL</td>
                    <td class="value" style="background-color: #333; color: #fff;">{{ $data->formatSar($data->total(), 2) }}</td>
                </tr>
            </table>
            @if($data->hasInsurance())
                <div style="margin-top: 8px; font-size: 6.5pt; color: #555; line-height: 1.5;">
                    {{ $data->insuranceNoteEn() }}
                    <br>
                    <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif;">{{ $data->insuranceNoteAr() }}</span>
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
