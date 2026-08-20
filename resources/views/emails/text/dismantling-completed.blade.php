{{ config('mail.from.name') }}
إشعار اكتمال الفك

قام العامل {{ $workerName }} برفع صور الفك للطلب التالي:

اسم الفعالية / الشركة: {{ $customerName }}
رقم الطلب: {{ $orderNumber }}

صور الفك ({{ count($photos) }}):
@foreach ($photos as $photo)
- {{ $photo['product_name'] }}
@endforeach

الصور مرفقة مع هذه الرسالة كملفات.

رابط تفاصيل الاسترجاع:
{{ $returnsUrl }}
