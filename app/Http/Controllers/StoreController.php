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
use Illuminate\Validation\ValidationException;
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
     * All products public page with category filter.
     */
    public function allProducts()
    {
        $products   = Product::with('category')->where('status', 'active')->orderBy('created_at', 'desc')->get();
        $categories = Category::orderBy('category_name')->get();

        return Inertia::render('Store/AllProducts', [
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Products filtered by category with sidebar of all categories.
     */
    public function categoryProducts(Category $category)
    {
        $products = Product::with('category')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();
        $categories = Category::orderBy('category_name')->get();

        return Inertia::render('Store/AllProducts', [
            'products' => $products,
            'categories' => $categories,
            'activeCategoryId' => $category->id,
            'pageTitle' => $category->category_name,
        ]);
    }

    /**
     * Public product detail page.
     */
    public function showProduct(Product $product)
    {
        if ($product->status !== 'active') {
            abort(404);
        }

        $product->load('category');

        $related = Product::with('category')
            ->where('status', 'active')
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->limit(4)
            ->get();

        return Inertia::render('Store/Show', [
            'product' => $product,
            'related' => $related,
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
    public function checkoutForm(Request $request)
    {
        $customer = null;

        if ($user = $request->user()) {
            $customer = [
                'customer_name' => $user->customer_name ?? '',
                'customer_phone' => $this->phoneForCheckout($user->phone),
                'customer_email' => $user->email ?? '',
            ];
        }

        return Inertia::render('Store/Checkout', [
            'customer' => $customer,
        ]);
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
                'items.*.duration' => 'nullable|integer|min:1',
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
            $duration = max(1, (int) ($item['duration'] ?? 1));
            $subtotal = (float) $item['price'] * (int) $item['quantity'] * $duration;
            $totalAmount += $subtotal;
            $orderItems[] = [
                'name' => $item['product_name'],
                'quantity' => (int) $item['quantity'],
                'duration' => $duration,
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

        $vatAmount = round($totalAmount * 0.15, 2);
        $grandTotal = round($totalAmount + $vatAmount, 2);

        try {
            $customerUser = $request->user();

            if ($customerUser) {
                $customerUser->fill([
                    'customer_name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'],
                    'email' => $validated['customer_email'],
                ]);
                $customerUser->save();
                $orderUser = $customerUser;
            } else {
                $orderUser = User::firstOrCreate(
                    ['email' => $validated['customer_email']],
                    [
                        'customer_name' => $validated['customer_name'],
                        'phone' => $validated['customer_phone'],
                        'password' => Hash::make(Str::random(32)),
                    ]
                );
                $orderUser->fill([
                    'customer_name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'],
                ]);
                $orderUser->save();
            }

            $orderNumber = Order::generateOrderNumber();
            $order = Order::create([
                'user_id' => $orderUser->id,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'address' => $validated['address'],
                'activity_date' => $validated['activity_date'],
                'order_number' => $orderNumber,
                'total_amount' => $grandTotal,
                'currency' => 'SAR',
                'status' => 'pending',
                'payment_status' => 'pending',
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

            $paymentController = app(PaymentController::class);
            $paymentResponse = $paymentController->createNoonSession([
                'user_id' => $orderUser->id,
                'amount' => $grandTotal,
                'currency' => 'SAR',
                'order_id' => $orderNumber,
                'customer_email' => $validated['customer_email'],
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'description' => 'طلب متجر - ' . $orderNumber,
                'from_app' => false,
            ]);

            if (! $paymentResponse instanceof \Illuminate\Http\JsonResponse) {
                if ($expectsJson) {
                    return response()->json([
                        'success' => false,
                        'message' => 'فشل في إنشاء جلسة الدفع (استجابة غير متوقعة)',
                    ], 500);
                }
                return back()->withErrors(['form' => 'فشل في إنشاء جلسة الدفع.']);
            }

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
            $message = $data['message'] ?? 'فشل في إنشاء جلسة الدفع';
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error' => $data['error'] ?? null,
                ], $status >= 400 ? $status : 422);
            }

            return back()->withErrors(['form' => $message]);
        } catch (ValidationException $e) {
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الدفع غير صالحة.',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Store checkout error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $showError = config('app.debug') || (bool) config('services.noon.store_checkout_debug', false);

            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $showError ? $e->getMessage() : 'حدث خطأ في الخادم. حاول مرة أخرى أو تواصل مع الدعم.',
                    'error' => $showError ? $e->getMessage() : null,
                ], 500);
            }

            return back()->withErrors([
                'form' => 'حدث خطأ في الخادم. حاول مرة أخرى.',
            ]);
        }
    }

    private function phoneForCheckout(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '0'.$digits;
        }

        return $phone;
    }
}
