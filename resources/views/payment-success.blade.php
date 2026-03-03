<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تم الدفع بنجاح - عالم المغامرة</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 0; background: linear-gradient(to bottom right, #ecfdf5, #fff, #ccfbf1); min-height: 100vh; color: #111; }
        .wrap { max-width: 32rem; margin: 0 auto; padding: 2rem 1rem; }
        .card { background: rgba(255,255,255,0.9); border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.05); overflow: hidden; }
        .card-inner { padding: 2rem; text-align: center; }
        .icon { width: 4rem; height: 4rem; margin: 0 auto 1.5rem; border-radius: 50%; background: #d1fae5; display: flex; align-items: center; justify-content: center; }
        .icon svg { width: 2rem; height: 2rem; color: #059669; }
        h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; }
        .sub { color: #4b5563; margin-bottom: 1.5rem; }
        .details { text-align: right; border-radius: 1rem; border: 1px solid #e5e7eb; background: #f9fafb; padding: 1.5rem; margin-bottom: 1.5rem; }
        .details h2 { font-size: 1rem; font-weight: 600; margin: 0 0 1rem; }
        .row { display: flex; justify-content: space-between; gap: 0.75rem; font-size: 0.875rem; margin-bottom: 0.5rem; }
        .row .label { color: #6b7280; }
        .items { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; }
        .items ul { list-style: none; padding: 0; margin: 0; }
        .items li { display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .total { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; font-weight: 600; }
        .order-id { display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 1.5rem; }
        .order-id code { font-family: ui-monospace, monospace; font-weight: 600; }
        .btns { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.5rem; border-radius: 0.75rem; font-weight: 500; text-decoration: none; border: none; cursor: pointer; font-size: 1rem; }
        .btn-primary { background: #059669; color: white; }
        .btn-primary:hover { background: #047857; }
        .btn-outline { background: white; color: #059669; border: 2px solid #059669; }
        .btn-outline:hover { background: #ecfdf5; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="card-inner">
                <div class="icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1>تم الدفع بنجاح</h1>
                <p class="sub">{{ $processed ? 'تم تأكيد دفعتك وإنشاء الطلب والفاتورة.' : 'تم استلام إشعار الدفع. إذا لم يظهر الطلب، تواصل مع الدعم.' }}</p>

                @if($order)
                    <div class="details">
                        <h2>تفاصيل الطلب</h2>
                        <div class="row">
                            <span class="label">رقم الطلب</span>
                            <span><strong>{{ $order['order_number'] }}</strong></span>
                        </div>
                        <div class="row">
                            <span class="label">الاسم</span>
                            <span>{{ $order['customer_name'] }}</span>
                        </div>
                        @if(!empty($order['customer_phone']))
                        <div class="row">
                            <span class="label">رقم الهاتف</span>
                            <a href="tel:{{ $order['customer_phone'] }}">{{ $order['customer_phone'] }}</a>
                        </div>
                        @endif
                        @if(!empty($order['address']))
                        <div class="row">
                            <span class="label">العنوان</span>
                            <span>{{ $order['address'] }}</span>
                        </div>
                        @endif
                        @if(!empty($order['activity_date']))
                        <div class="row">
                            <span class="label">تاريخ الفعالية</span>
                            <span>{{ $order['activity_date'] }}</span>
                        </div>
                        @endif
                        @if(!empty($order['items']))
                        <div class="items">
                            <p class="label" style="margin-bottom:0.5rem">المنتجات</p>
                            <ul>
                                @foreach($order['items'] as $item)
                                <li>
                                    <span>{{ $item['name'] ?? '' }} × {{ $item['quantity'] ?? 1 }}</span>
                                    <span>{{ number_format((float)($item['amount'] ?? ($item['price'] ?? 0) * ($item['quantity'] ?? 1)), 2) }} {{ $order['currency'] ?? 'SAR' }}</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                        <div class="total">
                            <span>الإجمالي</span>
                            <span>{{ number_format((float)($order['total_amount'] ?? 0), 2) }} {{ $order['currency'] ?? 'SAR' }}</span>
                        </div>
                    </div>
                @elseif($order_id)
                    <div class="order-id">
                        <span class="label">رقم الطلب:</span>
                        <code>{{ $order_id }}</code>
                    </div>
                @endif

                <div class="btns">
                    <a href="/store" class="btn btn-primary">العودة للمتجر</a>
                    @if(!empty($whatsapp_number))
                    @php
                        $waText = $order_id ? 'مرحباً، أنا عميل. رقم طلبي: ' . $order_id . ' - أود الاستفسار عن الطلب.' : 'مرحباً، أود الاستفسار.';
                    @endphp
                    <a href="https://wa.me/{{ $whatsapp_number }}?text={{ urlencode($waText) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline">تواصل واتساب</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
