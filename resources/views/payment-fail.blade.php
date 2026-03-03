<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فشل عملية الدفع - عالم المغامرة</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 0; background: linear-gradient(to bottom right, #fef2f2, #fff, #ffe4e6); min-height: 100vh; color: #111; }
        .wrap { max-width: 32rem; margin: 0 auto; padding: 2rem 1rem; }
        .card { background: rgba(255,255,255,0.9); border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); overflow: hidden; }
        .card-inner { padding: 2rem; text-align: center; }
        .icon { width: 4rem; height: 4rem; margin: 0 auto 1.5rem; border-radius: 50%; background: #fee2e2; display: flex; align-items: center; justify-content: center; }
        .icon svg { width: 2rem; height: 2rem; color: #dc2626; }
        h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; }
        .sub { color: #4b5563; margin-bottom: 1rem; }
        .ref { font-size: 0.875rem; color: #6b7280; font-family: ui-monospace, monospace; margin-bottom: 1.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.5rem; border-radius: 0.75rem; font-weight: 500; text-decoration: none; background: #dc2626; color: white; }
        .btn:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="card-inner">
                <div class="icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h1>فشلت عملية الدفع</h1>
                <p class="sub">لم تتم عملية الدفع. لم يتم خصم أي مبلغ من حسابك. يمكنك إعادة المحاولة أو العودة للصفحة الرئيسية.</p>
                @if($order_id)
                <p class="ref">المرجع: {{ $order_id }}</p>
                @endif
                <a href="/" class="btn">العودة للصفحة الرئيسية</a>
            </div>
        </div>
    </div>
</body>
</html>
