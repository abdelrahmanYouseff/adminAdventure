@php
    /** @var \App\Support\DeliveryNotePdfData $data */
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إذن تسليم {{ $data->referenceNumber() }}</title>
    <style>
        body {
            font-family: xbriyaz, sans-serif;
            font-size: 9pt;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.55;
        }
        .muted { color: #6b7280; }
        .bold { font-weight: bold; }
        .section-head {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a5f;
            border-bottom: 2px solid #3b89d2;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .items-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 8.5pt;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
        }
        .items-table th {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .sig-line {
            border-bottom: 1px solid #94a3b8;
            height: 24px;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
    <tr>
        <td height="5" bgcolor="#3b89d2"></td>
        <td width="8" height="5" bgcolor="#ff6b35"></td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px;">
    <tr>
        <td width="72" align="right" valign="middle">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" width="58" height="58" alt="عالم المغامرة">
            @endif
        </td>
        <td align="right" valign="middle" style="padding-right: 10px;">
            <div style="font-size: 14pt; font-weight: bold; color: #1e3a5f;">{{ $data->companyName() }}</div>
            <div class="muted" style="font-size: 8pt; margin-top: 4px;">
                {{ $data->companyPhone() }} — {{ $data->companyEmail() }}
            </div>
        </td>
        <td width="170" align="left" valign="middle">
            <div style="font-size: 16pt; font-weight: bold; color: #3b89d2;">إذن تسليم</div>
            <div class="muted" style="font-size: 8.5pt; margin-top: 4px;" dir="ltr" align="left">Delivery Note</div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
    <tr>
        <td width="49%" valign="top" bgcolor="#f8fafc" style="border: 1px solid #e5e7eb; padding: 12px;">
            <div class="section-head">بيانات أمر العمل</div>
            <table width="100%" cellpadding="4" cellspacing="0">
                <tr>
                    <td width="40%" align="right" class="muted">الرقم المرجعي</td>
                    <td width="60%" align="right" class="bold" dir="ltr">{{ $data->referenceNumber() }}</td>
                </tr>
                @if($data->invoiceNumber())
                    <tr>
                        <td align="right" class="muted">رقم الفاتورة</td>
                        <td align="right" class="bold" dir="ltr">{{ $data->invoiceNumber() }}</td>
                    </tr>
                @endif
                <tr>
                    <td align="right" class="muted">رقم الطلب</td>
                    <td align="right" dir="ltr">{{ $data->orderNumber() }}</td>
                </tr>
                <tr>
                    <td align="right" class="muted">تاريخ الإصدار</td>
                    <td align="right" dir="ltr">{{ $data->issueDate() }}</td>
                </tr>
            </table>
        </td>
        <td width="2%"></td>
        <td width="49%" valign="top" bgcolor="#f8fafc" style="border: 1px solid #e5e7eb; padding: 12px;">
            <div class="section-head">بيانات العميل</div>
            <table width="100%" cellpadding="4" cellspacing="0">
                <tr>
                    <td width="40%" align="right" class="muted">اسم العميل</td>
                    <td width="60%" align="right" class="bold">{{ $data->customerName() }}</td>
                </tr>
                <tr>
                    <td align="right" class="muted">الجوال</td>
                    <td align="right" dir="ltr">{{ $data->customerPhone() }}</td>
                </tr>
                @if($data->eventDate())
                    <tr>
                        <td align="right" class="muted">تاريخ الفعالية</td>
                        <td align="right" dir="ltr">{{ $data->eventDate() }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

@if($data->address())
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
        <tr>
            <td bgcolor="#fffbeb" style="border: 1px solid #fde68a; padding: 10px 12px;">
                <span class="bold">موقع التركيب:</span>
                {{ $data->address() }}
            </td>
        </tr>
    </table>
@endif

<div class="section-head">المنتجات المطلوب تركيبها</div>
<table class="items-table" style="margin-bottom: 18px;">
    <thead>
        <tr>
            <th width="8%" align="center">#</th>
            <th width="72%" align="right">المنتج</th>
            <th width="20%" align="center">الكمية</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data->productLines() as $index => $line)
            <tr>
                <td align="center">{{ $index + 1 }}</td>
                <td align="right">{{ $line['name'] }}</td>
                <td align="center">{{ $line['quantity'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
    <tr>
        <td width="49%" valign="top" style="border: 1px solid #e5e7eb; padding: 12px;">
            <div class="bold">توقيع مندوب التسليم</div>
            <div class="sig-line"></div>
            <div class="muted" style="font-size: 8pt; margin-top: 6px;">الاسم / التاريخ</div>
        </td>
        <td width="2%"></td>
        <td width="49%" valign="top" style="border: 1px solid #e5e7eb; padding: 12px;">
            <div class="bold">توقيع المستلم</div>
            <div class="sig-line"></div>
            <div class="muted" style="font-size: 8pt; margin-top: 6px;">الاسم / التاريخ</div>
        </td>
    </tr>
</table>

<div class="muted" style="font-size: 7.5pt; margin-top: 14px; text-align: center;">
    تم إنشاء المستند في {{ $data->generatedAt() }} — {{ $data->companyName() }}
</div>

</body>
</html>
