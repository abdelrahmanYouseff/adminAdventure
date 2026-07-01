<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderWhatsAppMessage;
use Symfony\Component\HttpFoundation\Response;

class OrderLocationController extends Controller
{
    public function show(string $slug): Response
    {
        $orderModel = Order::query()
            ->where('location_slug', $slug)
            ->orWhere('order_number', $slug)
            ->firstOrFail();

        $mapsUrl = OrderWhatsAppMessage::locationMapsUrl($orderModel->address);

        if (! $mapsUrl) {
            abort(404, 'لا يوجد موقع مسجّل لهذا الطلب');
        }

        return redirect()->away($mapsUrl);
    }
}
