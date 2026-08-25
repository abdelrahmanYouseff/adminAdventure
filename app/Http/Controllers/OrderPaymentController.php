<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OrderPaymentController extends Controller
{
    public function pay(Request $request, string $token): RedirectResponse|View
    {
        $token = preg_replace('/[^A-Za-z0-9]/', '', $token) ?? '';

        $order = $token !== ''
            ? Order::query()->where('payment_token', $token)->first()
            : null;

        if (! $order) {
            return view('order-pay-status', [
                'order' => null,
                'message' => 'رابط الدفع غير صالح أو منتهي.',
                'state' => 'unavailable',
                'due' => 0,
                'total' => 0,
            ]);
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return $this->statusView($order, 'هذا الطلب لم يعد متاحاً للدفع.', 'unavailable');
        }

        $chargeAmount = $this->availableChargeAmount($order);
        if ($chargeAmount <= 0.009) {
            $hasPending = $order->paymentReceipts()
                ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
                ->exists();

            return $this->statusView(
                $order,
                $hasPending
                    ? 'يوجد مبلغ مسجّل بانتظار الاعتماد. لا يمكن فتح دفع جديد حالياً.'
                    : 'تم سداد هذا الطلب بالكامل. شكراً لك.',
                $hasPending ? 'pending' : 'paid'
            );
        }

        try {
            $order->forceFill(['payment_method' => 'noon'])->save();

            $sessionResponse = app(PaymentController::class)->createNoonSession([
                'user_id' => $order->user_id,
                'amount' => $chargeAmount,
                'currency' => $order->currency ?: 'SAR',
                'order_id' => $order->order_number,
                'customer_email' => $order->customer_email ?: 'info@adventureksa.com',
                'customer_name' => $order->customer_name ?: 'Customer',
                'customer_phone' => $order->customer_phone,
                'description' => 'دفع الطلب '.$order->order_number,
                'from_app' => false,
                'ip_address' => $request->ip(),
                'source' => 'order_payment_link',
            ]);

            $payload = json_decode($sessionResponse->getContent(), true);
            $checkoutUrl = $payload['data']['checkout_url'] ?? null;

            if (! ($payload['success'] ?? false) || ! $checkoutUrl) {
                Log::error('Order Noon session failed', [
                    'order_id' => $order->id,
                    'response' => $payload,
                ]);

                return $this->statusView(
                    $order,
                    $payload['message'] ?? 'تعذر إنشاء جلسة الدفع. حاول مرة أخرى لاحقاً.',
                    'error'
                );
            }

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('Order payment link failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return $this->statusView(
                $order,
                config('app.debug') ? $e->getMessage() : 'حدث خطأ أثناء تجهيز رابط الدفع.',
                'error'
            );
        }
    }

    private function availableChargeAmount(Order $order): float
    {
        $pending = round((float) $order->paymentReceipts()
            ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
            ->sum('amount'), 2);
        $committed = round((float) ($order->amount_paid ?? 0) + $pending, 2);

        return round(max(0, (float) $order->total_amount - $committed), 2);
    }

    private function statusView(Order $order, string $message, string $state): View
    {
        return view('order-pay-status', [
            'order' => $order,
            'message' => $message,
            'state' => $state,
            'due' => $this->availableChargeAmount($order),
            'total' => round((float) $order->total_amount, 2),
        ]);
    }
}
