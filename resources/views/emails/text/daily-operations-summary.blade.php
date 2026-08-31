الملخص اليومي — {{ $dateLabel }}

طلبات جديدة: {{ $ordersCount }}
عمليات تركيب: {{ $installationsCount }}
عمليات فك: {{ $dismantlingCount }}

الطلبات التي أُنشئت اليوم:
@forelse ($orders as $order)
- {{ $order['order_number'] }} — {{ $order['customer_name'] }}
@empty
- لا توجد طلبات جديدة اليوم.
@endforelse
