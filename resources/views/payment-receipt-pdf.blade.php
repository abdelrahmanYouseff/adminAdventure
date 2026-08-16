<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سند قبض {{ $receipt->receipt_number }}</title>
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
            padding: 6px 8px;
            vertical-align: top;
            border: 1px solid #d1d5db;
        }
        .label {
            background: #f3f4f6;
            font-weight: bold;
            width: 28%;
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
            font-size: 10pt;
        }
        .items th,
        .items td {
            border: 1px solid #d1d5db;
            padding: 8px 6px;
        }
        .items th {
            background: #111827;
            color: #fff;
            text-align: center;
        }
        .totals {
            width: 55%;
            margin-right: auto;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .totals td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
        }
        .totals .k {
            background: #f9fafb;
            font-weight: bold;
            width: 60%;
        }
        .totals .highlight td {
            background: #111827;
            color: #fff;
            font-weight: bold;
        }
        .totals .paid td {
            background: #ecfdf5;
            color: #065f46;
            font-weight: bold;
        }
        .totals .remain td {
            background: #fffbeb;
            color: #92400e;
            font-weight: bold;
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
        <p class="title">سند قبض</p>
        <p class="subtitle">Receipt Voucher</p>
        <p class="subtitle ltr">{{ $receipt->receipt_number }}</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">رقم الطلب</td>
            <td class="ltr">{{ $order->order_number }}</td>
            <td class="label">تاريخ السند</td>
            <td class="ltr">{{ optional($receipt->created_at)->format('Y-m-d H:i') }}</td>
        </tr>
        <tr>
            <td class="label">اسم العميل</td>
            <td colspan="3">{{ $order->customer_name }}</td>
        </tr>
        <tr>
            <td class="label">الجوال</td>
            <td class="ltr">{{ $order->customer_phone ?: '—' }}</td>
            <td class="label">تاريخ الفعالية</td>
            <td class="ltr">{{ optional($order->activity_date)->format('Y-m-d') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">العنوان</td>
            <td colspan="3">{{ $order->address ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">طريقة الدفع</td>
            <td>{{ $receipt->payment_method ?: ($order->payment_method ?: '—') }}</td>
            <td class="label">الموظف</td>
            <td>{{ $receipt->recordedBy?->name ?: '—' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>السعر</th>
                <th>خصم / وحدة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $index => $item)
                <tr>
                    <td class="ltr" style="text-align:center">{{ $index + 1 }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td class="ltr" style="text-align:center">{{ $item['quantity'] }}</td>
                    <td class="ltr" style="text-align:center">{{ number_format($item['price'], 2) }}</td>
                    <td class="ltr" style="text-align:center">{{ number_format($item['discount'], 2) }}</td>
                    <td class="ltr" style="text-align:center">{{ number_format($item['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center">لا توجد منتجات</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="k">إجمالي الطلب</td>
            <td class="ltr">{{ number_format((float) $receipt->total_amount, 2) }} {{ $order->currency ?: 'SAR' }}</td>
        </tr>
        <tr>
            <td class="k">المدفوع قبل هذه الدفعة</td>
            <td class="ltr">{{ number_format((float) $receipt->amount_paid_before, 2) }} {{ $order->currency ?: 'SAR' }}</td>
        </tr>
        <tr class="paid">
            <td>مبلغ هذه الدفعة</td>
            <td class="ltr">{{ number_format((float) $receipt->amount, 2) }} {{ $order->currency ?: 'SAR' }}</td>
        </tr>
        <tr>
            <td class="k">إجمالي المدفوع بعد الدفعة</td>
            <td class="ltr">{{ number_format((float) $receipt->amount_paid_after, 2) }} {{ $order->currency ?: 'SAR' }}</td>
        </tr>
        <tr class="remain">
            <td>المتبقي بعد الدفعة</td>
            <td class="ltr">{{ number_format((float) $receipt->remaining_after, 2) }} {{ $order->currency ?: 'SAR' }}</td>
        </tr>
    </table>

    @if (filled($order->notes))
        <p style="margin-top: 16px; white-space: pre-wrap;"><strong>ملاحظات الطلب:</strong> {{ $order->notes }}</p>
    @endif
    @if (filled($receipt->notes) && trim((string) $receipt->notes) !== trim((string) ($order->notes ?? '')))
        <p style="margin-top: 8px; white-space: pre-wrap;"><strong>ملاحظات السند:</strong> {{ $receipt->notes }}</p>
    @endif

    <div class="footer">
        تم إصدار هذا السند إلكترونياً من نظام Adventure World
    </div>
</body>
</html>
