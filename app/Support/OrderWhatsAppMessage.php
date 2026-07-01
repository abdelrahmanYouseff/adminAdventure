<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

class OrderWhatsAppMessage
{
    public static function build(Order $order): string
    {
        $lines = [
            'طلب جديد - عالم المغامرة',
            'رقم الطلب: '.$order->order_number,
            'العميل: '.($order->customer_name ?: '—'),
            'الجوال: '.($order->customer_phone ?: '—'),
        ];

        if ($order->activity_date) {
            $lines[] = 'تاريخ الفعالية: '.self::formatActivityDate($order->activity_date);
        }

        $mapsUrl = self::locationMapsUrl($order->address);
        if ($mapsUrl) {
            $lines[] = 'الموقع: '.$mapsUrl;
        } elseif ($order->address) {
            $lines[] = 'الموقع: '.$order->address;
        }

        $items = collect($order->items ?? []);
        if ($items->isNotEmpty()) {
            $lines[] = 'المنتجات:';
            foreach ($items as $item) {
                $name = $item['name'] ?? $item['product_name'] ?? 'منتج';
                $quantity = (int) ($item['quantity'] ?? 1);
                $duration = (int) ($item['duration'] ?? 1);
                $amount = (float) ($item['amount'] ?? (($item['price'] ?? 0) * $quantity * max(1, $duration)));
                $lines[] = sprintf(
                    '• %s × %d (%d يوم) — %s ريال',
                    $name,
                    $quantity,
                    $duration,
                    number_format($amount, 2)
                );
            }
        }

        $currency = $order->currency ?? 'SAR';
        $currencyLabel = $currency === 'SAR' ? 'ريال' : $currency;
        $lines[] = sprintf(
            'الإجمالي: %s %s',
            number_format((float) $order->total_amount, 2),
            $currencyLabel
        );

        return implode("\n", $lines);
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

        return $carbon->locale('ar')->translatedFormat('j F Y');
    }
}
