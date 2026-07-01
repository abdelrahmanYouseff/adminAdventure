@php
    /** @var \App\Support\InvoicePdfData $data */
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة {{ $data->invoiceNumber() }}</title>
    <style>
        body {
            font-family: xbriyaz, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #1f2937;
            direction: rtl;
            text-align: right;
        }

        .brand-bar {
            height: 5px;
            background-color: #3b89d2;
            margin-bottom: 18px;
        }

        .header-table,
        .meta-table,
        .items-table,
        .info-row,
        .total-row,
        .totals-wrap {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .logo {
            width: 70px;
            height: 70px;
        }

        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 4px;
        }

        .company-tagline {
            font-size: 9pt;
            color: #6b7280;
            line-height: 1.5;
        }

        .invoice-title {
            font-size: 22pt;
            font-weight: bold;
            color: #3b89d2;
            margin-bottom: 4px;
            text-align: left;
        }

        .invoice-number {
            font-size: 11pt;
            color: #6b7280;
            text-align: left;
            direction: ltr;
        }

        .meta-table {
            margin-bottom: 18px;
        }

        .meta-table > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0 0 0 8px;
        }

        .card {
            background-color: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .card-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #3b89d2;
        }

        .info-row td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 10pt;
        }

        .info-label {
            color: #6b7280;
            width: 40%;
            padding-left: 8px;
        }

        .info-value {
            color: #111827;
            font-weight: bold;
        }

        .ltr {
            direction: ltr;
            text-align: left;
            unicode-bidi: embed;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: bold;
        }

        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        .status-overdue { background-color: #ffedd5; color: #c2410c; }

        .section-title {
            font-size: 13pt;
            font-weight: bold;
            color: #1e3a5f;
            margin: 10px 0 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #3b89d2;
        }

        .items-table {
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
        }

        .items-table th {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 9px 10px;
            font-size: 10pt;
            font-weight: bold;
            text-align: right;
            border: 1px solid #1e3a5f;
        }

        .items-table th.center { text-align: center; }

        .items-table td {
            padding: 10px;
            border: 1px solid #e5e7eb;
            font-size: 10pt;
            vertical-align: top;
        }

        .item-name {
            font-weight: bold;
            color: #111827;
        }

        .item-meta {
            color: #6b7280;
            font-size: 9pt;
            margin-top: 2px;
        }

        .money {
            direction: ltr;
            text-align: left;
            unicode-bidi: embed;
            font-weight: bold;
            white-space: nowrap;
        }

        .totals-box {
            width: 260px;
            margin-right: 0;
            margin-left: auto;
            background-color: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .total-row td {
            padding: 4px 0;
            font-size: 10pt;
        }

        .total-label {
            color: #6b7280;
        }

        .total-value {
            text-align: left;
            direction: ltr;
            font-weight: bold;
        }

        .total-final td {
            padding-top: 8px;
            border-top: 2px solid #3b89d2;
            font-size: 12pt;
            font-weight: bold;
            color: #1e3a5f;
        }

        .notes-box {
            margin-top: 16px;
            background-color: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .notes-title {
            font-size: 10pt;
            font-weight: bold;
            color: #92400e;
            margin-bottom: 4px;
        }

        .notes-text {
            color: #a16207;
            font-size: 9pt;
        }

        .footer {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .footer-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 4px;
        }

        .footer-text,
        .footer-contact {
            color: #6b7280;
            font-size: 9pt;
            line-height: 1.7;
        }

        .footer-meta {
            margin-top: 8px;
            font-size: 8pt;
            color: #9ca3af;
        }
    </style>
</head>
<body>

<div class="brand-bar"></div>

<table class="header-table">
    <tr>
        <td style="width: 80px;">
            @if(file_exists($data->logoPath()))
                <img src="{{ $data->logoPath() }}" alt="عالم المغامرة" class="logo">
            @endif
        </td>
        <td style="padding-right: 12px;">
            <div class="company-name">عالم المغامرة للترفيه</div>
            <div class="company-tagline">
                تأجير ألعاب ترفيهية للأطفال في المملكة العربية السعودية<br>
                admin.adventureksa.com
            </div>
        </td>
        <td style="width: 170px;">
            <div class="invoice-title">فاتورة</div>
            <div class="invoice-number">#{{ $data->invoiceNumber() }}</div>
        </td>
    </tr>
</table>

<table class="meta-table">
    <tr>
        <td>
            <div class="card">
                <div class="card-title">بيانات الفاتورة</div>
                <table class="info-row">
                    <tr>
                        <td class="info-label">رقم الفاتورة</td>
                        <td class="info-value ltr">{{ $data->invoiceNumber() }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">تاريخ الإصدار</td>
                        <td class="info-value ltr">{{ $data->issueDate() }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">تاريخ الاستحقاق</td>
                        <td class="info-value ltr">{{ $data->dueDate() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">الحالة</td>
                        <td class="info-value">
                            <span class="status-badge {{ $data->statusClass() }}">{{ $data->statusLabel() }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="info-label">طريقة الدفع</td>
                        <td class="info-value">{{ $data->paymentMethodLabel() }}</td>
                    </tr>
                </table>
            </div>
        </td>
        <td>
            <div class="card">
                <div class="card-title">بيانات العميل</div>
                <table class="info-row">
                    <tr>
                        <td class="info-label">اسم العميل</td>
                        <td class="info-value">{{ $data->customerName() }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">البريد الإلكتروني</td>
                        <td class="info-value ltr">{{ $data->customerEmail() }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">رقم الجوال</td>
                        <td class="info-value ltr">{{ $data->customerPhone() }}</td>
                    </tr>
                    @if($data->activityDate())
                    <tr>
                        <td class="info-label">تاريخ الفعالية</td>
                        <td class="info-value ltr">{{ $data->activityDate() }}</td>
                    </tr>
                    @endif
                    @if($data->address())
                    <tr>
                        <td class="info-label">الموقع</td>
                        <td class="info-value">{{ $data->address() }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="section-title">تفاصيل الفاتورة</div>

<table class="items-table">
    <thead>
        <tr>
            <th style="width: 40%;">الوصف</th>
            <th class="center" style="width: 10%;">الكمية</th>
            <th class="center" style="width: 12%;">المدة</th>
            <th style="width: 19%;">سعر الوحدة</th>
            <th style="width: 19%;">الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data->lineItems() as $item)
            <tr>
                <td>
                    <div class="item-name">{{ $item['name'] }}</div>
                    @if(!empty($item['duration']) && $item['duration'] > 1)
                        <div class="item-meta">مدة الحجز: {{ $item['duration'] }} يوم</div>
                    @endif
                </td>
                <td class="center">{{ $item['quantity'] }}</td>
                <td class="center">{{ !empty($item['duration']) ? $item['duration'].' يوم' : '—' }}</td>
                <td class="money">{{ $data->formatMoney($item['unit_price']) }}</td>
                <td class="money">{{ $data->formatMoney($item['total']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals-wrap">
    <tr>
        <td></td>
        <td style="width: 260px;">
            <div class="totals-box">
                <table class="total-row">
                    <tr>
                        <td class="total-label">المجموع الفرعي</td>
                        <td class="total-value">{{ $data->formatMoney($data->subtotal()) }}</td>
                    </tr>
                    <tr>
                        <td class="total-label">ضريبة القيمة المضافة (15%)</td>
                        <td class="total-value">{{ $data->formatMoney($data->vatAmount()) }}</td>
                    </tr>
                    <tr class="total-final">
                        <td>الإجمالي المستحق</td>
                        <td class="total-value">{{ $data->formatMoney($data->total()) }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

@if($data->notes())
    <div class="notes-box">
        <div class="notes-title">ملاحظات</div>
        <div class="notes-text">{{ $data->notes() }}</div>
    </div>
@endif

<div class="footer">
    <div class="footer-title">شكراً لاختياركم عالم المغامرة!</div>
    <div class="footer-text">نقدّر ثقتكم ونتطلع لخدمتكم مجدداً.</div>
    <div class="footer-contact">
        <strong>عالم المغامرة للترفيه</strong><br>
        البريد: info@adventureksa.com<br>
        الموقع: admin.adventureksa.com
    </div>
    <div class="footer-meta">
        تم إنشاء هذه الفاتورة آلياً بتاريخ <span class="ltr">{{ $data->generatedAt() }}</span>
    </div>
</div>

</body>
</html>
