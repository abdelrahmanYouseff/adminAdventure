<?php

return [
    // تخطي OTP تماماً: تسجيل دخول مباشرة برقم الجوال
    'skip_enabled' => filter_var(env('OTP_SKIP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // يخلي أي OTP = 0000 (للاختبار فقط)
    'force_fixed' => filter_var(env('OTP_FORCE_FIXED', false), FILTER_VALIDATE_BOOLEAN),
];

