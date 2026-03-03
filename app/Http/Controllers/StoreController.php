<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StoreController extends Controller
{
    /**
     * Public store: products with categories.
     */
    public function index()
    {
        $products = Product::with('category')->where('status', 'active')->orderBy('created_at', 'desc')->get();
        $categories = Category::orderBy('category_name')->get();

        return Inertia::render('Store/Index', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Cart page (cart state is client-side via localStorage).
     */
    public function cart()
    {
        return Inertia::render('Store/Cart');
    }

    /**
     * Checkout form page.
     */
    public function checkoutForm()
    {
        return Inertia::render('Store/Checkout');
    }

    /**
     * Submit checkout: create guest user, order, then Noon payment session; return redirect_url.
     */
    public function submitCheckout(Request $request)
    {
        $expectsJson = $request->expectsJson();

        try {
            $validated = $request->validate([
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:20',
                'customer_email' => 'required|email',
                'address' => 'required|string|max:1000',
                'activity_date' => 'required|date|after_or_equal:today',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.product_name' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات غير صالحة',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        $items = $validated['items'];
        $totalAmount = 0;
        $orderItems = [];
        foreach ($items as $item) {
            $subtotal = (float) $item['price'] * (int) $item['quantity'];
            $totalAmount += $subtotal;
            $orderItems[] = [
                'name' => $item['product_name'],
                'quantity' => (int) $item['quantity'],
                'amount' => $subtotal,
                'price' => (float) $item['price'],
            ];
        }

        if ($totalAmount < 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'إجمالي الطلب يجب أن يكون أكبر من صفر.',
            ], 422);
        }

        try {
            $guestUser = User::firstOrCreate(
                ['email' => $validated['customer_email']],
                [
                    'customer_name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'],
                    'password' => Hash::make(Str::random(32)),
                ]
            );
            $guestUser->fill([
                'customer_name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'],
            ]);
            $guestUser->save();

            $orderNumber = Order::generateOrderNumber();
            $order = Order::create([
                'user_id' => $guestUser->id,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'address' => $validated['address'],
                'activity_date' => $validated['activity_date'],
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'currency' => 'SAR',
                'status' => 'pending',
                'payment_method' => 'noon',
                'items' => $orderItems,
                'notes' => 'طلب من المتجر - تاريخ الفعالية: ' . $validated['activity_date'],
            ]);

            foreach ($items as $item) {
                $order->products()->attach($item['product_id'], [
                    'quantity' => (int) $item['quantity'],
                    'price' => (float) $item['price'],
                ]);
            }

            $paymentRequest = Request::create('', 'POST', [
                'user_id' => $guestUser->id,
                'amount' => round($totalAmount, 2),
                'currency' => 'SAR',
                'order_id' => $orderNumber,
                'customer_email' => $validated['customer_email'],
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'description' => 'طلب متجر - ' . $orderNumber,
            ]);

            $paymentController = app(PaymentController::class);
            $paymentResponse = $paymentController->createPaymentSession($paymentRequest);
            $data = $paymentResponse->getData(true);

            if (! empty($data['success']) && ! empty($data['data']['checkout_url'])) {
                if ($expectsJson) {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => $data['data']['checkout_url'],
                        'order_number' => $orderNumber,
                    ]);
                }

                return redirect()->away($data['data']['checkout_url']);
            }

            $status = $paymentResponse->getStatusCode();
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $data['message'] ?? 'فشل في إنشاء جلسة الدفع',
                    'error' => $data['error'] ?? null,
                ], $status >= 400 ? $status : 422);
            }

            return back()->withErrors([
                'form' => $data['message'] ?? 'فشل في إنشاء جلسة الدفع',
            ]);
        } catch (\Throwable $e) {
            Log::error('Store checkout error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ في الخادم. حاول مرة أخرى أو تواصل مع الدعم.',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }

            return back()->withErrors([
                'form' => 'حدث خطأ في الخادم. حاول مرة أخرى.',
            ]);
        }
    }
}
