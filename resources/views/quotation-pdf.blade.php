@php
    /** @var \App\Support\QuotationPdfData $data */
    $border = 'border: 1px solid #333;';
    $th = $border.' padding: 6px 5px; background-color: #f0f0f0; font-weight: bold; font-size: 8pt;';
    $td = $border.' padding: 6px 5px; font-size: 8pt; vertical-align: top;';
    $sectionTitle = 'font-size: 11pt; font-weight: bold; color: #1a1a1a; margin: 0 0 6px 0; text-transform: uppercase;';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Proposal {{ $data->quotationNumber() }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .proposal-title {
            font-size: 22pt;
            font-weight: bold;
            text-align: center;
            margin: 10px 0 14px 0;
            letter-spacing: 1px;
        }
        .meta-label {
            font-weight: bold;
            color: #333;
        }
        .items-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 8pt;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #333;
            padding: 5px 4px;
        }
        .items-table th {
            background-color: #e8e8e8;
            font-weight: bold;
            text-align: center;
        }
        .totals-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 9pt;
        }
        .totals-table td {
            padding: 5px 8px;
            border: 1px solid #333;
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
            font-size: 10pt;
        }
        .footer-bar {
            background-color: #333;
            color: #fff;
            font-size: 7.5pt;
            padding: 8px 10px;
            text-align: center;
            margin-top: 12px;
        }
        .terms-list {
            margin: 0;
            padding-left: 16px;
            font-size: 8pt;
            line-height: 1.55;
        }
        .terms-list li {
            margin-bottom: 4px;
        }
        .ack-box {
            border: 1px solid #333;
            padding: 10px 12px;
            margin-top: 10px;
            font-size: 8.5pt;
        }
        .ack-line {
            border-bottom: 1px solid #999;
            height: 22px;
            margin-top: 6px;
        }
    </style>
</head>
<body>

{{-- Top header: company + logo --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 4px;">
    <tr>
        <td width="65%" valign="top">
            <div class="company-name">{{ $data->companyLegalNameEn() }}</div>
            <div style="font-size: 8.5pt; margin-top: 3px;">
                C.R No. {{ $data->commercialRegister() }}
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif;">{{ $data->commercialRegister() }} السجل التجاري</span>
            </div>
        </td>
        <td width="35%" align="right" valign="top">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="Adventure World" height="52" style="max-width: 120px;">
            @endif
        </td>
    </tr>
</table>

<div class="proposal-title">Proposal</div>

{{-- Company info + date / proposal no --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
    <tr>
        <td width="58%" valign="top" style="font-size: 8.5pt; line-height: 1.55;">
            <div style="font-weight: bold; font-size: 10pt; margin-bottom: 4px;">{{ $data->companyLegalNameEn() }}</div>
            <div dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif; font-size: 8.5pt; margin-bottom: 4px;">{{ $data->companyLegalNameAr() }}</div>
            <div>{{ $data->companyAddress() }}</div>
            <div>Tel: {{ $data->companyPhone() }}</div>
            <div>Email: {{ $data->companyEmail() }}</div>
            <div>Website: {{ $data->companyWebsite() }}</div>
            <div>VAT Number: {{ $data->vatNumber() }}</div>
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top" align="right" style="font-size: 9pt; line-height: 1.7;">
            <div><span class="meta-label">Date:</span> {{ $data->issueDateLong() }}</div>
            <div><span class="meta-label">Proposal No:</span> {{ $data->quotationNumber() }}</div>
            <div><span class="meta-label">Valid Until:</span> {{ $data->validUntilLong() }}</div>
        </td>
    </tr>
</table>

{{-- BILL TO --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
    <tr>
        <td style="{{ $border }} padding: 10px 12px;">
            <div style="{{ $sectionTitle }}">Bill To</div>
            <div style="font-weight: bold; font-size: 10pt;">{{ $data->customerName() }}</div>
            @if($data->customerAddress())
                <div style="margin-top: 4px;">{{ $data->customerAddress() }}</div>
            @endif
            <div style="margin-top: 4px; font-size: 8.5pt;">
                Email / Contact No: {{ $data->customerEmail() }} / {{ $data->customerPhone() }}
            </div>
        </td>
    </tr>
</table>

{{-- QUOTATION DETAILS --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
    <tr>
        <td style="{{ $border }} padding: 10px 12px;">
            <div style="{{ $sectionTitle }}">Quotation Details</div>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 8.5pt;">
                <tr>
                    <td width="50%" valign="top">
                        <div><span class="meta-label">Prepared By:</span> {{ $data->preparedBy() }}</div>
                        <div style="margin-top: 3px;"><span class="meta-label">Valid Until:</span> {{ $data->validUntilLong() }}</div>
                    </td>
                    <td width="50%" valign="top">
                        @if($data->customerAddress())
                            <div><span class="meta-label">Location:</span> {{ $data->customerAddress() }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Line items table --}}
<table class="items-table" style="margin-bottom: 12px;">
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
                        <br><span style="font-size: 7.5pt; color: #444;">{{ $item['description'] }}</span>
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
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
    <tr>
        <td width="58%" valign="top">
            <div style="{{ $sectionTitle }}">Terms &amp; Conditions</div>
            <ul class="terms-list">
                @foreach($data->termsAndConditions() as $term)
                    <li>-: {{ $term }}</li>
                @endforeach
            </ul>

            <div style="{{ $sectionTitle }} margin-top: 12px;">Bank details:-</div>
            <div style="font-size: 8.5pt; line-height: 1.6;">
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
                <tr class="total-row">
                    <td class="label" style="background-color: #333; color: #fff;">TOTAL</td>
                    <td class="value" style="background-color: #333; color: #fff;">{{ $data->formatSar($data->total(), 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Client Acknowledgment --}}
<div class="ack-box">
    <div style="font-weight: bold; font-size: 10pt; margin-bottom: 8px;">Client Acknowledgment</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 8.5pt;">
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
            <td colspan="2" valign="bottom" style="padding-top: 10px;">
                Signature:
                <div class="ack-line" style="width: 60%;"></div>
            </td>
        </tr>
    </table>
</div>

{{-- Footer --}}
<div class="footer-bar">
    CR: {{ $data->commercialRegister() }}
    &nbsp;|&nbsp;
    Address: {{ $data->companyAddress() }}
    &nbsp;|&nbsp;
    Phone: {{ $data->companyPhone() }}
    &nbsp;|&nbsp;
    Email: {{ $data->companyEmail() }}
    <br>
    Thank you for your Business...!
</div>

</body>
</html>
