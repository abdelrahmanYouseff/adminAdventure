@extends('emails.layout')

@section('title', 'أمر عمل جديد')
@section('preheader', 'أمر عمل جديد — يرجى الدخول للمنصة لتعيين العمال')
@section('subtitle', 'إشعار أمر عمل جديد')
@section('accent', '#2563eb')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.8;">
        تم إصدار <strong>أمر عمل جديد</strong> ويحتاج تعيين عمال للتركيب.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 8px;font-size:13px;color:#1d4ed8;">اسم العميل / الشركة</p>
                <p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#1e3a8a;">{{ $customerName }}</p>

                <p style="margin:0 0 4px;font-size:13px;color:#1d4ed8;">رقم الهاتف</p>
                <p style="margin:0 0 14px;font-size:15px;font-weight:700;" dir="ltr">
                    {{ $customerPhone ?: '—' }}
                </p>

                <p style="margin:0 0 4px;font-size:13px;color:#1d4ed8;">رقم الطلب</p>
                <p style="margin:0;font-size:15px;font-weight:700;letter-spacing:0.02em;" dir="ltr">{{ $orderNumber }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px;font-size:14px;font-weight:700;">الألعاب / المنتجات المطلوب تركيبها</p>
    <ul style="margin:0 0 20px;padding:0 18px 0 0;font-size:14px;line-height:1.9;color:#334155;">
        @forelse ($products as $product)
            <li style="margin:0 0 4px;">{{ $product }}</li>
        @empty
            <li style="margin:0;color:#94a3b8;">لا توجد منتجات مسجلة.</li>
        @endforelse
    </ul>

    <p style="margin:0 0 18px;font-size:14px;line-height:1.8;color:#0f172a;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;">
        يرجى الدخول للمنصة لتعيين العمال على أمر العمل.
    </p>

    <p style="margin:0;">
        <a
            href="{{ $assignWorkersUrl }}"
            style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 18px;border-radius:10px;"
        >
            الدخول لأوامر العمل
        </a>
    </p>
@endsection
