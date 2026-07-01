<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderWhatsAppMessage;
use Symfony\Component\HttpFoundation\Response;

class OrderLocationController extends Controller
{
    public function show(string $order): Response
    {
        $orderModel = Order::query()
            ->where('order_number', $order)
            ->firstOrFail();

        $mapsUrl = OrderWhatsAppMessage::locationMapsUrl($orderModel->address);

        if (! $mapsUrl) {
            abort(404, 'لا يوجد موقع مسجّل لهذا الطلب');
        }

        return redirect()->away($mapsUrl);
    }
}
