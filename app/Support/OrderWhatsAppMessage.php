<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

class OrderWhatsAppMessage
{
    public static function build(Order $order): string
    {
        $separator = '────────────────────';

        $lines = [
            'طلب جديد — عالم المغامرة',
            $separator,
            '',
            'رقم الطلب',
            $order->order_number,
            '',
            'العميل',
            $order->customer_name ?: '—',
            '',
            'الجوال',
            $order->customer_phone ?: '—',
        ];

        if ($order->activity_date) {
            $lines[] = '';
            $lines[] = 'تاريخ الفعالية';
            $lines[] = self::formatActivityDate($order->activity_date);
        }

        $locationUrl = self::locationPageUrl($order->order_number);
        if ($locationUrl && ($order->address || self::locationMapsUrl($order->address))) {
            $lines[] = '';
            $lines[] = 'الموقع';
            $lines[] = $locationUrl;
        }

        $items = collect($order->items ?? []);
        if ($items->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'المنتجات';
            foreach ($items as $item) {
                $name = $item['name'] ?? $item['product_name'] ?? 'منتج';
                $lines[] = '• '.$name;
            }
        }

        $lines[] = '';
        $lines[] = $separator;

        return implode("\n", $lines);
    }

    public static function locationPageUrl(string $orderNumber): string
    {
        return route('store.order.location', ['order' => $orderNumber], absolute: true);
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
