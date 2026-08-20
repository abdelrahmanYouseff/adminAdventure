<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اختبار البريد</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;background:#ea580c;color:#ffffff;">
                            <p style="margin:0;font-size:18px;font-weight:700;">{{ config('app.name') }}</p>
                            <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">اختبار إرسال البريد عبر Resend</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.7;">
                                تم استلام هذه الرسالة بنجاح. إعدادات البريد في النظام تعمل بشكل صحيح.
                            </p>
                            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.6;" dir="ltr">
                                Sent at: {{ $sentAt }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
