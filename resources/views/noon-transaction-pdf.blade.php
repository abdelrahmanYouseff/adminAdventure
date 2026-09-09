<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إيصال نون {{ $noon['id'] }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 11pt;
            color: #111827;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 18px;
            border-bottom: 2px solid #111827;
            padding-bottom: 12px;
        }
        .title {
            font-size: 20pt;
            font-weight: bold;
            margin: 0 0 6px 0;
        }
        .subtitle {
            font-size: 10pt;
            color: #4b5563;
        }
        .meta {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .meta td {
            padding: 8px;
            vertical-align: top;
            border: 1px solid #d1d5db;
        }
        .label {
            background: #f3f4f6;
            font-weight: bold;
            width: 28%;
        }
        .amount {
            margin-top: 18px;
            padding: 14px;
            background: #ecfdf5;
            color: #065f46;
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
        }
        .footer {
            margin-top: 28px;
            font-size: 9pt;
            color: #6b7280;
            text-align: center;
        }
        .ltr { direction: ltr; text-align: left; unicode-bidi: embed; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">إيصال دفع نون</p>
        <p class="subtitle">Noon Payment Receipt</p>
        <p class="subtitle ltr">{{ $noon['id'] }}</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">اسم العميل</td>
            <td colspan="3">{{ $customer_name }}</td>
        </tr>
        <tr>
            <td class="label">الجوال</td>
            <td class="ltr">{{ $customer_phone ?: '—' }}</td>
            <td class="label">رقم الطلب</td>
            <td class="ltr">{{ $order_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">رقم عملية نون</td>
            <td class="ltr">{{ $noon['id'] }}</td>
            <td class="label">الحالة</td>
            <td>ناجحة</td>
        </tr>
        <tr>
            <td class="label">المرجع</td>
            <td class="ltr">{{ $noon['reference'] ?: '—' }}</td>
            <td class="label">التاريخ</td>
            <td class="ltr">{{ $noon['created_at'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">طريقة الدفع</td>
            <td colspan="3">بوابة نون</td>
        </tr>
    </table>

    <div class="amount ltr">
        {{ number_format((float) $noon['amount'], 2) }} {{ $noon['currency'] ?? 'SAR' }}
    </div>

    <div class="footer">
        تم سحب هذه المعاملة من بوابة نون وإصدار الإيصال إلكترونياً من نظام Adventure World
    </div>
</body>
</html>
