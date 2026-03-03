<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class PaymentController extends Controller
{
    /**
     * Create payment session with Noon (without creating invoice)
     */
    public function createPaymentSession(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:SAR,USD,AED,KWD,QAR,BHD,OMR,JOD,EGP',
            'order_id' => 'required|string|unique:invoices,invoice_number',
            'description' => 'nullable|string|max:255',
            'customer_email' => 'required|email',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        try {
            $noon = config('services.noon', []);
            $noonApiUrl = rtrim($noon['api_url'] ?? 'https://api-test.sa.noonpayments.com/payment/v1/', '/') . '/';
            $baseUrl = rtrim(config('app.url', env('APP_URL', '')), '/');

            if (empty($noon['api_key']) || empty($noon['business_id']) || empty($noon['app_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noon API configuration incomplete',
                ], 500);
            }

            // Noon auth: "Key " + base64(BusinessId.AppId:ApiKey) OR "Key_" + base64 (normalized to "Key ")
            $authHeader = $noon['auth_header'] ?? null;
            if ($authHeader !== null && $authHeader !== '') {
                $authHeader = preg_replace('/^Key_/', 'Key ', trim((string) $authHeader));
            } else {
                $authHeader = 'Key ' . base64_encode($noon['business_id'] . '.' . $noon['app_id'] . ':' . $noon['api_key']);
            }

            $returnUrl = $baseUrl . '/payment/success?order_id=' . $request->order_id;
            $configuration = [
                'returnUrl' => $returnUrl,
                'paymentAction' => $noon['payment_action'] ?? 'SALE',
            ];
            if ($noon['cancel_url_enabled'] ?? true) {
                $configuration['cancelUrl'] = $baseUrl . '/payment/fail?order_id=' . $request->order_id;
            }

            $orderPayload = [
                'amount' => (float) $request->amount,
                'currency' => $request->currency,
                'reference' => $request->order_id,
                'name' => $request->description ?? 'Order from Adventure World',
                'category' => $noon['order_category'] ?? 'pay',
            ];
            if (! empty($noon['order_channel'])) {
                $orderPayload['channel'] = $noon['order_channel'];
            }

            $paymentData = [
                'apiOperation' => 'INITIATE',
                'order' => $orderPayload,
                'configuration' => $configuration,
            ];

            Log::info('Noon API Request', [
                'url' => $noonApiUrl . 'order',
                'returnUrl' => $returnUrl,
                'data' => $paymentData,
            ]);

            $headers = [
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-api-key' => $noon['api_key'],
            ];

            $response = Http::withHeaders($headers)->post($noonApiUrl . 'order', $paymentData);

            if (! $response->successful() && $response->status() === 403 && ! empty($configuration['cancelUrl'])) {
                unset($configuration['cancelUrl']);
                $paymentData['configuration'] = $configuration;
                Log::info('Noon API Retry without cancelUrl');
                $response = Http::withHeaders($headers)->post($noonApiUrl . 'order', $paymentData);
            }

            if ($response->successful()) {
                $paymentResponse = $response->json();

                // احصل على رابط التشيك أوت الصحيح من Noon
                $checkoutUrl = $paymentResponse['result']['checkoutData']['postUrl'] ?? null;

                // Store payment session data in cache (بدون تغيير)
                $paymentSessionData = [
                    'user_id' => $request->user_id,
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'order_id' => $request->order_id,
                    'description' => $request->description,
                    'customer_email' => $request->customer_email,
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'noon_order_id' => $paymentResponse['order']['id'] ?? null,
                    'created_at' => now(),
                ];
                Cache::put('payment_session_' . $request->order_id, $paymentSessionData, 3600);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment session created successfully',
                    'data' => [
                        'checkout_url' => $checkoutUrl,
                        'payment_id' => $paymentResponse['result']['order']['id'] ?? null,
                        'order_id' => $request->order_id,
                        'status' => $paymentResponse['result']['order']['status'] ?? null,
                        'noon_response' => $paymentResponse, // للتحقق من الـ response الكامل
                    ],
                ], 201);
            } else {
                $errorBody = $response->json() ?? [];
                $status = $response->status();
                $resultCode = $errorBody['resultCode'] ?? $errorBody['result']['resultCode'] ?? null;
                $message = $errorBody['message'] ?? $errorBody['result']['message'] ?? $errorBody['errors'][0]['message'] ?? null;

                Log::error('Noon API Error', [
                    'status' => $status,
                    'resultCode' => $resultCode,
                    'message' => $message,
                    'response' => $errorBody,
                    'returnUrl' => $returnUrl,
                ]);

                $userMessage = 'فشل في إنشاء جلسة الدفع.';
                if ($status === 403 && (int) $resultCode === 5019) {
                    $userMessage = 'رفض إنشاء الجلسة (403/5019). تحقق من بيانات الدخول لنون، وأن رابط العودة مسموح في لوحة نون (Allowed Return URLs)، واستخدم بيئة الاختبار أو الحية المناسبة.';
                } elseif ($message) {
                    $userMessage .= ' ' . (is_string($message) ? $message : json_encode($message));
                }

                return response()->json([
                    'success' => false,
                    'message' => $userMessage,
                    'resultCode' => $resultCode,
                    'error' => $errorBody,
                ], $status);
            }

        } catch (\Throwable $e) {
            Log::error('Payment Session Creation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'فشل في إنشاء جلسة الدفع. حاول مرة أخرى.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Process payment success: create/update order and invoice from cache, clear cache.
     * If an order already exists with order_number = orderId (e.g. from store checkout), update it and create invoice only.
     * Idempotent: if session cache is already cleared (e.g. webhook already processed), but invoice exists and is paid,
     * returns processed true so the app does not run confirmation twice.
     * Returns ['processed' => bool, 'order_id' => ?, 'payment_id' => ?].
     */
    protected function processPaymentSuccess(?string $orderId, ?string $paymentId): array
    {
        if (! $orderId) {
            return ['processed' => false, 'order_id' => null, 'payment_id' => $paymentId];
        }

        $paymentSessionData = Cache::get('payment_session_' . $orderId);

        // Already processed (e.g. by webhook): invoice exists and is paid – return success so app does not retry
        if (! $paymentSessionData) {
            $existingInvoice = Invoice::where('invoice_number', $orderId)->where('status', 'paid')->first();
            if ($existingInvoice) {
                $order = Order::where('order_number', $orderId)
                    ->orWhereHas('invoice', fn ($q) => $q->where('invoice_number', $orderId))
                    ->first();
                return [
                    'processed' => true,
                    'order_id' => $orderId,
                    'payment_id' => $order?->payment_id ?? $paymentId,
                ];
            }
            return ['processed' => false, 'order_id' => $orderId, 'payment_id' => $paymentId];
        }

        $invoice = Invoice::create([
            'user_id' => $paymentSessionData['user_id'],
            'rental_id' => null,
            'invoice_number' => $orderId,
            'amount' => $paymentSessionData['amount'],
            'status' => 'paid',
            'payment_method' => 'noon',
            'issued_at' => now(),
            'due_date' => now()->addDays(7),
        ]);

        $existingOrder = Order::where('order_number', $orderId)->first();
        if ($existingOrder) {
            $existingOrder->update([
                'invoice_id' => $invoice->id,
                'status' => 'paid',
                'payment_method' => 'noon',
                'payment_id' => $paymentId,
            ]);
        } else {
            Order::create([
                'user_id' => $paymentSessionData['user_id'],
                'invoice_id' => $invoice->id,
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => $paymentSessionData['amount'],
                'currency' => $paymentSessionData['currency'] ?? 'SAR',
                'status' => 'paid',
                'payment_method' => 'noon',
                'payment_id' => $paymentId,
                'notes' => $paymentSessionData['description'] ?? 'دفع حجز مغامرة',
                'items' => [
                    [
                        'name' => $paymentSessionData['description'] ?? 'دفع حجز مغامرة',
                        'amount' => $paymentSessionData['amount'],
                        'quantity' => 1,
                    ],
                ],
            ]);
        }

        Cache::forget('payment_session_' . $orderId);

        return ['processed' => true, 'order_id' => $orderId, 'payment_id' => $paymentId];
    }

    /**
     * Handle payment success (API) – returns JSON. Used by app as handlePaymentSuccess.
     * Accepts order_id or session_id (and optional payment_id).
     */
    public function paymentSuccess(Request $request)
    {
        $orderId = $request->get('order_id') ?? $request->get('session_id');
        $result = $this->processPaymentSuccess($orderId, $request->get('payment_id'));

        return response()->json([
            'success' => true,
            'message' => 'Payment completed successfully',
            'payment_id' => $result['payment_id'],
            'order_id' => $result['order_id'],
        ]);
    }

    /**
     * Handle payment success (Web) – redirect from gateway, show success page.
     * When opened in app WebView (Flutter), returns minimal HTML so the app never gets 500.
     */
    public function paymentSuccessPage(Request $request)
    {
        $orderId = $request->get('order_id') ?? $request->get('merchantReference');
        $paymentId = $request->get('payment_id') ?? $request->get('orderId');

        $isAppWebView = $this->isAppWebView($request);

        try {
            $result = $this->processPaymentSuccess($orderId, $paymentId);
        } catch (\Throwable $e) {
            Log::error('Payment success processing error', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            if ($isAppWebView) {
                return $this->paymentSuccessMinimalHtml($orderId, true);
            }
            throw $e;
        }

        if ($isAppWebView) {
            return $this->paymentSuccessMinimalHtml($result['order_id'] ?? $orderId, $result['processed']);
        }

        $order = null;
        if ($orderId) {
            $order = Order::where('order_number', $orderId)->first();
        }

        $orderDetails = null;
        if ($order) {
            $orderDetails = [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'address' => $order->getAttribute('address'),
                'activity_date' => $order->activity_date ? (is_object($order->activity_date) ? $order->activity_date->format('Y-m-d') : $order->activity_date) : null,
                'total_amount' => (float) $order->total_amount,
                'currency' => $order->currency ?? 'SAR',
                'items' => $order->items ?? [],
            ];
        }

        return Inertia::render('Payment/Success', [
            'processed' => $result['processed'],
            'order_id' => $result['order_id'],
            'payment_id' => $result['payment_id'],
            'order' => $orderDetails,
            'whatsapp_number' => preg_replace('/\D/', '', (string) env('WHATSAPP_NUMBER', '')),
        ]);
    }

    /**
     * Detect if request is from app WebView (Flutter/Android/iOS in-app browser).
     */
    protected function isAppWebView(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());
        if ($request->query('from_app') === '1' || $request->query('source') === 'app') {
            return true;
        }
        return str_contains($ua, 'wv') || str_contains($ua, 'webview')
            || str_contains($ua, 'flutter') || str_contains($ua, 'dart');
    }

    /**
     * Minimal HTML success page for app WebView – no Inertia/session, always 200.
     */
    protected function paymentSuccessMinimalHtml(?string $orderId, bool $processed): \Illuminate\Http\Response
    {
        $title = $processed ? 'تم الدفع بنجاح' : 'جاري المعالجة';
        $message = $processed
            ? 'تمت عملية الدفع بنجاح. يمكنك إغلاق هذه الصفحة والعودة للتطبيق.'
            : 'جاري تأكيد الدفع. يمكنك إغلاق هذه الصفحة.';
        $orderIdSafe = e((string) ($orderId ?? ''));
        $orderIdJson = json_encode((string) ($orderId ?? ''));
        $processedJson = $processed ? 'true' : 'false';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 24px; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0fdf4; color: #166534; }
        .box { text-align: center; max-width: 360px; }
        h1 { font-size: 1.5rem; margin-bottom: 12px; }
        p { color: #15803d; line-height: 1.6; }
        .order { font-weight: 600; margin-top: 16px; font-size: 0.95rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>✓ {$title}</h1>
        <p>{$message}</p>
        <p class="order">رقم الطلب: {$orderIdSafe}</p>
    </div>
    <script>
        (function() {
            var payload = { type: 'payment_success', order_id: {$orderIdJson}, processed: {$processedJson} };
            if (window.ReactNativeWebView && window.ReactNativeWebView.postMessage) {
                window.ReactNativeWebView.postMessage(JSON.stringify(payload));
            }
            if (window.flutter_inappwebview && typeof window.flutter_inappwebview.callHandler === 'function') {
                window.flutter_inappwebview.callHandler('paymentSuccess', payload);
            }
        })();
    </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Handle payment cancellation (API) – returns JSON.
     */
    public function paymentCancel(Request $request)
    {
        $orderId = $request->get('order_id');

        if ($orderId) {
            $invoice = Invoice::where('invoice_number', $orderId)->first();
            if ($invoice) {
                $invoice->update(['status' => 'cancelled']);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled',
            'order_id' => $orderId,
        ]);
    }

    /**
     * Handle payment cancellation (Web) – redirect from gateway, show cancel page.
     */
    /**
     * Handle payment failure (Web) – redirect from gateway when payment fails/cancelled.
     */
    public function paymentFailPage(Request $request)
    {
        $orderId = $request->get('order_id');

        if ($orderId) {
            $invoice = Invoice::where('invoice_number', $orderId)->first();
            if ($invoice) {
                $invoice->update(['status' => 'cancelled']);
            }
        }

        return Inertia::render('Payment/Fail', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Handle payment cancellation (Web) – redirect from gateway when user cancels.
     */
    public function paymentCancelPage(Request $request)
    {
        $orderId = $request->get('order_id');

        if ($orderId) {
            $invoice = Invoice::where('invoice_number', $orderId)->first();
            if ($invoice) {
                $invoice->update(['status' => 'cancelled']);
            }
        }

        return Inertia::render('Payment/Cancel', [
            'order_id' => $orderId,
        ]);
    }

        /**
     * Handle webhook from Noon
     */
    public function webhook(Request $request)
    {
        try {
            $payload = $request->all();

            Log::info('Noon Webhook Received', $payload);

            // Verify webhook signature (if Noon provides it)
            // $signature = $request->header('X-Noon-Signature');
            // if (!$this->verifyWebhookSignature($payload, $signature)) {
            //     return response()->json(['error' => 'Invalid signature'], 400);
            // }

            $orderId = $payload['order']['reference'] ?? null;
            $paymentStatus = $payload['order']['status'] ?? null;
            $paymentId = $payload['order']['id'] ?? null;

            if ($orderId && $paymentStatus) {
                // Get payment session data from cache
                $paymentSessionData = Cache::get('payment_session_' . $orderId);

                if ($paymentSessionData) {
                    switch ($paymentStatus) {
                        case 'CAPTURED':
                        case 'AUTHORIZED':
                            // Create invoice when payment is successful
                            $invoice = Invoice::create([
                                'user_id' => $paymentSessionData['user_id'],
                                'rental_id' => null,
                                'invoice_number' => $orderId,
                                'amount' => $paymentSessionData['amount'],
                                'status' => 'paid',
                                'payment_method' => 'noon',
                                'issued_at' => now(),
                                'due_date' => now()->addDays(7),
                            ]);

                            // Update existing order (e.g. store checkout) or create new one
                            $existingOrder = Order::where('order_number', $orderId)->first();
                            if ($existingOrder) {
                                $existingOrder->update([
                                    'invoice_id' => $invoice->id,
                                    'status' => 'paid',
                                    'payment_method' => 'noon',
                                    'payment_id' => $paymentId,
                                ]);
                            } else {
                                Order::create([
                                    'user_id' => $paymentSessionData['user_id'],
                                    'invoice_id' => $invoice->id,
                                    'order_number' => Order::generateOrderNumber(),
                                    'total_amount' => $paymentSessionData['amount'],
                                    'currency' => $paymentSessionData['currency'] ?? 'SAR',
                                    'status' => 'paid',
                                    'payment_method' => 'noon',
                                    'payment_id' => $paymentId,
                                    'notes' => $paymentSessionData['description'] ?? 'دفع حجز مغامرة',
                                    'items' => [
                                        [
                                            'name' => $paymentSessionData['description'] ?? 'دفع حجز مغامرة',
                                            'amount' => $paymentSessionData['amount'],
                                            'quantity' => 1,
                                        ],
                                    ],
                                ]);
                            }

                            // Remove payment session from cache so polling/redirect see paid state
                            Cache::forget('payment_session_' . $orderId);
                            break;

                        case 'FAILED':
                        case 'CANCELLED':
                        case 'DECLINED':
                            // Create cancelled invoice
                            $invoice = Invoice::create([
                                'user_id' => $paymentSessionData['user_id'],
                                'rental_id' => null,
                                'invoice_number' => $orderId,
                                'amount' => $paymentSessionData['amount'],
                                'status' => 'cancelled',
                                'payment_method' => 'noon',
                                'issued_at' => now(),
                                'due_date' => now()->addDays(7),
                            ]);

                            // Remove payment session from cache
                            Cache::forget('payment_session_' . $orderId);
                            break;

                        case 'PENDING':
                        case 'INITIATED':
                            // Keep payment session in cache
                            break;
                    }
                }
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Webhook Processing Error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Get payment status for polling (e.g. from WebView).
     * Accepts session_id or order_id. Returns status in a format the app expects:
     * completed / success / paid when payment is done, so the app can run
     * handlePaymentSuccess and createOrder without relying on redirect.
     */
    public function getPaymentStatus(Request $request)
    {
        $sessionId = $request->query('session_id') ?? $request->query('order_id');
        if (! is_string($sessionId) || $sessionId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing session_id or order_id',
            ], 422);
        }

        $orderId = $sessionId;

        // First check if invoice exists (payment completed – e.g. after webhook or redirect)
        $invoice = Invoice::where('invoice_number', $orderId)->first();

        if ($invoice && $invoice->status === 'paid') {
            return response()->json([
                'success' => true,
                'status' => 'completed',
                'payment_status' => 'success',
                'data' => [
                    'order_id' => $invoice->invoice_number,
                    'status' => 'completed',
                    'payment_status' => 'success',
                    'amount' => $invoice->amount,
                    'payment_method' => $invoice->payment_method,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                ],
            ]);
        }

        // Check if payment session exists (payment in progress)
        $paymentSessionData = Cache::get('payment_session_' . $orderId);

        if ($paymentSessionData) {
            return response()->json([
                'success' => true,
                'status' => 'pending',
                'payment_status' => 'pending',
                'data' => [
                    'order_id' => $orderId,
                    'status' => 'pending',
                    'amount' => $paymentSessionData['amount'],
                    'payment_method' => 'noon',
                    'created_at' => $paymentSessionData['created_at'],
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment session not found',
        ], 404);
    }

        /**
     * Get payment URL from Noon response
     */
    private function getPaymentUrl($paymentResponse)
    {
        // Extract payment URL from Noon response
        // This depends on Noon's API response structure
        if (isset($paymentResponse['result']['paymentOptions'])) {
            foreach ($paymentResponse['result']['paymentOptions'] as $option) {
                if ($option['method'] === 'CARD_SANDBOX' || $option['method'] === 'CARD') {
                    return $option['action'] ?? null;
                }
            }
        }

        // Fallback: construct checkout URL
        $orderId = $paymentResponse['result']['order']['id'] ?? null;
        if ($orderId) {
            return 'https://checkout.noonpayments.com/payment/v1/checkout/' . $orderId;
        }

        // If no URL found, return a default checkout URL
        return 'https://checkout.noonpayments.com/payment/v1/checkout';
    }

    /**
     * Verify webhook signature (implement based on Noon documentation)
     */
    private function verifyWebhookSignature($payload, $signature)
    {
        // Implement signature verification based on Noon's webhook documentation
        // This is a placeholder - you need to implement this based on Noon's requirements
        return true;
    }
}
