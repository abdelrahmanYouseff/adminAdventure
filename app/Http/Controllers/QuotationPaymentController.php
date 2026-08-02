<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\Quotation;
use App\Services\QuotationToOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class QuotationPaymentController extends Controller
{
    public function pay(Request $request, string $token): RedirectResponse|View
    {
        $quotation = Quotation::query()
            ->where('payment_token', $token)
            ->with('items')
            ->firstOrFail();

        if ($quotation->status === 'rejected' || $quotation->status === 'expired') {
            return $this->statusView($quotation, 'هذا العرض لم يعد متاحاً للدفع.', 'unavailable');
        }

        $due = $quotation->amountDue();
        if ($due <= 0.009) {
            return $this->statusView($quotation, 'تم سداد هذا العرض بالكامل. شكراً لك.', 'paid');
        }

        if ($quotation->items->isEmpty()) {
            return $this->statusView($quotation, 'لا يمكن إتمام الدفع — عرض السعر بدون بنود.', 'error');
        }

        try {
            $orderService = app(QuotationToOrderService::class);

            // Mirror any staff-recorded amount_paid into pending receipts first.
            $orderService->syncPaymentFromQuotation($quotation);
            $order = $orderService->ensureOrder($quotation->fresh());

            $order->payment_method = 'noon';
            $order->save();

            $chargeAmount = $this->availableChargeAmount($order);
            if ($chargeAmount <= 0.009) {
                return $this->statusView(
                    $quotation,
                    'يوجد مبلغ مسجّل بانتظار اعتماد المحاسب. لا يمكن فتح دفع جديد حالياً.',
                    'pending'
                );
            }

            $chargeAmount = min($chargeAmount, $due);

            $customerEmail = $quotation->customer_email
                ?: ($order->customer_email ?: 'info@adventureksa.com');
            $customerName = $quotation->customer_name ?: ($order->customer_name ?: 'Customer');
            $customerPhone = $quotation->customer_phone ?: $order->customer_phone;

            $sessionResponse = app(PaymentController::class)->createNoonSession([
                'user_id' => $order->user_id,
                'amount' => $chargeAmount,
                'currency' => $order->currency ?: 'SAR',
                'order_id' => $order->order_number,
                'customer_email' => $customerEmail,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'description' => 'دفع عرض سعر '.$quotation->quotation_number,
                'from_app' => false,
                'ip_address' => $request->ip(),
                'quotation_id' => $quotation->id,
                'source' => 'quotation_pdf',
            ]);

            $payload = json_decode($sessionResponse->getContent(), true);
            $checkoutUrl = $payload['data']['checkout_url'] ?? null;

            if (! ($payload['success'] ?? false) || ! $checkoutUrl) {
                Log::error('Quotation Noon session failed', [
                    'quotation_id' => $quotation->id,
                    'response' => $payload,
                ]);

                return $this->statusView(
                    $quotation,
                    $payload['message'] ?? 'تعذر إنشاء جلسة الدفع عبر Noon. حاول مرة أخرى لاحقاً.',
                    'error'
                );
            }

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('Quotation payment link failed', [
                'quotation_id' => $quotation->id,
                'message' => $e->getMessage(),
            ]);

            return $this->statusView(
                $quotation,
                config('app.debug') ? $e->getMessage() : 'حدث خطأ أثناء تجهيز رابط الدفع.',
                'error'
            );
        }
    }

    private function availableChargeAmount(Order $order): float
    {
        $pendingSum = round((float) $order->paymentReceipts()
            ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
            ->sum('amount'), 2);
        $committed = round((float) ($order->amount_paid ?? 0) + $pendingSum, 2);

        return round(max(0, (float) $order->total_amount - $committed), 2);
    }

    private function statusView(Quotation $quotation, string $message, string $state): View
    {
        return view('quotation-pay-status', [
            'quotation' => $quotation,
            'message' => $message,
            'state' => $state,
            'due' => $quotation->amountDue(),
            'total' => round((float) $quotation->total_amount, 2),
        ]);
    }
}
