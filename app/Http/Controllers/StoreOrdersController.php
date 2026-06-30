<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreOrdersController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $phoneVariants = $this->phoneVariants($user->phone);

        $query = Order::query()
            ->with(['products'])
            ->where(function ($q) use ($user, $phoneVariants) {
                $q->where('user_id', $user->id);

                if ($user->email) {
                    $q->orWhere('customer_email', $user->email);
                }

                if ($phoneVariants !== []) {
                    $q->orWhereIn('customer_phone', $phoneVariants);
                }
            });

        $paymentStatus = $request->string('payment_status')->toString();
        if ($paymentStatus !== '' && $paymentStatus !== 'all') {
            $query->where('payment_status', $paymentStatus);
        }

        $orders = $query
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => (float) $order->total_amount,
                'currency' => $order->currency,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'activity_date' => $order->activity_date?->format('Y-m-d'),
                'created_at' => $order->created_at?->format('Y-m-d'),
                'items_count' => max(count($order->items ?? []), $order->products->count()),
                'product_names' => $order->products->pluck('product_name')->take(3)->values()->all(),
            ]);

        return Inertia::render('Store/Orders', [
            'orders' => $orders,
            'filters' => [
                'payment_status' => $paymentStatus !== '' ? $paymentStatus : 'all',
            ],
        ]);
    }

    /** @return list<string> */
    private function phoneVariants(?string $phone): array
    {
        if (! $phone) {
            return [];
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return [];
        }

        $normalized = $digits;
        if (str_starts_with($normalized, '966')) {
            $normalized = substr($normalized, 3);
        }
        if (str_starts_with($normalized, '0')) {
            $normalized = substr($normalized, 1);
        }

        return array_values(array_unique(array_filter([
            $phone,
            $digits,
            $normalized,
            '0'.$normalized,
            '+966'.$normalized,
            '966'.$normalized,
        ])));
    }
}
