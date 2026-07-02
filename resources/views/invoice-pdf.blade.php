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
            font-size: 10pt;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .muted { color: #6b7280; }
        .bold { font-weight: bold; }
        .section-head {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a5f;
            border-bottom: 2px solid #3b89d2;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        .status-overdue { background-color: #ffedd5; color: #c2410c; }
        .status-badge {
            padding: 3px 10px;
            font-size: 9pt;
            font-weight: bold;
        }
    </style>
</head>
<body>

{{-- شريط العلامة --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
    <tr>
        <td height="5" bgcolor="#3b89d2"></td>
        <td width="8" height="5" bgcolor="#ff6b35"></td>
    </tr>
</table>

{{-- الهيدر --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 14px;">
    <tr>
        <td width="72" align="right" valign="middle">
            @if(file_exists($data->logoPath()))
                <img src="{{ $data->logoPath() }}" width="64" height="64" alt="عالم المغامرة">
            @endif
        </td>
        <td align="right" valign="middle" style="padding-right: 12px;">
            <div style="font-size: 17pt; font-weight: bold; color: #1e3a5f;">عالم المغامرة للترفيه</div>
            <div class="muted" style="font-size: 9pt; line-height: 1.6; margin-top: 4px;">
                تأجير ألعاب ترفيهية للأطفال في المملكة العربية السعودية<br>
                admin.adventureksa.com
            </div>
        </td>
        <td width="180" align="left" valign="middle">
            <div style="font-size: 20pt; font-weight: bold; color: #3b89d2;">فاتورة</div>
            <div class="muted" style="font-size: 10pt; margin-top: 4px;" dir="ltr" align="left">#{{ $data->invoiceNumber() }}</div>
        </td>
    </tr>
</table>

{{-- بيانات الفاتورة والعميل --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px;">
    <tr>
        {{-- بيانات الفاتورة --}}
        <td width="49%" valign="top" bgcolor="#f8fafc" style="border: 1px solid #e5e7eb; padding: 12px;">
            <div class="section-head">بيانات الفاتورة</div>
            <table width="100%" cellpadding="5" cellspacing="0">
                <tr>
                    <td width="42%" align="right" class="muted">رقم الفاتورة</td>
                    <td width="58%" align="right" class="bold" dir="ltr">{{ $data->invoiceNumber() }}</td>
                </tr>
                <tr>
                    <td align="right" class="muted">تاريخ الإصدار</td>
                    <td align="right" class="bold" dir="ltr">{{ $data->issueDate() }}</td>
                </tr>
                <tr>
                    <td align="right" class="muted">تاريخ الاستحقاق</td>
                    <td align="right" class="bold" dir="ltr">{{ $data->dueDate() ?? '—' }}</td>
                </tr>
                <tr>
                    <td align="right" class="muted">الحالة</td>
                    <td align="right">
                        <span class="status-badge {{ $data->statusClass() }}">{{ $data->statusLabel() }}</span>
                    </td>
                </tr>
                <tr>
                    <td align="right" class="muted">طريقة الدفع</td>
                    <td align="right" class="bold">{{ $data->paymentMethodLabel() }}</td>
                </tr>
            </table>
        </td>

        <td width="2%"></td>

        {{-- بيانات العميل --}}
        <td width="49%" valign="top" bgcolor="#f8fafc" style="border: 1px solid #e5e7eb; padding: 12px;">
            <div class="section-head">بيانات العميل</div>
            <table width="100%" cellpadding="5" cellspacing="0">
                <tr>
                    <td width="42%" align="right" class="muted">اسم العميل</td>
                    <td width="58%" align="right" class="bold">{{ $data->customerName() }}</td>
                </tr>
                <tr>
                    <td align="right" class="muted">البريد الإلكتروني</td>
                    <td align="right" class="bold" dir="ltr">{{ $data->customerEmail() }}</td>
                </tr>
                <tr>
                    <td align="right" class="muted">رقم الجوال</td>
                    <td align="right" class="bold" dir="ltr">{{ $data->customerPhone() }}</td>
                </tr>
                @if($data->activityDate())
                <tr>
                    <td align="right" class="muted">تاريخ الفعالية</td>
                    <td align="right" class="bold" dir="ltr">{{ $data->activityDate() }}</td>
                </tr>
                @endif
                @if($data->address())
                <tr>
                    <td align="right" class="muted" valign="top">الموقع</td>
                    <td align="right" class="bold">{{ $data->address() }}</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- عنوان الجدول --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
    <tr>
        <td class="section-head">تفاصيل الفاتورة</td>
    </tr>
</table>

{{-- جدول المنتجات --}}
<table width="100%" cellpadding="8" cellspacing="0" style="border: 1px solid #e5e7eb; margin-bottom: 16px;">
    <thead>
        <tr bgcolor="#1e3a5f" style="color: #ffffff;">
            <th width="38%" align="right" style="color: #ffffff; font-size: 10pt;">الوصف</th>
            <th width="10%" align="center" style="color: #ffffff; font-size: 10pt;">الكمية</th>
            <th width="12%" align="center" style="color: #ffffff; font-size: 10pt;">المدة</th>
            <th width="20%" align="right" style="color: #ffffff; font-size: 10pt;">سعر الوحدة</th>
            <th width="20%" align="right" style="color: #ffffff; font-size: 10pt;">الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data->lineItems() as $index => $item)
            <tr bgcolor="{{ $index % 2 === 0 ? '#ffffff' : '#f9fafb' }}">
                <td align="right" valign="top">
                    <span class="bold">{{ $item['name'] }}</span>
                    @if(!empty($item['duration']) && $item['duration'] > 1)
                        <br><span class="muted" style="font-size: 9pt;">مدة الحجز: {{ $item['duration'] }} يوم</span>
                    @endif
                </td>
                <td align="center" valign="middle">{{ $item['quantity'] }}</td>
                <td align="center" valign="middle">{{ !empty($item['duration']) ? $item['duration'].' يوم' : '—' }}</td>
                <td align="right" valign="middle" dir="ltr" class="bold">{{ $data->formatMoney($item['unit_price']) }}</td>
                <td align="right" valign="middle" dir="ltr" class="bold">{{ $data->formatMoney($item['total']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- الإجماليات --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
    <tr>
        <td width="58%"></td>
        <td width="42%" valign="top">
            <table width="100%" cellpadding="7" cellspacing="0" bgcolor="#f8fafc" style="border: 1px solid #e5e7eb;">
                <tr>
                    <td align="right" class="muted">المجموع الفرعي</td>
                    <td width="42%" align="right" dir="ltr" class="bold">{{ $data->formatMoney($data->subtotal()) }}</td>
                </tr>
                <tr>
                    <td align="right" class="muted">ضريبة القيمة المضافة (15%)</td>
                    <td align="right" dir="ltr" class="bold">{{ $data->formatMoney($data->vatAmount()) }}</td>
                </tr>
                <tr>
                    <td colspan="2" style="border-top: 2px solid #3b89d2; padding-top: 4px;"></td>
                </tr>
                <tr>
                    <td align="right" style="font-size: 11pt; font-weight: bold; color: #1e3a5f;">الإجمالي المستحق</td>
                    <td align="right" dir="ltr" style="font-size: 11pt; font-weight: bold; color: #1e3a5f;">{{ $data->formatMoney($data->total()) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($data->notes())
<table width="100%" cellpadding="12" cellspacing="0" bgcolor="#fffbeb" style="border: 1px solid #fcd34d; margin-bottom: 16px;">
    <tr>
        <td align="right">
            <div style="font-weight: bold; color: #92400e; margin-bottom: 4px;">ملاحظات</div>
            <div style="color: #a16207; font-size: 9pt; line-height: 1.7;">{{ $data->notes() }}</div>
        </td>
    </tr>
</table>
@endif

{{-- الفوتر --}}
<table width="100%" cellpadding="14" cellspacing="0" style="border-top: 1px solid #e5e7eb; margin-top: 8px;">
    <tr>
        <td align="center">
            <div style="font-size: 11pt; font-weight: bold; color: #1e3a5f; margin-bottom: 4px;">شكراً لاختياركم عالم المغامرة!</div>
            <div class="muted" style="font-size: 9pt; margin-bottom: 6px;">نقدّر ثقتكم ونتطلع لخدمتكم مجدداً.</div>
            <div class="muted" style="font-size: 9pt; line-height: 1.8;">
                <strong>عالم المغامرة للترفيه</strong><br>
                البريد: info@adventureksa.com &nbsp;|&nbsp; الموقع: admin.adventureksa.com
            </div>
            <div style="font-size: 8pt; color: #9ca3af; margin-top: 8px;">
                تم إنشاء هذه الفاتورة آلياً بتاريخ <span dir="ltr">{{ $data->generatedAt() }}</span>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
