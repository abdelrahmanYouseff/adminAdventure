{{ config('mail.from.name') }}
إشعار أمر عمل جديد

تم إصدار أمر عمل جديد ويحتاج تعيين عمال للتركيب.

اسم العميل / الشركة: {{ $customerName }}
رقم الهاتف: {{ $customerPhone ?: '—' }}
رقم الطلب: {{ $orderNumber }}

الألعاب / المنتجات المطلوب تركيبها:
@forelse ($products as $product)
- {{ $product }}
@empty
- لا توجد منتجات مسجلة.
@endforelse

يرجى الدخول للمنصة لتعيين العمال على أمر العمل.

رابط أوامر العمل:
{{ $assignWorkersUrl }}
