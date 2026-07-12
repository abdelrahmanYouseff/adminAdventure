@php
    /** @var \App\Support\DeliveryNotePdfData $data */
    $border = 'border: 1px solid #333;';
    $sectionTitle = 'font-size: 8pt; font-weight: bold; color: #1a1a1a; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.3px;';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Delivery Note {{ $data->referenceNumber() }}</title>
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
        .doc-title {
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
        .footer-bar {
            background-color: #333;
            color: #fff;
            font-size: 6.5pt;
            padding: 10px 12px;
            text-align: center;
            margin-top: 16px;
            line-height: 1.6;
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
        .location-box {
            {{ $border }}
            padding: 10px 12px;
            margin-bottom: 16px;
            font-size: 7.5pt;
            line-height: 1.65;
            background-color: #fafafa;
        }
    </style>
</head>
<body>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
    <tr>
        <td width="65%" valign="top">
            <div class="company-name">{{ $data->companyLegalNameEn() }}</div>
            <div style="font-size: 7pt; margin-top: 5px; line-height: 1.5;">
                C.R No. {{ $data->commercialRegister() }}
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <span dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif;">{{ $data->commercialRegister() }} السجل التجاري</span>
            </div>
        </td>
        <td width="35%" align="right" valign="top">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="Adventure World" height="46" style="max-width: 110px;">
            @endif
        </td>
    </tr>
</table>

<div class="doc-title">Delivery Note</div>

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
            <div><span class="meta-label">Reference No:</span> {{ $data->referenceNumber() }}</div>
            @if($data->invoiceNumber())
                <div><span class="meta-label">Invoice No:</span> {{ $data->invoiceNumber() }}</div>
            @endif
            <div><span class="meta-label">Order No:</span> {{ $data->orderNumber() }}</div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="section-box">
            <div style="{{ $sectionTitle }}">Deliver To</div>
            <div style="font-weight: bold; font-size: 8.5pt; margin-bottom: 4px;">{{ $data->customerName() }}</div>
            <div style="margin-top: 6px; font-size: 7pt;">
                Email / Contact No: {{ $data->customerEmail() }} / {{ $data->customerPhone() }}
            </div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="section-box">
            <div style="{{ $sectionTitle }}">Event Details</div>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 7.5pt; line-height: 1.65;">
                <tr>
                    <td width="50%" valign="top">
                        @if($data->eventDateLong())
                            <div><span class="meta-label">Event Date:</span> {{ $data->eventDateLong() }}</div>
                        @endif
                        <div style="margin-top: 5px;"><span class="meta-label">Products:</span> {{ $data->productsCount() }}</div>
                    </td>
                    <td width="50%" valign="top">
                        @if($data->address())
                            <div><span class="meta-label">Location:</span> {{ $data->address() }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($data->address())
    <div class="location-box">
        <strong>Installation / Delivery Location:</strong><br>
        {{ $data->address() }}
    </div>
@endif

<div style="{{ $sectionTitle }} margin-bottom: 8px;">Items To Install</div>
<table class="items-table" style="margin-bottom: 16px;">
    <thead>
        <tr>
            <th width="8%">#</th>
            <th width="72%" align="left">Description</th>
            <th width="20%">Qty</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data->productLines() as $index => $line)
            <tr>
                <td align="center">{{ $index + 1 }}</td>
                <td align="left"><strong>{{ $line['name'] }}</strong></td>
                <td align="center">{{ $line['quantity'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="ack-box">
    <div style="font-weight: bold; font-size: 8.5pt; margin-bottom: 10px;">Delivery Acknowledgment</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 7.5pt;">
        <tr>
            <td width="50%" valign="bottom">
                Delivered By (Company Representative):
                <div class="ack-line"></div>
            </td>
            <td width="50%" valign="bottom" style="padding-left: 16px;">
                Received By (Client):
                <div class="ack-line"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" valign="bottom" style="padding-top: 12px;">
                Signature &amp; Date:
                <div class="ack-line" style="width: 60%;"></div>
            </td>
        </tr>
    </table>
</div>

<div class="footer-bar">
    CR: {{ $data->commercialRegister() }}
    &nbsp;|&nbsp;
    Address: {{ $data->companyAddress() }}
    &nbsp;|&nbsp;
    Phone: {{ $data->companyPhone() }}
    &nbsp;|&nbsp;
    Email: {{ $data->companyEmail() }}
    <br>
    Adventure World Entertainment Company — Delivery Note
</div>

</body>
</html>
