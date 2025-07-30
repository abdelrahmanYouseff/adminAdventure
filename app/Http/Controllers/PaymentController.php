<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
                        // Noon API Configuration
            $noonApiUrl = env('NOON_API_URL', 'https://api-test.sa.noonpayments.com/payment/v1/');
            $noonAuthHeader = env('NOON_AUTH_HEADER');

            if (!env('NOON_API_KEY') || !env('NOON_BUSINESS_ID') || !env('NOON_APP_ID')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noon API configuration incomplete',
                ], 500);
            }

            // Prepare payment data for Noon
            $paymentData = [
                'apiOperation' => 'INITIATE',
                'order' => [
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'reference' => $request->order_id,
                    'name' => $request->description ?? 'Order from Adventure World',
                    'category' => 'pay'
                ],
                'configuration' => [
                    'returnUrl' => env('APP_URL') . '/api/payment/success?order_id=' . $request->order_id,
                ]
            ];

            // Prepare Authorization header
            $authHeader = 'Key ' . base64_encode(env('NOON_BUSINESS_ID') . '.' . env('NOON_APP_ID') . ':' . env('NOON_API_KEY'));

            // Log request details for debugging
            Log::info('Noon API Request', [
                'url' => $noonApiUrl . 'order',
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'data' => $paymentData,
            ]);

            // Call Noon API
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-api-key' => env('NOON_API_KEY'),
            ])->post($noonApiUrl . 'order', $paymentData);

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
                Log::error('Noon API Error', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'request_data' => $paymentData,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment session',
                    'error' => $response->json(),
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error('Payment Session Creation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle payment success
     */
    public function paymentSuccess(Request $request)
    {
        $paymentId = $request->get('payment_id');
        $orderId = $request->get('order_id');

        // Get payment session data from cache
        $paymentSessionData = Cache::get('payment_session_' . $orderId);

        if ($paymentSessionData) {
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

            // Remove payment session from cache
            Cache::forget('payment_session_' . $orderId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment completed successfully',
            'payment_id' => $paymentId,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Handle payment cancellation
     */
    public function paymentCancel(Request $request)
    {
        $orderId = $request->get('order_id');

        // Update invoice status
        if ($orderId) {
            $invoice = Invoice::where('invoice_number', $orderId)->first();
            if ($invoice) {
                $invoice->update([
                    'status' => 'cancelled',
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled',
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
                            // Create invoice only when payment is successful
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

                            // Remove payment session from cache
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
     * Get payment status
     */
    public function getPaymentStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
        ]);

        // First check if invoice exists (payment completed)
        $invoice = Invoice::where('invoice_number', $request->order_id)->first();

        if ($invoice) {
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'amount' => $invoice->amount,
                    'payment_method' => $invoice->payment_method,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                ],
            ]);
        }

        // Check if payment session exists (payment in progress)
        $paymentSessionData = Cache::get('payment_session_' . $request->order_id);

        if ($paymentSessionData) {
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $request->order_id,
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
