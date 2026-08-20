<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تم التركيب</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;background:#0284c7;color:#ffffff;">
                            <p style="margin:0;font-size:18px;font-weight:700;">{{ config('app.name') }}</p>
                            <p style="margin:6px 0 0;font-size:13px;opacity:0.95;">إشعار اكتمال التركيب</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
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

                            <p style="margin:0 0 12px;font-size:14px;font-weight:700;">صور التركيب ({{ count($photos) }})</p>

                            @foreach ($photos as $photo)
                                <div style="margin:0 0 16px;padding:12px;border:1px solid #e2e8f0;border-radius:12px;">
                                    <p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#334155;">
                                        {{ $photo['product_name'] }}
                                    </p>
                                    @if (!empty($photo['photo_url']))
                                        <a href="{{ $photo['photo_url'] }}" target="_blank" rel="noopener noreferrer">
                                            <img
                                                src="{{ $photo['photo_url'] }}"
                                                alt="{{ $photo['product_name'] }}"
                                                style="display:block;width:100%;max-width:100%;height:auto;border-radius:8px;"
                                            >
                                        </a>
                                    @else
                                        <p style="margin:0;font-size:12px;color:#94a3b8;">الصورة مرفقة مع الرسالة.</p>
                                    @endif
                                </div>
                            @endforeach

                            <p style="margin:8px 0 0;font-size:12px;color:#64748b;line-height:1.6;">
                                الصور مرفقة أيضاً كملفات في هذه الرسالة للمراجعة بدون فتح الرابط.
                            </p>

                            <p style="margin:22px 0 0;">
                                <a
                                    href="{{ $workOrderUrl }}"
                                    style="display:inline-block;background:#0284c7;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 18px;border-radius:10px;"
                                >
                                    فتح أمر العمل
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
