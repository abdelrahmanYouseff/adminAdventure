<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;

use Inertia\Inertia;

use App\Http\Controllers\PaymentController;
use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect()->route('home');
});

Route::get('/home', function (Request $request) {
    $products   = \App\Models\Product::with('category')->where('status', 'active')->orderBy('created_at', 'desc')->get();
    $categories = \App\Models\Category::orderBy('category_name')->get();

    $paymentSuccess = $request->session()->get('payment_success');

    if (! $paymentSuccess && $request->filled('paid_order')) {
        try {
            $order = \App\Models\Order::where('order_number', $request->string('paid_order'))->first();
            if ($order && ($order->payment_status === 'paid' || $order->status === 'paid')) {
                $paymentSuccess = app(PaymentController::class)->buildPaymentSuccessPayload($order);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('home paid_order payload failed', [
                'paid_order' => $request->string('paid_order'),
                'message' => $e->getMessage(),
            ]);
        }
    }

    return Inertia::render('Home', [
        'products'         => $products,
        'categories'       => $categories,
        'payment_success'  => $paymentSuccess,
    ]);
})->name('home');

// Privacy page (public)
Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'staff'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'admin'])->prefix('settings')->name('settings.')->group(function () {
    Route::get('whatsapp', [\App\Http\Controllers\Settings\WhatsappNotificationSettingsController::class, 'index'])
        ->name('whatsapp.index');
    Route::post('whatsapp', [\App\Http\Controllers\Settings\WhatsappNotificationSettingsController::class, 'store'])
        ->name('whatsapp.store');
    Route::patch('whatsapp/{recipient}', [\App\Http\Controllers\Settings\WhatsappNotificationSettingsController::class, 'update'])
        ->name('whatsapp.update');
    Route::delete('whatsapp/{recipient}', [\App\Http\Controllers\Settings\WhatsappNotificationSettingsController::class, 'destroy'])
        ->name('whatsapp.destroy');
});

Route::get('products', [ProductController::class, 'index'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products');

Route::get('products/create', [ProductController::class, 'create'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products.create');

Route::post('products', [ProductController::class, 'store'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products.store');

Route::post('products/import', [ProductController::class, 'import'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products.import');

Route::get('products/{product}/edit', [ProductController::class, 'edit'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products.edit');

Route::put('products/{product}', [ProductController::class, 'update'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products.update');

Route::patch('products/{product}', [ProductController::class, 'update'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products.patch');

Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products.toggle-status');

Route::delete('products/{product}', [ProductController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('products.destroy');

Route::get('categories', [CategoryController::class, 'index'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('categories.index');

Route::get('categories/create', [CategoryController::class, 'create'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('categories.create');

Route::post('categories', [CategoryController::class, 'store'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('categories.store');

Route::get('categories/{category}', [CategoryController::class, 'show'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('categories.show');

Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('categories.edit');

Route::put('categories/{category}', [CategoryController::class, 'update'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('categories.update');

Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('categories.destroy');

Route::get('customers', [\App\Http\Controllers\CustomerController::class, 'index'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('customers');




Route::get('users', [\App\Http\Controllers\UserController::class, 'index'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('users');

Route::post('users', [\App\Http\Controllers\UserController::class, 'store'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('users.store');

Route::delete('users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('users.destroy');

// Package routes
Route::resource('packages', PackageController::class)->middleware(['auth', 'verified', 'admin']);

// Invoices Routes
Route::get('invoices', [InvoiceController::class, 'index'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('invoices.index');

Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('invoices.show');

Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'generatePdf'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('invoices.pdf');

Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('invoices.update-status');

Route::get('invoices/export/csv', [InvoiceController::class, 'export'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('invoices.export');

Route::patch('invoices/update-overdue', [InvoiceController::class, 'updateOverdueInvoices'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('invoices.update-overdue');

// Quotations Routes
Route::resource('quotations', QuotationController::class)->middleware(['auth', 'verified', 'admin']);

Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'generatePdf'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('quotations.pdf');

Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('quotations.update-status');

// Orders Routes
Route::get('orders', [OrderController::class, 'index'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('orders.index');

Route::get('orders/{order}', [OrderController::class, 'show'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('orders.show');

Route::delete('orders/{order}', [OrderController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'admin'])
    ->name('orders.destroy');

Route::get('worker-orders', [\App\Http\Controllers\WorkerOrderController::class, 'index'])
    ->middleware(['auth', 'verified', 'staff'])
    ->name('worker-orders.index');

Route::get('worker-orders/{order}', [\App\Http\Controllers\WorkerOrderController::class, 'show'])
    ->middleware(['auth', 'verified', 'staff'])
    ->name('worker-orders.show');

Route::get('worker-orders/{order}/delivery-note', [\App\Http\Controllers\WorkerOrderController::class, 'deliveryNote'])
    ->middleware(['auth', 'verified', 'staff'])
    ->name('worker-orders.delivery-note');

Route::post('worker-orders/lines/{workerOrder}/complete', [\App\Http\Controllers\WorkerOrderController::class, 'complete'])
    ->middleware(['auth', 'verified', 'staff'])
    ->name('worker-orders.complete');

// Public store (no auth)
Route::get('store', [\App\Http\Controllers\StoreController::class, 'index'])->name('store.index');
Route::get('store/cart', [\App\Http\Controllers\StoreController::class, 'cart'])->name('store.cart');
Route::get('store/checkout', [\App\Http\Controllers\StoreController::class, 'checkoutForm'])->name('store.checkout');
Route::post('store/checkout', [\App\Http\Controllers\StoreController::class, 'submitCheckout'])->name('store.checkout.submit');
Route::get('store/products/{product}', [\App\Http\Controllers\StoreController::class, 'showProduct'])->name('store.product.show');
Route::get('store/all-products', [\App\Http\Controllers\StoreController::class, 'allProducts'])->name('store.all-products');
Route::get('store/categories/{category}', [\App\Http\Controllers\StoreController::class, 'categoryProducts'])->name('store.category.show');

Route::get('order/{slug}/location', [\App\Http\Controllers\OrderLocationController::class, 'show'])
    ->name('store.order.location');

Route::middleware('auth')->group(function () {
    Route::get('store/complete-registration', [\App\Http\Controllers\StoreCompleteRegistrationController::class, 'create'])
        ->name('store.complete-registration');
    Route::post('store/complete-registration', [\App\Http\Controllers\StoreCompleteRegistrationController::class, 'store'])
        ->name('store.complete-registration.store');
    Route::get('store/account', [\App\Http\Controllers\StoreAccountController::class, 'edit'])->name('store.account');
    Route::patch('store/account', [\App\Http\Controllers\StoreAccountController::class, 'update'])->name('store.account.update');
    Route::get('store/orders', [\App\Http\Controllers\StoreOrdersController::class, 'index'])->name('store.orders');
});

// Payment return URLs (no auth - used by payment gateway redirect)
Route::match(['GET', 'POST'], 'payment/return/{order}', [\App\Http\Controllers\PaymentController::class, 'paymentReturnPage'])
    ->name('payment.return');
Route::get('payment/return/{order}/status', [\App\Http\Controllers\PaymentController::class, 'paymentReturnStatus'])
    ->name('payment.return.status');
Route::match(['GET', 'POST'], 'payment/success', [\App\Http\Controllers\PaymentController::class, 'paymentSuccessPage'])
    ->name('payment.success');
Route::match(['GET', 'POST'], 'payment/fail', [\App\Http\Controllers\PaymentController::class, 'paymentFailPage'])
    ->name('payment.fail');
Route::match(['GET', 'POST'], 'payment/cancel', [\App\Http\Controllers\PaymentController::class, 'paymentCancelPage'])
    ->name('payment.cancel');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
