<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentSession;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    /**
     * Mobile app checkout: creates the Order and a Noon payment session in one call.
     * POST /api/mobile/payment/checkout-url
     */
    public function mobileCheckoutUrl(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = validator($request->all(), [
                'user_id'          => 'required|exists:users,id',
                'customer_name'    => 'required|string|max:255',
                'customer_phone'   => 'required|string|max:20',
                'customer_email'   => 'required|email|max:255',
                'address'          => 'nullable|string|max:1000',
                'activity_date'    => 'nullable|date',
                'currency'         => 'nullable|string|in:SAR,USD,AED,KWD,QAR,BHD,OMR,JOD,EGP',
                'items'            => 'required|array|min:1',
                'items.*.product_id'   => 'nullable|exists:products,id',
                'items.*.product_name' => 'required|string|max:255',
                'items.*.quantity'     => 'required|integer|min:1',
                'items.*.price'        => 'required|numeric|min:0',
            ])->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صالحة',
                'errors'  => $e->errors(),
            ], 422);
        }

        $currency = $validated['currency'] ?? 'SAR';
        $items    = $validated['items'];

        // Calculate total server-side from items
        $totalAmount = collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);
        if ($totalAmount < 0.01) {
            return response()->json(['success' => false, 'message' => 'المبلغ الإجمالي غير صحيح'], 422);
        }

        // Build items for Order.items JSON, also collect product IDs for pivot
        $orderItems = [];
        $pivotProducts = [];
        foreach ($items as $item) {
            $orderItems[] = [
                'product_id'   => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'quantity'     => $item['quantity'],
                'price'        => $item['price'],
                'subtotal'     => $item['price'] * $item['quantity'],
            ];
            if (! empty($item['product_id'])) {
                $pivotProducts[$item['product_id']] = [
                    'quantity' => $item['quantity'],
                    'price'    => $item['price'],
                ];
            }
        }

        $order = null;
        try {
            DB::beginTransaction();

            $orderNumber = Order::generateOrderNumber();

            $order = Order::create([
                'user_id'        => $validated['user_id'],
                'customer_name'  => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'address'        => $validated['address'] ?? null,
                'activity_date'  => $validated['activity_date'] ?? null,
                'order_number'   => $orderNumber,
                'total_amount'   => $totalAmount,
                'currency'       => $currency,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'payment_method' => 'noon',
                'items'          => $orderItems,
            ]);

            if (! empty($pivotProducts)) {
                $order->products()->attach($pivotProducts);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('mobileCheckoutUrl order creation failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل إنشاء الطلب'], 500);
        }

        // Create Noon session — if it fails, delete the orphaned order
        $sessionResponse = $this->createNoonSession([
            'user_id'        => $validated['user_id'],
            'amount'         => $totalAmount,
            'currency'       => $currency,
            'order_id'       => $order->order_number,
            'description'    => 'طلب رقم ' . $order->order_number,
            'customer_email' => $validated['customer_email'],
            'customer_name'  => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'from_app'       => true,
        ]);

        $responseData = json_decode($sessionResponse->getContent(), true);

        if (! ($responseData['success'] ?? false)) {
            // Roll back — delete the order if Noon session could not be created
            try {
                $order->products()->detach();
                $order->delete();
            } catch (\Throwable $ex) {
                Log::warning('mobileCheckoutUrl order cleanup failed', ['message' => $ex->getMessage()]);
            }
            return $sessionResponse;
        }

        return response()->json([
            'success'      => true,
            'message'      => 'تم إنشاء جلسة الدفع بنجاح',
            'data'         => [
                'checkout_url' => $responseData['data']['checkout_url'],
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $totalAmount,
                'currency'     => $currency,
            ],
        ], 201);
    }

    /**
     * Create payment session with Noon — API endpoint (validates request then delegates).
     */
    public function createPaymentSession(Request $request)
    {
        try {
            $validated = validator($request->all(), [
                'user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|in:SAR,USD,AED,KWD,QAR,BHD,OMR,JOD,EGP',
                'order_id' => 'required|string|unique:invoices,invoice_number',
                'description' => 'nullable|string|max:255',
                'customer_email' => 'required|email',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'from_app' => 'nullable|boolean',
            ])->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صالحة',
                'errors' => $e->errors(),
            ], 422);
        }

        return $this->createNoonSession(array_merge($validated, [
            'from_app' => $request->boolean('from_app') || $request->input('source') === 'app',
        ]));
    }

    /**
     * Core Noon session creation — accepts a plain array, no Request dependency.
     * Safe to call from other controllers or services without triggering setContainer issues.
     */
    public function createNoonSession(array $data): \Illuminate\Http\JsonResponse
    {
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

            $authHeader = $noon['auth_header'] ?? null;
            if ($authHeader !== null && $authHeader !== '') {
                $authHeader = preg_replace('/^Key_/', 'Key ', trim((string) $authHeader));
            } else {
                $authHeader = 'Key ' . base64_encode($noon['business_id'] . '.' . $noon['app_id'] . ':' . $noon['api_key']);
            }

            $fromApp = ! empty($data['from_app']);
            $orderId = $data['order_id'];
            $returnUrl = $baseUrl . '/payment/success?order_id=' . $orderId . ($fromApp ? '&from_app=1' : '');
            $failUrl = $baseUrl . '/payment/fail?order_id=' . $orderId . ($fromApp ? '&from_app=1' : '');
            $configuration = [
                'returnUrl' => $returnUrl,
                'paymentAction' => $noon['payment_action'] ?? 'SALE',
            ];
            if ($noon['cancel_url_enabled'] ?? true) {
                $configuration['cancelUrl'] = $failUrl;
            }

            $orderPayload = [
                'amount' => (float) $data['amount'],
                'currency' => $data['currency'],
                'reference' => $orderId,
                'name' => $data['description'] ?? 'Order from Adventure World',
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
                $checkoutUrl = $paymentResponse['result']['checkoutData']['postUrl'] ?? null;

                $paymentSessionData = [
                    'user_id' => $data['user_id'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'order_id' => $orderId,
                    'description' => $data['description'] ?? null,
                    'customer_email' => $data['customer_email'] ?? null,
                    'customer_name' => $data['customer_name'] ?? null,
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'noon_order_id' => $paymentResponse['order']['id'] ?? null,
                    'created_at' => now()->toIso8601String(),
                ];
                Cache::put('payment_session_' . $orderId, $paymentSessionData, 3600);

                try {
                    PaymentSession::updateOrCreate(
                        ['merchant_reference' => $orderId],
                        [
                            'user_id' => (int) $data['user_id'],
                            'amount' => $paymentSessionData['amount'],
                            'currency' => $paymentSessionData['currency'] ?? 'SAR',
                            'payload' => $paymentSessionData,
                            'noon_order_id' => $paymentResponse['order']['id'] ?? null,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Payment session DB save failed (cache used)', [
                        'order_id' => $orderId,
                        'message' => $e->getMessage(),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment session created successfully',
                    'data' => [
                        'checkout_url' => $checkoutUrl,
                        'payment_id' => $paymentResponse['result']['order']['id'] ?? null,
                        'order_id' => $orderId,
                        'status' => $paymentResponse['result']['order']['status'] ?? null,
                        'noon_response' => $paymentResponse,
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
                    $userMessage = 'رفض إنشاء الجلسة (403/5019). تحقق من بيانات الدخول لنون، وأن رابط العودة مسموح في لوحة نون (Allowed Return URLs).';
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

        $cacheKey = 'payment_session_' . $orderId;
        $cacheExists = Cache::has($cacheKey);
        Log::info('processPaymentSuccess', ['order_id' => $orderId, 'cache_exists' => $cacheExists]);

        $paymentSessionData = Cache::get($cacheKey);

        // Already processed (e.g. by webhook): invoice exists and is paid – return success so app does not retry
        if (! $paymentSessionData) {
            if (! $cacheExists) {
                Log::info('processPaymentSuccess cache_miss', ['order_id' => $orderId]);
            }
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

        try {
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
        } catch (\Throwable $e) {
            Log::error('processPaymentSuccess exception', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
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
     * Flutter calls this via POST right after intercepting the /payment/success redirect.
     * Immediately tries to confirm payment via Noon API, updates Order, then returns current status.
     * This gives the backend a head-start before the app starts polling.
     *
     * POST /api/payment/notify-success
     * Body: { "order_id": "ORD-..." }  OR  { "session_id": "ORD-..." }
     */
    public function notifyPaymentSuccess(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->input('order_id') ?? $request->input('session_id');

        if (! $orderId) {
            return response()->json(['success' => false, 'message' => 'order_id مطلوب'], 422);
        }

        $order = Order::where('order_number', $orderId)->first();

        // Already confirmed — nothing to do
        if ($order && $order->payment_status === 'paid') {
            return response()->json([
                'success'        => true,
                'status'         => 'completed',
                'payment_status' => 'success',
                'data'           => [
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                    'amount'       => $order->total_amount,
                    'currency'     => $order->currency ?? 'SAR',
                ],
            ]);
        }

        // Try to verify via Noon API using the stored PaymentSession noonOrderId
        $confirmed = false;
        try {
            $session = PaymentSession::where('merchant_reference', $orderId)->first();
            $noonOrderId = $session?->noon_order_id ?? ($order?->payment_id ?? null);

            if ($noonOrderId && $this->verifyNoonOrderStatus($noonOrderId, 'CAPTURED')) {
                DB::transaction(function () use ($order, $orderId, $noonOrderId) {
                    if ($order && $order->payment_status !== 'paid') {
                        // Upsert invoice
                        $invoice = Invoice::firstOrCreate(
                            ['invoice_number' => $orderId],
                            [
                                'user_id'        => $order->user_id,
                                'rental_id'      => null,
                                'amount'         => $order->total_amount,
                                'status'         => 'paid',
                                'payment_method' => 'noon',
                                'issued_at'      => now(),
                                'due_date'       => now()->addDays(7),
                            ]
                        );
                        if ($invoice->status !== 'paid') {
                            $invoice->update(['status' => 'paid']);
                        }

                        $order->update([
                            'payment_status'          => 'paid',
                            'status'                  => 'paid',
                            'payment_id'              => $noonOrderId,
                            'payment_order_reference' => $orderId,
                            'invoice_id'              => $invoice->id,
                        ]);
                    }
                    $this->markPaymentSessionUsed($orderId);
                    Cache::forget('payment_session_' . $orderId);
                });
                $confirmed = true;
                Log::info('notifyPaymentSuccess: confirmed via Noon API', ['order_id' => $orderId]);
            }
        } catch (\Throwable $e) {
            Log::warning('notifyPaymentSuccess: Noon API check failed', [
                'order_id' => $orderId,
                'message'  => $e->getMessage(),
            ]);
        }

        // Refresh order after potential update
        $order = $order ? $order->fresh() : Order::where('order_number', $orderId)->first();
        $isPaid = $confirmed || ($order?->payment_status === 'paid');

        return response()->json([
            'success'        => true,
            'status'         => $isPaid ? 'completed' : 'pending',
            'payment_status' => $isPaid ? 'success' : 'pending',
            'data'           => $order ? [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'amount'       => $order->total_amount,
                'currency'     => $order->currency ?? 'SAR',
            ] : ['order_id' => $orderId],
        ]);
    }

    /**
     * Handle payment success callback (GET or POST).
     * No session or auth; uses order_id from query/body and DB only.
     * Returns simple HTML for WebView and browser. Excluded from CSRF.
     *
     * Noon sends in redirect URL:
     *   - order_id       : our merchant reference (appended by us to returnUrl)
     *   - orderId        : Noon's internal order ID
     *   - orderStatus    : CAPTURED | AUTHORIZED | FAILED | PENDING | …
     *   - orderReference : merchant reference (Noon standard param)
     */
    public function paymentSuccessPage(Request $request)
    {
        // Resolve our merchant reference (order_number) from multiple possible params
        $orderId = $request->get('order_id')
            ?? $request->get('orderReference')
            ?? $request->get('merchantReference');

        // Noon's internal order ID and status from the redirect
        $noonOrderId   = $request->get('orderId');
        $noonStatus    = strtoupper((string) ($request->get('orderStatus') ?? $request->get('status') ?? ''));
        $fromApp       = $request->query('from_app') === '1';

        $noonConfirmed = in_array($noonStatus, ['CAPTURED', 'AUTHORIZED'], true);

        // If Noon confirmed payment in redirect params, update Order immediately
        if ($orderId && $noonConfirmed) {
            try {
                $order = Order::where('order_number', $orderId)->first();
                if ($order && $order->payment_status !== 'paid') {
                    $order->update([
                        'payment_status'          => 'paid',
                        'status'                  => 'paid',
                        'payment_id'              => $noonOrderId ?? $order->payment_id,
                        'payment_order_reference' => $orderId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('paymentSuccessPage order update failed', [
                    'order_id' => $orderId,
                    'message'  => $e->getMessage(),
                ]);
            }
        }

        $success      = $noonConfirmed || $this->isPaymentProcessed($orderId);
        $orderIdSafe  = $orderId !== null && $orderId !== '' ? e((string) $orderId) : '—';
        $orderIdJson  = json_encode((string) ($orderId ?? ''));
        $processedJson = $success ? 'true' : 'false';

        $title   = $success ? 'Payment Successful' : 'Payment Pending';
        $message = $success
            ? 'Your payment has been confirmed.'
            : 'Your payment is being processed. You can close this page.';
        $bg    = $success ? '#f0fdf4' : '#fffbeb';
        $color = $success ? '#166534' : '#92400e';

        // Deep-link redirect for Flutter when payment is confirmed
        $deepLinkScript = '';
        if ($fromApp && $success) {
            $scheme        = config('app.mobile_scheme', 'adventureworld');
            $deepLinkUrl   = e("{$scheme}://payment/success?order_id=" . urlencode((string) ($orderId ?? '')));
            $deepLinkJson  = json_encode($deepLinkUrl);
            $deepLinkScript = <<<JS

    <script>
        (function() {
            var payload = { type: 'payment_success', order_id: {$orderIdJson}, processed: {$processedJson} };
            if (window.ReactNativeWebView && window.ReactNativeWebView.postMessage) {
                window.ReactNativeWebView.postMessage(JSON.stringify(payload));
            }
            if (window.flutter_inappwebview && typeof window.flutter_inappwebview.callHandler === 'function') {
                window.flutter_inappwebview.callHandler('paymentSuccess', payload);
            }
            try { window.location.href = {$deepLinkJson}; } catch(e) {}
        })();
    </script>
JS;
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 2rem; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: {$bg}; color: {$color}; text-align: center; }
        .box { max-width: 24rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { margin-bottom: 1rem; }
        .order { font-size: 0.875rem; color: #4b5563; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{$title}</h1>
        <p>{$message}</p>
        <p class="order">Order: {$orderIdSafe}</p>
    </div>{$deepLinkScript}
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Check if payment is already confirmed (by webhook). No side effects.
     */
    protected function isPaymentProcessed(?string $orderId): bool
    {
        if (! $orderId) {
            return false;
        }

        $order = Order::where('order_number', $orderId)->first();
        if ($order && $order->status === 'paid') {
            return true;
        }

        return Invoice::where('invoice_number', $orderId)->where('status', 'paid')->exists();
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

        if ($this->isAppWebView($request)) {
            return $this->paymentFailMinimalHtml($orderId);
        }

        return response()->view('payment-fail', ['order_id' => $orderId]);
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

        if ($this->isAppWebView($request)) {
            return $this->paymentFailMinimalHtml($orderId);
        }

        return response()->view('payment-cancel', ['order_id' => $orderId]);
    }

    /**
     * Minimal HTML for payment fail/cancel in app WebView – always 200.
     */
    protected function paymentFailMinimalHtml(?string $orderId): \Illuminate\Http\Response
    {
        $orderIdSafe = e((string) ($orderId ?? ''));
        $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لم تتم العملية</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 24px; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #fef2f2; color: #991b1b; text-align: center; }
    </style>
</head>
<body>
    <p>لم تتم عملية الدفع. يمكنك إغلاق هذه الصفحة.</p>
    <p class="order">رقم الطلب: {$orderIdSafe}</p>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Handle webhook from Noon. Verifies signature (if configured), loads session from DB or cache,
     * confirms payment in a transaction with idempotency.
     */
    public function webhook(Request $request)
    {
        $rawBody = $request->getContent();
        $payload = $request->all();

        Log::info('Noon Webhook Received', ['payload_keys' => array_keys($payload)]);

        try {
            $secret = config('services.noon.webhook_secret');
            if ($secret && is_string($secret) && $secret !== '') {
                $signature = $request->header('X-Noon-Signature') ?? $request->header('x-noon-signature');
                if (! $this->verifyWebhookSignature($rawBody, $signature, $secret)) {
                    Log::warning('Noon Webhook invalid signature');
                    return response()->json(['error' => 'Invalid signature'], 400);
                }
            }

            $orderId = $payload['order']['reference'] ?? null;
            $paymentStatus = $payload['order']['status'] ?? null;
            $paymentId = $payload['order']['id'] ?? null;

            if (! $orderId || ! $paymentStatus) {
                return response()->json(['success' => true]);
            }

            $paymentSessionData = $this->getPaymentSessionData($orderId);

            if (! $paymentSessionData) {
                Log::info('Noon Webhook no session data', ['order_id' => $orderId]);
                return response()->json(['success' => true]);
            }

            switch ($paymentStatus) {
                case 'CAPTURED':
                case 'AUTHORIZED':
                    if ($this->isPaymentProcessed($orderId)) {
                        Log::info('Noon Webhook idempotent skip', ['order_id' => $orderId]);
                        return response()->json(['success' => true]);
                    }

                    if (! $this->verifyNoonOrderStatus($paymentId, $paymentStatus)) {
                        Log::warning('Noon Webhook status mismatch', ['order_id' => $orderId, 'payment_id' => $paymentId]);
                    }

                    DB::transaction(function () use ($orderId, $paymentId, $paymentSessionData) {
                        // Upsert Invoice for backward compatibility
                        $invoice = Invoice::firstOrCreate(
                            ['invoice_number' => $orderId],
                            [
                                'user_id'         => $paymentSessionData['user_id'],
                                'rental_id'       => null,
                                'amount'          => $paymentSessionData['amount'],
                                'status'          => 'paid',
                                'payment_method'  => 'noon',
                                'issued_at'       => now(),
                                'due_date'        => now()->addDays(7),
                            ]
                        );
                        // Always mark the invoice paid (handles case where it was created earlier)
                        if ($invoice->status !== 'paid') {
                            $invoice->update(['status' => 'paid']);
                        }

                        // Update the existing Order — orders always pre-exist in the new flow
                        $existingOrder = Order::where('order_number', $orderId)->lockForUpdate()->first();
                        if ($existingOrder) {
                            $existingOrder->update([
                                'invoice_id'              => $invoice->id,
                                'status'                  => 'paid',
                                'payment_status'          => 'paid',
                                'payment_method'          => 'noon',
                                'payment_id'              => $paymentId,
                                'payment_order_reference' => $orderId,
                            ]);
                        } else {
                            Log::warning('Noon Webhook: no existing Order found for reference', [
                                'order_reference' => $orderId,
                            ]);
                        }

                        $this->markPaymentSessionUsed($orderId);
                        Cache::forget('payment_session_' . $orderId);
                    });
                    break;

                case 'FAILED':
                case 'CANCELLED':
                case 'DECLINED':
                    DB::transaction(function () use ($orderId, $paymentSessionData) {
                        // Upsert a cancelled Invoice
                        Invoice::firstOrCreate(
                            ['invoice_number' => $orderId],
                            [
                                'user_id'        => $paymentSessionData['user_id'],
                                'rental_id'      => null,
                                'amount'         => $paymentSessionData['amount'],
                                'status'         => 'cancelled',
                                'payment_method' => 'noon',
                                'issued_at'      => now(),
                                'due_date'       => now()->addDays(7),
                            ]
                        );

                        // Mark the existing Order as failed (keep it for records)
                        $existingOrder = Order::where('order_number', $orderId)->lockForUpdate()->first();
                        if ($existingOrder && $existingOrder->payment_status !== 'paid') {
                            $existingOrder->update([
                                'payment_status' => 'failed',
                                'status'         => 'cancelled',
                            ]);
                        }

                        $this->markPaymentSessionUsed($orderId);
                        Cache::forget('payment_session_' . $orderId);
                    });
                    break;

                case 'PENDING':
                case 'INITIATED':
                    break;
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Noon Webhook Processing Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Get payment session data from DB first, then cache.
     */
    protected function getPaymentSessionData(string $orderId): ?array
    {
        $session = PaymentSession::where('merchant_reference', $orderId)->whereNull('used_at')->first();
        if ($session && is_array($session->payload)) {
            return $session->payload;
        }

        return Cache::get('payment_session_' . $orderId);
    }

    /**
     * Mark payment session as used so webhook is idempotent.
     */
    protected function markPaymentSessionUsed(string $orderId): void
    {
        PaymentSession::where('merchant_reference', $orderId)->update(['used_at' => now()]);
    }

    /**
     * Verify webhook signature (HMAC-SHA256 of raw body). Noon docs may specify header name and format.
     */
    protected function verifyWebhookSignature(string $rawBody, ?string $signature, string $secret): bool
    {
        if (! $signature) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature) || hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /**
     * Optionally verify payment status with Noon API before marking order paid.
     */
    protected function verifyNoonOrderStatus(?string $noonOrderId, string $expectedStatus): bool
    {
        if (! $noonOrderId) {
            return false;
        }
        $noon = config('services.noon', []);
        if (empty($noon['api_key']) || empty($noon['api_url'])) {
            return true;
        }
        try {
            $url = rtrim($noon['api_url'], '/') . '/order/' . $noonOrderId;
            $authHeader = $noon['auth_header'] ?? null;
            if ($authHeader === null || $authHeader === '') {
                $authHeader = 'Key ' . base64_encode($noon['business_id'] . '.' . $noon['app_id'] . ':' . $noon['api_key']);
            } else {
                $authHeader = preg_replace('/^Key_/', 'Key ', trim((string) $authHeader));
            }
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Accept' => 'application/json',
                'x-api-key' => $noon['api_key'],
            ])->get($url);
            if (! $response->successful()) {
                return false;
            }
            $data = $response->json();
            $status = $data['order']['status'] ?? $data['result']['order']['status'] ?? null;
            return in_array($status, ['CAPTURED', 'AUTHORIZED'], true);
        } catch (\Throwable $e) {
            Log::warning('Noon order status check failed', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get payment status for polling (e.g. from app WebView).
     * Priority: Order.payment_status → Invoice → Cache → PaymentSession DB → 404.
     * Statuses returned: completed | pending | failed | not_found.
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

        // 1. Order — primary source (covers mobile flow where order pre-exists)
        $order = Order::where('order_number', $orderId)->first();

        if ($order) {
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success'        => true,
                    'status'         => 'completed',
                    'payment_status' => 'success',
                    'data'           => [
                        'order_id'       => $order->id,
                        'order_number'   => $order->order_number,
                        'status'         => 'completed',
                        'payment_status' => 'success',
                        'amount'         => $order->total_amount,
                        'currency'       => $order->currency ?? 'SAR',
                        'payment_method' => $order->payment_method,
                        'created_at'     => $order->created_at,
                    ],
                ]);
            }

            if ($order->payment_status === 'failed') {
                return response()->json([
                    'success'        => true,
                    'status'         => 'failed',
                    'payment_status' => 'failed',
                    'data'           => [
                        'order_id'     => $order->id,
                        'order_number' => $order->order_number,
                        'status'       => 'failed',
                        'amount'       => $order->total_amount,
                        'currency'     => $order->currency ?? 'SAR',
                        'created_at'   => $order->created_at,
                    ],
                ]);
            }

            // Order exists but payment still pending — return pending immediately
            // (no need to check cache/session if order row is there)
            return response()->json([
                'success'        => true,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'data'           => [
                    'order_id'       => $order->id,
                    'order_number'   => $order->order_number,
                    'status'         => 'pending',
                    'amount'         => $order->total_amount,
                    'currency'       => $order->currency ?? 'SAR',
                    'payment_method' => $order->payment_method,
                    'created_at'     => $order->created_at,
                ],
            ]);
        }

        // 2. Invoice fallback — covers the legacy web-store flow (no pre-existing Order row)
        $invoice = Invoice::where('invoice_number', $orderId)->first();

        if ($invoice) {
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success'        => true,
                    'status'         => 'completed',
                    'payment_status' => 'success',
                    'data'           => [
                        'order_id'       => $orderId,
                        'order_number'   => $orderId,
                        'status'         => 'completed',
                        'payment_status' => 'success',
                        'amount'         => $invoice->amount,
                        'currency'       => 'SAR',
                        'payment_method' => $invoice->payment_method,
                        'created_at'     => $invoice->created_at,
                    ],
                ]);
            }

            if (in_array($invoice->status, ['cancelled', 'failed'], true)) {
                return response()->json([
                    'success'        => true,
                    'status'         => 'failed',
                    'payment_status' => 'failed',
                    'data'           => [
                        'order_id'   => $orderId,
                        'status'     => 'failed',
                        'amount'     => $invoice->amount,
                        'created_at' => $invoice->created_at,
                    ],
                ]);
            }
        }

        // 3. Cache — session still alive, payment in progress
        $paymentSessionData = Cache::get('payment_session_' . $orderId);

        if ($paymentSessionData) {
            return response()->json([
                'success'        => true,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'data'           => [
                    'order_id'       => $orderId,
                    'status'         => 'pending',
                    'amount'         => $paymentSessionData['amount'] ?? null,
                    'currency'       => $paymentSessionData['currency'] ?? 'SAR',
                    'payment_method' => 'noon',
                    'created_at'     => $paymentSessionData['created_at'] ?? null,
                ],
            ]);
        }

        // 4. PaymentSession DB — cache expired but session row exists (webhook not yet processed)
        $dbSession = PaymentSession::where('merchant_reference', $orderId)
            ->whereNull('used_at')
            ->first();

        if ($dbSession) {
            return response()->json([
                'success'        => true,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'data'           => [
                    'order_id'       => $orderId,
                    'status'         => 'pending',
                    'amount'         => $dbSession->amount,
                    'currency'       => $dbSession->currency ?? 'SAR',
                    'payment_method' => 'noon',
                    'created_at'     => $dbSession->created_at,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'status'  => 'not_found',
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
}
