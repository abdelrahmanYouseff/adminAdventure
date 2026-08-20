@extends('emails.layout')

@section('title', 'تم التركيب')
@section('preheader')
    تم رفع صور التركيب — {{ $customerName }}
@endsection
@section('subtitle', 'إشعار اكتمال التركيب')
@section('accent', '#0284c7')

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.8;">
        قام العامل <strong>{{ $workerName }}</strong> برفع صور التركيب للطلب التالي:
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 8px;font-size:13px;color:#075985;">اسم الفعالية / الشركة</p>
                <p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#075985;">{{ $customerName }}</p>
                <p style="margin:0 0 4px;font-size:13px;color:#075985;">رقم الطلب</p>
                <p style="margin:0;font-size:15px;font-weight:700;letter-spacing:0.02em;" dir="ltr">{{ $orderNumber }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px;font-size:14px;font-weight:700;">صور التركيب ({{ count($photos) }})</p>
    <ul style="margin:0 0 16px;padding:0 18px 0 0;font-size:14px;line-height:1.9;color:#334155;">
        @foreach ($photos as $photo)
            <li style="margin:0 0 4px;">{{ $photo['product_name'] }}</li>
        @endforeach
    </ul>

    <p style="margin:0 0 18px;font-size:13px;line-height:1.7;color:#64748b;">
        الصور مرفقة مع هذه الرسالة كملفات. يمكنك أيضاً مراجعتها من المنصة عبر الرابط أدناه.
    </p>

    <p style="margin:0;">
        <a
            href="{{ $workOrderUrl }}"
            style="display:inline-block;background:#0284c7;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 18px;border-radius:10px;"
        >
            فتح أمر العمل
        </a>
    </p>
@endsection
