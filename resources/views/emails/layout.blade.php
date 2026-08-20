<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;background:@yield('accent', '#2563eb');color:#ffffff;">
                            <p style="margin:0;font-size:18px;font-weight:700;">{{ config('mail.from.name') }}</p>
                            <p style="margin:6px 0 0;font-size:13px;opacity:0.95;">@yield('subtitle')</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                            <p style="margin:0 0 6px;font-size:12px;line-height:1.7;color:#64748b;">
                                رسالة نظامية من {{ config('mail.from.name') }}.
                            </p>
                            @if (config('mail.reply_to.address') && config('mail.reply_to.address') !== config('mail.from.address'))
                                <p style="margin:0;font-size:12px;line-height:1.7;color:#64748b;">
                                    للاستفسار:
                                    <a href="mailto:{{ config('mail.reply_to.address') }}" style="color:#2563eb;text-decoration:none;">
                                        {{ config('mail.reply_to.address') }}
                                    </a>
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
