<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دفع عرض السعر {{ $quotation->quotation_number }}</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f6f8;
            color: #1a1a1a;
            margin: 0;
            padding: 24px;
        }
        .card {
            max-width: 480px;
            margin: 48px auto;
            background: #fff;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 8px 30px rgba(0,0,0,.06);
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .badge-paid { background: #ecfdf5; color: #047857; }
        .badge-pending { background: #fffbeb; color: #b45309; }
        .badge-error, .badge-unavailable { background: #fef2f2; color: #b91c1c; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { line-height: 1.7; color: #444; }
        .meta { margin-top: 18px; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge badge-{{ $state }}">
            @if($state === 'paid') تم السداد
            @elseif($state === 'pending') بانتظار الاعتماد
            @elseif($state === 'unavailable') غير متاح
            @else تعذر الدفع
            @endif
        </div>
        <h1>عرض السعر {{ $quotation->quotation_number }}</h1>
        <p>{{ $message }}</p>
        <div class="meta">
            الإجمالي: {{ number_format($total, 2) }} ر.س
            @if($due > 0)
                <br>المستحق: {{ number_format($due, 2) }} ر.س
            @endif
        </div>
    </div>
</body>
</html>
