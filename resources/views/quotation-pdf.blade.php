@php
    /** @var \App\Support\QuotationPdfData $data */
    $cell = 'border: 1px solid #000; padding: 4px; font-size: 8.5pt;';
    $labelCell = $cell.' background-color: #f5f5f5; font-weight: bold;';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>عرض سعر {{ $data->quotationNumber() }}</title>
    <style>
        body {
            font-family: xbriyaz, sans-serif;
            font-size: 8.5pt;
            color: #000;
            margin: 0;
            padding: 0;
            line-height: 1.45;
        }
        .muted { color: #333; }
        .bold { font-weight: bold; }
        .section-head {
            font-size: 9pt;
            font-weight: bold;
            color: #000;
            border-bottom: 1px solid #000;
            padding-bottom: 4px;
            margin: 0;
        }
        table.section-gap-table {
            width: 100%;
            border: 0;
            margin: 0;
            padding: 0;
        }
        table.bordered {
            border-collapse: collapse;
            width: 100%;
            font-size: 8.5pt;
        }
        table.bordered th,
        table.bordered td {
            border: 1px solid #000;
            padding: 4px;
        }
        table.bordered th {
            background-color: #e8e8e8;
            font-weight: bold;
        }
    </style>
</head>
<body>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px; border-bottom: 2px solid #000; padding-bottom: 12px;">
    <tr>
        <td width="72" align="right" valign="middle">
            @if(file_exists($data->logoPath()))
                <img src="{{ $data->logoPath() }}" width="56" height="56" alt="عالم المغامرة">
            @endif
        </td>
        <td align="right" valign="middle" style="padding-right: 12px;">
            <div style="font-size: 14pt; font-weight: bold; color: #000;">عالم المغامرة للترفيه</div>
            <div class="muted" style="font-size: 7.5pt; line-height: 1.5; margin-top: 3px;">
                تأجير ألعاب ترفيهية للأطفال في المملكة العربية السعودية
            </div>
        </td>
        <td width="180" align="left" valign="middle">
            <div style="font-size: 15pt; font-weight: bold; color: #000;">عرض سعر</div>
            <div style="font-size: 8.5pt; margin-top: 3px; color: #000;" dir="ltr" align="left">#{{ $data->quotationNumber() }}</div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="8" cellspacing="0" class="bordered" style="margin-bottom: 16px;">
    <tr>
        <td align="right" style="{{ $cell }}">
            <span class="bold">صلاحية العرض:</span>
            <span dir="ltr">حتى {{ $data->validUntilDate() }}</span>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px;">
    <tr>
        <td width="49%" valign="top">
            <div class="section-head">بيانات العرض</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="14"></td></tr></table>
            <table class="bordered">
                <tr>
                    <td width="42%" align="right" style="{{ $labelCell }}">رقم العرض</td>
                    <td width="58%" align="right" class="bold" dir="ltr" style="{{ $cell }}">{{ $data->quotationNumber() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">تاريخ الإصدار</td>
                    <td align="right" class="bold" dir="ltr" style="{{ $cell }}">{{ $data->issueDate() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">صالح حتى</td>
                    <td align="right" class="bold" dir="ltr" style="{{ $cell }}">{{ $data->validUntilDate() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">الحالة</td>
                    <td align="right" class="bold" style="{{ $cell }}">{{ $data->statusLabel() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">أُعد بواسطة</td>
                    <td align="right" class="bold" style="{{ $cell }}">{{ $data->preparedBy() }}</td>
                </tr>
            </table>
        </td>

        <td width="2%"></td>

        <td width="49%" valign="top">
            <div class="section-head">بيانات العميل</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="14"></td></tr></table>
            <table class="bordered">
                <tr>
                    <td width="42%" align="right" style="{{ $labelCell }}">اسم العميل</td>
                    <td width="58%" align="right" class="bold" style="{{ $cell }}">{{ $data->customerName() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">البريد الإلكتروني</td>
                    <td align="right" class="bold" dir="ltr" style="{{ $cell }}">{{ $data->customerEmail() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">رقم الجوال</td>
                    <td align="right" class="bold" dir="ltr" style="{{ $cell }}">{{ $data->customerPhone() }}</td>
                </tr>
                <tr>
                    <td align="right" valign="top" style="{{ $labelCell }}">العنوان</td>
                    <td align="right" class="bold" style="{{ $cell }}">{{ $data->customerAddress() ?? '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="section-head">تفاصيل البنود</div>
<table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="14"></td></tr></table>

<table class="bordered" style="margin-bottom: 16px;">
    <thead>
        <tr>
            <th width="6%" align="center">#</th>
            <th width="34%" align="right">المنتج / الخدمة</th>
            <th width="24%" align="right">الوصف</th>
            <th width="10%" align="center">الكمية</th>
            <th width="13%" align="right">سعر الوحدة</th>
            <th width="13%" align="right">الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data->lineItems() as $index => $item)
            <tr>
                <td align="center" valign="middle">{{ $index + 1 }}</td>
                <td align="right" valign="top" class="bold">{{ $item['name'] }}</td>
                <td align="right" valign="top" style="font-size: 8pt;">{{ $item['description'] ?? '—' }}</td>
                <td align="center" valign="middle">{{ $item['quantity'] }}</td>
                <td align="right" valign="middle" dir="ltr" class="bold">{{ $data->formatMoney($item['unit_price']) }}</td>
                <td align="right" valign="middle" dir="ltr" class="bold">{{ $data->formatMoney($item['total']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
    <tr>
        <td width="58%" valign="top">
            <div class="section-head">شروط العرض</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="14"></td></tr></table>
            <table class="bordered">
                <tr>
                    <td align="right" style="{{ $cell }} font-size: 8pt; line-height: 1.6;">
                        • الأسعار بالريال السعودي وتشمل ضريبة القيمة المضافة حيث ينطبق ذلك.<br>
                        • يُعتمد العرض خلال فترة الصلاحية المحددة أعلاه.
                    </td>
                </tr>
            </table>
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top">
            <div class="section-head">الملخص المالي</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="14"></td></tr></table>
            <table class="bordered">
                <tr>
                    <td align="right" style="{{ $labelCell }}">المجموع الفرعي</td>
                    <td width="42%" align="right" dir="ltr" class="bold" style="{{ $cell }}">{{ $data->formatMoney($data->subtotal()) }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">ضريبة القيمة المضافة (15%)</td>
                    <td align="right" dir="ltr" class="bold" style="{{ $cell }}">{{ $data->formatMoney($data->vatAmount()) }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }} font-size: 9pt;">الإجمالي</td>
                    <td align="right" dir="ltr" style="{{ $cell }} font-size: 9pt; font-weight: bold;">{{ $data->formatMoney($data->total()) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($data->notes())
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
    <tr>
        <td>
            <div class="section-head">ملاحظات</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="14"></td></tr></table>
            <table class="bordered">
                <tr>
                    <td align="right" style="{{ $cell }} font-size: 8pt; line-height: 1.55;">{{ $data->notes() }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endif

<div class="section-head">طرق الدفع</div>
<table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="14"></td></tr></table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
    <tr>
        <td width="58%" valign="top">
            <div class="section-head" style="font-size: 8pt;">الحساب البنكي</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="10"></td></tr></table>
            <table class="bordered">
                <tr>
                    <td width="34%" align="right" style="{{ $labelCell }}">البنك</td>
                    <td align="right" style="{{ $cell }}">{{ $data->bankName() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">اسم الحساب</td>
                    <td align="right" style="{{ $cell }}">{{ $data->companyLegalName() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">رقم الحساب</td>
                    <td align="right" dir="ltr" style="{{ $cell }}">{{ $data->bankAccountNumber() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">رقم الآيبان (IBAN)</td>
                    <td align="right" dir="ltr" style="{{ $cell }}">{{ $data->bankIban() }}</td>
                </tr>
            </table>
        </td>
        <td width="4%"></td>
        <td width="38%" valign="top">
            <div class="section-head" style="font-size: 8pt;">الدفع عن طريق</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="10"></td></tr></table>
            <table class="bordered">
                @foreach($data->paymentMethods() as $method)
                    <tr>
                        <td align="right" style="{{ $cell }}">• {{ $method }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
    <tr>
        <td width="49%" valign="top">
            <div class="section-head" style="font-size: 8pt;">الرقم الضريبي</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="10"></td></tr></table>
            <table class="bordered">
                <tr>
                    <td width="42%" align="right" style="{{ $labelCell }}">الرقم الضريبي</td>
                    <td align="right" dir="ltr" style="{{ $cell }}">{{ $data->vatNumber() }}</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">السجل التجاري</td>
                    <td align="right" dir="ltr" style="{{ $cell }}">{{ $data->commercialRegister() }}</td>
                </tr>
            </table>
        </td>
        <td width="2%"></td>
        <td width="49%" valign="top">
            <div class="section-head" style="font-size: 8pt;">بيانات التواصل</div>
            <table class="section-gap-table" cellpadding="0" cellspacing="0"><tr><td height="10"></td></tr></table>
            <table class="bordered">
                <tr>
                    <td width="42%" align="right" style="{{ $labelCell }}">الهاتف</td>
                    <td align="right" dir="ltr" style="{{ $cell }}">0114101840 - 0559668015</td>
                </tr>
                <tr>
                    <td align="right" style="{{ $labelCell }}">البريد الإلكتروني</td>
                    <td align="right" dir="ltr" style="{{ $cell }}">info@adventureksa.com</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="12" cellspacing="0" style="border-top: 1px solid #000; margin-top: 8px;">
    <tr>
        <td align="center">
            <div style="font-size: 9pt; font-weight: bold; color: #000; margin-bottom: 3px;">شكراً لاهتمامكم بعالم المغامرة!</div>
            <div class="muted" style="font-size: 7.5pt; margin-bottom: 5px;">يسعدنا تواصلكم لأي استفسار أو تعديل على هذا العرض.</div>
            <div style="font-size: 7.5pt; line-height: 1.6; color: #000;">
                <strong>عالم المغامرة للترفيه</strong><br>
                البريد: info@adventureksa.com
            </div>
            <div style="font-size: 7pt; color: #555; margin-top: 6px;">
                تم إنشاء عرض السعر آلياً بتاريخ <span dir="ltr">{{ $data->generatedAt() }}</span>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
