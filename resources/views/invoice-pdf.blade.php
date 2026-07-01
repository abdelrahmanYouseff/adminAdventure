@php
    /** @var \App\Support\InvoicePdfData $data */
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>فاتورة {{ $data->invoiceNumber() }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.7;
            color: #1f2937;
            direction: rtl;
            text-align: right;
            background: #ffffff;
        }

        .page {
            padding: 28px 32px;
        }

        .brand-bar {
            height: 6px;
            background: linear-gradient(to left, #3b89d2, #ff6b35);
            border-radius: 999px;
            margin-bottom: 24px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .logo {
            width: 78px;
            height: 78px;
            object-fit: contain;
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 4px;
        }

        .company-tagline {
            font-size: 11px;
            color: #6b7280;
            line-height: 1.5;
        }

        .invoice-badge {
            text-align: left;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #3b89d2;
            margin-bottom: 4px;
        }

        .invoice-number {
            font-size: 13px;
            color: #6b7280;
            direction: ltr;
            text-align: left;
        }

        .meta-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-bottom: 24px;
        }

        .meta-table td {
            width: 50%;
            vertical-align: top;
        }

        .card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 18px;
        }

        .card-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #3b89d2;
        }

        .info-row {
            width: 100%;
            margin-bottom: 8px;
        }

        .info-row td {
            padding: 2px 0;
            vertical-align: top;
        }

        .info-label {
            color: #6b7280;
            font-size: 11px;
            width: 38%;
            padding-left: 8px;
        }

        .info-value {
            color: #111827;
            font-weight: bold;
            font-size: 12px;
        }

        .info-value-ltr {
            direction: ltr;
            text-align: left;
            unicode-bidi: embed;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
        }

        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-overdue { background: #ffedd5; color: #c2410c; }

        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #1e3a5f;
            margin: 8px 0 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid #3b89d2;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .items-table th {
            background: #1e3a5f;
            color: #ffffff;
            padding: 11px 12px;
            font-size: 11px;
            font-weight: bold;
            text-align: right;
        }

        .items-table th.center { text-align: center; }
        .items-table th.ltr { direction: ltr; text-align: left; }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            vertical-align: top;
        }

        .items-table tr:nth-child(even) td {
            background: #f9fafb;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .item-name {
            font-weight: bold;
            color: #111827;
            margin-bottom: 3px;
        }

        .item-meta {
            color: #6b7280;
            font-size: 10px;
        }

        .money {
            direction: ltr;
            text-align: left;
            unicode-bidi: embed;
            white-space: nowrap;
            font-weight: bold;
        }

        .totals-wrap {
            width: 100%;
            margin-top: 8px;
        }

        .totals-wrap td {
            vertical-align: top;
        }

        .totals-box {
            width: 280px;
            margin-right: auto;
            margin-left: 0;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
        }

        .total-row {
            width: 100%;
            margin-bottom: 8px;
        }

        .total-row td {
            padding: 2px 0;
        }

        .total-label {
            color: #6b7280;
            font-size: 12px;
        }

        .total-value {
            text-align: left;
            direction: ltr;
            font-weight: bold;
            color: #111827;
        }

        .total-final td {
            padding-top: 10px;
            border-top: 2px solid #3b89d2;
            font-size: 15px;
            font-weight: bold;
            color: #1e3a5f;
        }

        .notes-box {
            margin-top: 18px;
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 12px;
            padding: 14px 16px;
        }

        .notes-title {
            font-size: 12px;
            font-weight: bold;
            color: #92400e;
            margin-bottom: 6px;
        }

        .notes-text {
            color: #a16207;
            font-size: 11px;
            line-height: 1.7;
        }

        .footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .footer-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 6px;
        }

        .footer-text {
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .footer-contact {
            color: #4b5563;
            font-size: 10px;
            line-height: 1.8;
        }

        .footer-meta {
            margin-top: 10px;
            font-size: 9px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="brand-bar"></div>

    <table class="header-table">
        <tr>
            <td style="width: 90px;">
                @if(file_exists($data->logoPath()))
                    <img src="{{ $data->logoPath() }}" alt="عالم المغامرة" class="logo">
                @endif
            </td>
            <td style="padding-right: 14px;">
                <div class="company-name">عالم المغامرة للترفيه</div>
                <div class="company-tagline">
                    تأجير ألعاب ترفيهية للأطفال في المملكة العربية السعودية<br>
                    admin.adventureksa.com
                </div>
            </td>
            <td class="invoice-badge" style="width: 180px;">
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
                            <td class="info-value info-value-ltr">{{ $data->invoiceNumber() }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">تاريخ الإصدار</td>
                            <td class="info-value info-value-ltr">{{ $data->issueDate() }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">تاريخ الاستحقاق</td>
                            <td class="info-value info-value-ltr">{{ $data->dueDate() ?? '—' }}</td>
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
                            <td class="info-value info-value-ltr">{{ $data->customerEmail() }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">رقم الجوال</td>
                            <td class="info-value info-value-ltr">{{ $data->customerPhone() }}</td>
                        </tr>
                        @if($data->activityDate())
                        <tr>
                            <td class="info-label">تاريخ الفعالية</td>
                            <td class="info-value info-value-ltr">{{ $data->activityDate() }}</td>
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
                <th style="width: 42%;">الوصف</th>
                <th class="center" style="width: 10%;">الكمية</th>
                <th class="center" style="width: 12%;">المدة</th>
                <th class="ltr" style="width: 18%;">سعر الوحدة</th>
                <th class="ltr" style="width: 18%;">الإجمالي</th>
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
                    <td style="text-align: center;">{{ $item['quantity'] }}</td>
                    <td style="text-align: center;">{{ !empty($item['duration']) ? $item['duration'].' يوم' : '—' }}</td>
                    <td class="money">{{ $data->formatMoney($item['unit_price']) }}</td>
                    <td class="money">{{ $data->formatMoney($item['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td></td>
            <td style="width: 280px;">
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
            تم إنشاء هذه الفاتورة آلياً بتاريخ {{ $data->generatedAt() }}
        </div>
    </div>
</div>
</body>
</html>
