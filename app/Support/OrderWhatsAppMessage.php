<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

class OrderWhatsAppMessage
{
    public static function build(Order $order): string
    {
        $lines = [
            'طلب جديد — عالم المغامرة',
            'رقم الطلب: '.$order->order_number,
            'العميل: '.($order->customer_name ?: '—'),
            'الجوال: '.($order->customer_phone ?: '—'),
        ];

        if ($order->activity_date) {
            $lines[] = 'تاريخ الفعالية: '.self::formatActivityDate($order->activity_date);
        }

        $locationUrl = self::locationPageUrl($order);
        if ($locationUrl && ($order->address || self::locationMapsUrl($order->address))) {
            $lines[] = 'الموقع: '.$locationUrl;
        }

        $items = collect($order->items ?? []);
        if ($items->isNotEmpty()) {
            $lines[] = 'المنتجات:';
            foreach ($items as $item) {
                $name = $item['name'] ?? $item['product_name'] ?? 'منتج';
                $lines[] = '• '.$name;
            }
        }

        return implode("\n", $lines);
    }

    public static function locationPageUrl(Order $order): string
    {
        $slug = $order->location_slug ?: $order->order_number;

        return PublicAppUrl::to('/order/'.$slug.'/location');
    }

    public static function locationMapsUrl(?string $address): ?string
    {
        if (! $address || trim($address) === '') {
            return null;
        }

        $trimmed = trim($address);

        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/', $trimmed, $matches)) {
            return 'https://www.google.com/maps?q='.$matches[1].','.$matches[2];
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($trimmed);
    }

    private static function formatActivityDate(mixed $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format('Y-m-d');
    }
}
