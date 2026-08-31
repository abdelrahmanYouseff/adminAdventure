@extends('emails.layout')

@section('title', 'الملخص اليومي')
@section('preheader', 'ملخص عمليات اليوم: طلبات وتركيب وفك')
@section('subtitle', 'ملخص نهاية اليوم — {{ $dateLabel }}')
@section('accent', '#0f766e')

@section('content')
    <p style="margin:0 0 20px;font-size:15px;line-height:1.8;">
        ملخص سريع لنشاط المنصة خلال يوم <strong>{{ $dateLabel }}</strong>.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;">
        <tr>
            <td width="33%" style="padding:0 4px 8px 0;vertical-align:top;">
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 12px;text-align:center;">
                    <p style="margin:0 0 6px;font-size:12px;color:#1d4ed8;">طلبات جديدة</p>
                    <p style="margin:0;font-size:28px;font-weight:800;color:#1e3a8a;">{{ $ordersCount }}</p>
                </div>
            </td>
            <td width="33%" style="padding:0 4px 8px;vertical-align:top;">
                <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:14px 12px;text-align:center;">
                    <p style="margin:0 0 6px;font-size:12px;color:#047857;">عمليات تركيب</p>
                    <p style="margin:0;font-size:28px;font-weight:800;color:#065f46;">{{ $installationsCount }}</p>
                </div>
            </td>
            <td width="33%" style="padding:0 0 8px 4px;vertical-align:top;">
                <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 12px;text-align:center;">
                    <p style="margin:0 0 6px;font-size:12px;color:#c2410c;">عمليات فك</p>
                    <p style="margin:0;font-size:28px;font-weight:800;color:#9a3412;">{{ $dismantlingCount }}</p>
                </div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px;font-size:14px;font-weight:700;">الطلبات التي أُنشئت اليوم</p>
    @if (count($orders) > 0)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
            @foreach ($orders as $order)
                <tr>
                    <td style="padding:12px 14px;border-bottom:1px solid #f1f5f9;font-size:14px;">
                        <strong dir="ltr">{{ $order['order_number'] }}</strong>
                        <span style="color:#64748b;"> — {{ $order['customer_name'] }}</span>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="margin:0;font-size:14px;color:#94a3b8;">لا توجد طلبات جديدة اليوم.</p>
    @endif
@endsection
