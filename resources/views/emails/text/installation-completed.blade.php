{{ config('mail.from.name') }}
إشعار اكتمال التركيب

قام العامل {{ $workerName }} برفع صور التركيب للطلب التالي:

اسم الفعالية / الشركة: {{ $customerName }}
رقم الطلب: {{ $orderNumber }}

صور التركيب ({{ count($photos) }}):
@foreach ($photos as $photo)
- {{ $photo['product_name'] }}
@endforeach

الصور مرفقة مع هذه الرسالة كملفات.

رابط أمر العمل:
{{ $workOrderUrl }}
