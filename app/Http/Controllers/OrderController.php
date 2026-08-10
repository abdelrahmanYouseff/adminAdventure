<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\OrderPaymentReceipt;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderPaymentReceiptService;
use App\Services\WorkerOrderSyncService;
use App\Support\OrderInsuranceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $currency = (string) $request->query('currency', 'all');
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;

        $query = Order::with([
            'user',
            'invoice',
            'products',
            'workerOrders' => fn ($q) => $q->orderBy('line_index'),
        ])
            ->withSum([
                'paymentReceipts as pending_payment_sum' => fn ($q) => $q
                    ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING),
            ], 'amount');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('payment_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('products', function ($productQuery) use ($search) {
                        $productQuery->where('product_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== 'all' && in_array($status, ['pending', 'processing', 'paid', 'cancelled', 'refunded'], true)) {
            $query->where('status', $status);
        }

        if ($currency !== 'all' && in_array($currency, ['SAR', 'USD', 'EUR'], true)) {
            $query->where('currency', $currency);
        }

        $user = $request->user();
        $canEditTime = $user && $user->hasAnyRole(
            User::ROLE_ADMIN,
            User::ROLE_GENERAL_MANAGER,
            User::ROLE_MANAGER,
        );
        $canSettle = (bool) $canEditTime;

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $orders->getCollection()->transform(function (Order $order) use ($canEditTime, $canSettle) {
            $rawTime = $order->getAttributes()['activity_time'] ?? null;
            $order->setAttribute(
                'activity_time',
                $rawTime ? \Carbon\Carbon::parse($rawTime)->format('H:i') : null
            );
            $order->setAttribute(
                'can_edit_activity_time',
                $canEditTime && blank($rawTime)
            );

            $pending = round((float) ($order->pending_payment_sum ?? 0), 2);
            $breakdown = $this->orderChargeBreakdown($order);
            $grandTotal = $breakdown['grand'];
            $due = round(max(
                0,
                $grandTotal - (float) ($order->amount_paid ?? 0)
            ), 2);
            $available = round(max(0, $due - $pending), 2);
            $order->setAttribute('settle_available', $available);
            $order->setAttribute('due_amount', $due);
            $order->setAttribute(
                'can_settle',
                $canSettle
                && $due > 0.009
                && ! in_array($order->status, ['cancelled', 'refunded'], true)
            );
            $order->setAttribute(
                'can_edit',
                $canEditTime && ! in_array($order->status, ['cancelled', 'refunded'], true)
            );
            $order->setAttribute('can_delete', $canEditTime);
            $order->setAttribute(
                'payment_url',
                $due > 0.009 && ! in_array($order->status, ['cancelled', 'refunded'], true)
                    ? url('/pay/order/'.$order->ensurePaymentToken())
                    : null
            );

            // Expose grand total (subtotal + VAT + insurance) so Index matches Edit.
            $order->setAttribute('total_amount', $grandTotal);
            $order->setAttribute('vat_amount', $breakdown['tax']);
            $order->setAttribute('tax_amount', $breakdown['tax']);

            $installMeta = $this->installationColumnMeta($order, (bool) $canEditTime);
            $order->setAttribute('installation', $installMeta);
            $order->setAttribute('dismantling', $this->dismantlingColumnMeta($order));

            return $order;
        });

        $statusCountsBase = Order::query()
            ->when($currency !== 'all' && in_array($currency, ['SAR', 'USD', 'EUR'], true), fn ($q) => $q->where('currency', $currency));

        $statusCounts = [
            'all' => (clone $statusCountsBase)->count(),
            'pending' => (clone $statusCountsBase)->where('status', 'pending')->count(),
            'processing' => (clone $statusCountsBase)->where('status', 'processing')->count(),
            'paid' => (clone $statusCountsBase)->where('status', 'paid')->count(),
            'cancelled' => (clone $statusCountsBase)->where('status', 'cancelled')->count(),
            'refunded' => (clone $statusCountsBase)->where('status', 'refunded')->count(),
        ];

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['all', 'pending', 'processing', 'paid', 'cancelled', 'refunded'], true) ? $status : 'all',
                'currency' => in_array($currency, ['all', 'SAR', 'USD', 'EUR'], true) ? $currency : 'all',
                'per_page' => $perPage,
            ],
            'statusCounts' => $statusCounts,
            'canManageActivityTime' => $canEditTime,
        ]);
    }


    public function show(Order $order)
    {
        $order->load(['user', 'invoice', 'products']);

        $breakdown = $this->orderChargeBreakdown($order);
        $order->setAttribute('total_amount', $breakdown['grand']);
        $order->setAttribute('tax_amount', $breakdown['tax']);
        $order->setAttribute(
            'remaining_amount',
            round(max(0, $breakdown['grand'] - (float) ($order->amount_paid ?? 0)), 2)
        );
        $order->setAttribute('payment_url', $order->noonPaymentUrl());

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }

    public function create()
    {
        $products = Product::query()
            ->active()
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'description', 'price', 'image', 'insurance_amount']);

        return Inertia::render('Orders/Create', [
            'products' => $products,
        ]);
    }

    public function edit(Order $order)
    {
        $user = request()->user();
        if (! $user || ! $user->hasAnyRole(User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER)) {
            abort(403);
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'لا يمكن تعديل طلب ملغي أو مسترد.');
        }

        $order->load(['products']);

        $products = Product::query()
            ->active()
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'description', 'price', 'image', 'insurance_amount']);

        $items = [];
        if (is_array($order->items) && $order->items !== []) {
            foreach ($order->items as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $price = round((float) ($item['price'] ?? $item['unit_price'] ?? 0), 2);
                $discount = round((float) ($item['discount_amount'] ?? 0), 2);
                $productId = ! empty($item['product_id']) ? (int) $item['product_id'] : null;
                $items[] = [
                    'product_id' => $productId,
                    'product_name' => (string) ($item['name'] ?? $item['product_name'] ?? 'صنف مخصص'),
                    'description' => (string) ($item['description'] ?? ''),
                    'statement' => (string) ($item['statement'] ?? ''),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount_amount' => $discount,
                    'total_price' => isset($item['amount'])
                        ? round((float) $item['amount'], 2)
                        : round($qty * max(0, $price - $discount), 2),
                ];
            }
        } elseif ($order->products->isNotEmpty()) {
            foreach ($order->products as $product) {
                $qty = (int) ($product->pivot->quantity ?? 0);
                $price = round((float) ($product->pivot->price ?? 0), 2);
                $discount = round((float) ($product->pivot->discount_amount ?? 0), 2);
                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'description' => $product->description ?? '',
                    'statement' => '',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount_amount' => $discount,
                    'total_price' => round($qty * max(0, $price - $discount), 2),
                ];
            }
        }

        $rawTime = $order->getAttributes()['activity_time'] ?? null;
        $breakdown = $this->orderChargeBreakdown($order);
        $pendingSum = round((float) $order->paymentReceipts()
            ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
            ->sum('amount'), 2);

        return Inertia::render('Orders/Edit', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'address' => $order->address,
                'activity_date' => $order->activity_date?->format('Y-m-d'),
                'activity_time' => $rawTime ? \Carbon\Carbon::parse($rawTime)->format('H:i') : null,
                'dismantling_at' => $order->dismantling_at?->format('Y-m-d\TH:i'),
                'currency' => $order->currency ?: 'SAR',
                'payment_method' => $order->payment_method ?: 'cash',
                'status' => $order->status,
                'notes' => $order->notes,
                'amount_paid' => (float) ($order->amount_paid ?? 0),
                'tax_amount' => $breakdown['tax'],
                'insurance_amount' => $breakdown['insurance'],
                'total_amount' => $breakdown['grand'],
                'settle_available' => round(max(
                    0,
                    $breakdown['grand']
                    - (float) ($order->amount_paid ?? 0)
                    - $pendingSum
                ), 2),
                'items' => $items,
            ],
            'products' => $products,
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER)) {
            abort(403);
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return back()->with('error', 'لا يمكن تعديل طلب ملغي أو مسترد.');
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'activity_date' => ['nullable', 'date'],
            'activity_time' => ['nullable', 'date_format:H:i'],
            'dismantling_at' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'in:SAR,USD,EUR'],
            'payment_method' => ['required', 'string', 'in:credit_card,cash,bank_transfer,paypal,noon'],
            'status' => ['required', 'string', 'in:pending,processing,paid,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'payment_proof' => ['nullable', 'array', 'max:10'],
            'payment_proof.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.statement' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:items.*.unit_price'],
        ], [
            'customer_name.required' => 'اسم العميل مطلوب.',
            'items.required' => 'يجب إضافة منتج واحد على الأقل.',
            'items.min' => 'يجب إضافة منتج واحد على الأقل.',
            'items.*.product_id.exists' => 'أحد المنتجات المحددة غير موجود.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
            'status.required' => 'حالة الطلب مطلوبة.',
            'amount_paid.min' => 'المبلغ المدفوع لا يمكن أن يكون سالباً.',
            'activity_time.date_format' => 'صيغة وقت الفعالية غير صحيحة.',
            'dismantling_at.date' => 'تاريخ الفك غير صالح.',
            'payment_proof.array' => 'مرفقات التحويل يجب أن تكون قائمة صور.',
            'payment_proof.max' => 'يمكن رفع 10 صور كحد أقصى.',
            'payment_proof.*.image' => 'مرفق التحويل يجب أن يكون صورة.',
            'payment_proof.*.mimes' => 'صيغ صورة التحويل المسموحة: jpg, jpeg, png, webp.',
            'payment_proof.*.max' => 'حجم صورة التحويل يجب ألا يتجاوز 5 ميجابايت.',
            'items.*.discount_amount.min' => 'خصم الوحدة لا يمكن أن يكون سالباً.',
            'items.*.discount_amount.lte' => 'خصم الوحدة لا يمكن أن يتجاوز سعر الوحدة.',
        ]);

        foreach ($validated['items'] as $index => $item) {
            $hasCatalog = ! empty($item['product_id']);
            $hasCustomName = filled($item['product_name'] ?? null);
            if (! $hasCatalog && ! $hasCustomName) {
                return back()
                    ->withInput()
                    ->withErrors([
                        "items.{$index}.product_name" => 'اسم الصنف مطلوب للمنتجات غير الموجودة في النظام.',
                    ]);
            }
        }

        $productIds = collect($validated['items'])
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $products = $productIds === []
            ? collect()
            : Product::whereIn('id', $productIds)->get(['id', 'product_name'])->keyBy('id');
        $insurance = OrderInsuranceCalculator::fromLines($validated['items']);
        $insuranceTotal = $insurance['total'];

        $itemsForOrder = [];
        $totalAmount = 0;
        $discountTotal = 0;

        foreach ($validated['items'] as $item) {
            $qty = (int) $item['quantity'];
            $price = round((float) $item['unit_price'], 2);
            $discountAmount = round((float) ($item['discount_amount'] ?? 0), 2);
            $lineTotal = round($qty * ($price - $discountAmount), 2);
            $totalAmount += $lineTotal;
            $discountTotal += round($qty * $discountAmount, 2);

            $productId = ! empty($item['product_id']) ? (int) $item['product_id'] : null;
            $catalog = $productId ? ($products[$productId] ?? null) : null;
            $name = $catalog
                ? (string) $catalog->product_name
                : trim((string) ($item['product_name'] ?? 'صنف مخصص'));

            $itemsForOrder[] = [
                'product_id' => $productId,
                'name' => $name,
                'description' => trim((string) ($item['description'] ?? '')),
                'statement' => trim((string) ($item['statement'] ?? '')),
                'quantity' => $qty,
                'price' => $price,
                'discount_amount' => $discountAmount,
                'amount' => $lineTotal,
                'insurance_amount' => $productId
                    ? (float) ($insurance['unit_by_product'][$productId] ?? 0)
                    : 0.0,
            ];
        }

        $chargeSubtotal = round($totalAmount, 2);
        $taxAmount = round($chargeSubtotal * 0.15, 2);
        $chargeAmount = round($chargeSubtotal + $taxAmount + $insuranceTotal, 2);

        $newPayment = array_key_exists('amount_paid', $validated) && $validated['amount_paid'] !== null
            ? round((float) $validated['amount_paid'], 2)
            : 0.0;

        $pendingSum = round((float) $order->paymentReceipts()
            ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
            ->sum('amount'), 2);
        $committed = round((float) ($order->amount_paid ?? 0) + $pendingSum, 2);

        if ($chargeAmount + 0.009 < $committed) {
            return back()
                ->withInput()
                ->withErrors([
                    'items' => 'إجمالي الطلب الجديد ('.$chargeAmount.') أقل من المبالغ المسجّلة/المعتمدة ('.$committed.').',
                ]);
        }

        $availableAfterUpdate = round(max(0, $chargeAmount - $committed), 2);
        if ($newPayment > $availableAfterUpdate + 0.009) {
            return back()
                ->withInput()
                ->withErrors([
                    'amount_paid' => 'مبلغ السداد أكبر من المتبقي غير المسجّل ('.$availableAfterUpdate.').',
                ]);
        }

        DB::transaction(function () use (
            $order,
            $validated,
            $itemsForOrder,
            $chargeAmount,
            $discountTotal,
            $taxAmount,
            $insurance,
            $insuranceTotal,
            $productIds,
            $committed,
        ) {
            $order->fill([
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'activity_date' => $validated['activity_date'] ?? null,
                'activity_time' => $validated['activity_time'] ?? $order->activity_time,
                'dismantling_at' => $validated['dismantling_at'] ?? null,
                'total_amount' => $chargeAmount,
                'discount_total' => $discountTotal,
                'tax_amount' => $taxAmount,
                'insurance_amount' => $insuranceTotal,
                'insurance_status' => $insuranceTotal > 0
                    ? ($order->insurance_status === 'none' ? 'pending' : $order->insurance_status)
                    : 'none',
                'currency' => $validated['currency'],
                'payment_method' => $validated['payment_method'],
                'items' => $itemsForOrder,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($validated['status'] === 'cancelled') {
                $order->status = 'cancelled';
            } elseif ($order->status !== 'paid') {
                $remaining = round(max(0, $chargeAmount - (float) ($order->amount_paid ?? 0)), 2);
                if ($remaining <= 0.009 && (float) ($order->amount_paid ?? 0) > 0) {
                    $order->status = 'paid';
                    $order->payment_status = 'paid';
                } elseif ($committed > 0 || (float) ($order->amount_paid ?? 0) > 0) {
                    $order->status = 'processing';
                } else {
                    $order->status = $validated['status'] === 'processing' ? 'processing' : 'pending';
                }
            }

            $order->save();

            if ($order->invoice_id) {
                Invoice::query()->whereKey($order->invoice_id)->update([
                    'amount' => $chargeAmount,
                    'brand_id' => Product::resolveBrandIdForIds($productIds),
                    'payment_method' => $validated['payment_method'],
                ]);
            }

            $order->products()->detach();
            foreach ($validated['items'] as $item) {
                $productId = ! empty($item['product_id']) ? (int) $item['product_id'] : null;
                if (! $productId) {
                    continue;
                }
                $order->products()->attach($productId, [
                    'quantity' => (int) $item['quantity'],
                    'price' => (float) $item['unit_price'],
                    'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                    'insurance_amount' => (float) ($insurance['unit_by_product'][$productId] ?? 0),
                ]);
            }

            app(\App\Services\OrderInvoiceService::class)->ensureFinalInvoice($order);
        });

        $successMessage = 'تم تحديث الطلب بنجاح.';

        if ($newPayment > 0.009) {
            $proofImages = $this->storePaymentProofImages($request);

            $accountNumber = isset($validated['account_number'])
                ? trim((string) $validated['account_number'])
                : null;
            if ($accountNumber === '') {
                $accountNumber = null;
            }

            try {
                $receipt = app(OrderPaymentReceiptService::class)->recordPayment(
                    $order->fresh(),
                    $newPayment,
                    $request->user(),
                    $validated['payment_method'],
                    'settlement',
                    'سداد من تعديل الطلب — بانتظار اعتماد المحاسب',
                    $proofImages !== [] ? $proofImages : null,
                    $accountNumber,
                );
                $successMessage = 'تم تحديث الطلب وتسجيل السداد في سند القبض '.$receipt->receipt_number.' وبانتظار اعتماد المحاسب.';
            } catch (\Throwable $e) {
                $this->deleteStoredPaymentProofs($proofImages);

                return redirect()
                    ->route('orders.edit', $order)
                    ->with('error', 'تم حفظ التعديلات لكن فشل تسجيل السداد: '.$e->getMessage());
            }
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', $successMessage);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'activity_date' => ['nullable', 'date'],
            'activity_time' => ['nullable', 'date_format:H:i'],
            'dismantling_at' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'in:SAR,USD,EUR'],
            'payment_method' => ['required', 'string', 'in:credit_card,cash,bank_transfer,paypal,noon'],
            'status' => ['required', 'string', 'in:pending,processing,paid,cancelled'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_proof' => ['nullable', 'array', 'max:10'],
            'payment_proof.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.statement' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:items.*.unit_price'],
        ], [
            'customer_name.required' => 'اسم العميل مطلوب.',
            'items.required' => 'يجب إضافة منتج واحد على الأقل.',
            'items.min' => 'يجب إضافة منتج واحد على الأقل.',
            'items.*.product_id.exists' => 'أحد المنتجات المحددة غير موجود.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
            'status.required' => 'حالة الطلب مطلوبة.',
            'amount_paid.min' => 'المبلغ المدفوع لا يمكن أن يكون سالباً.',
            'activity_time.date_format' => 'صيغة وقت الفعالية غير صحيحة.',
            'dismantling_at.date' => 'تاريخ الفك غير صالح.',
            'payment_proof.array' => 'مرفقات التحويل يجب أن تكون قائمة صور.',
            'payment_proof.max' => 'يمكن رفع 10 صور كحد أقصى.',
            'payment_proof.*.image' => 'مرفق التحويل يجب أن يكون صورة.',
            'payment_proof.*.mimes' => 'صيغ صورة التحويل المسموحة: jpg, jpeg, png, webp.',
            'payment_proof.*.max' => 'حجم صورة التحويل يجب ألا يتجاوز 5 ميجابايت.',
            'items.*.discount_amount.min' => 'خصم الوحدة لا يمكن أن يكون سالباً.',
            'items.*.discount_amount.lte' => 'خصم الوحدة لا يمكن أن يتجاوز سعر الوحدة.',
        ]);

        foreach ($validated['items'] as $index => $item) {
            $hasCatalog = ! empty($item['product_id']);
            $hasCustomName = filled($item['product_name'] ?? null);
            if (! $hasCatalog && ! $hasCustomName) {
                return back()
                    ->withInput()
                    ->withErrors([
                        "items.{$index}.product_name" => 'اسم الصنف مطلوب للمنتجات غير الموجودة في النظام.',
                    ]);
            }
        }

        $productIds = collect($validated['items'])
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $products = $productIds === []
            ? collect()
            : Product::whereIn('id', $productIds)->get(['id', 'product_name'])->keyBy('id');
        $insurance = OrderInsuranceCalculator::fromLines($validated['items']);
        $insuranceTotal = $insurance['total'];

        $itemsForOrder = [];
        $totalAmount = 0;
        $discountTotal = 0;

        foreach ($validated['items'] as $item) {
            $qty = (int) $item['quantity'];
            $price = round((float) $item['unit_price'], 2);
            $discountAmount = round((float) ($item['discount_amount'] ?? 0), 2);
            $lineTotal = round($qty * ($price - $discountAmount), 2);
            $totalAmount += $lineTotal;
            $discountTotal += round($qty * $discountAmount, 2);

            $productId = ! empty($item['product_id']) ? (int) $item['product_id'] : null;
            $catalog = $productId ? ($products[$productId] ?? null) : null;
            $name = $catalog
                ? (string) $catalog->product_name
                : trim((string) ($item['product_name'] ?? 'صنف مخصص'));

            $itemsForOrder[] = [
                'product_id' => $productId,
                'name' => $name,
                'description' => trim((string) ($item['description'] ?? '')),
                'statement' => trim((string) ($item['statement'] ?? '')),
                'quantity' => $qty,
                'price' => $price,
                'discount_amount' => $discountAmount,
                'amount' => $lineTotal,
                'insurance_amount' => $productId
                    ? (float) ($insurance['unit_by_product'][$productId] ?? 0)
                    : 0.0,
            ];
        }

        $chargeSubtotal = round($totalAmount, 2);
        $taxAmount = round($chargeSubtotal * 0.15, 2);
        $chargeAmount = round($chargeSubtotal + $taxAmount + $insuranceTotal, 2);
        $amountPaid = array_key_exists('amount_paid', $validated) && $validated['amount_paid'] !== null
            ? round((float) $validated['amount_paid'], 2)
            : ($validated['status'] === 'paid' ? $chargeAmount : 0.0);

        if ($amountPaid > $chargeAmount) {
            return back()
                ->withInput()
                ->withErrors(['amount_paid' => 'المبلغ المدفوع لا يمكن أن يتجاوز إجمالي الطلب.']);
        }

        $userId = $request->user()?->id ?? 1;

        // Payments collected by employees stay pending until an accountant
        // approves them, so the order starts with a zero approved balance and
        // never lands directly in work orders.
        $isCancelled = $validated['status'] === 'cancelled';
        $initialStatus = $isCancelled
            ? 'cancelled'
            : ($amountPaid > 0 ? 'processing' : 'pending');

        $order = DB::transaction(function () use (
            $validated,
            $itemsForOrder,
            $chargeAmount,
            $discountTotal,
            $taxAmount,
            $initialStatus,
            $userId,
            $insurance,
            $insuranceTotal,
        ) {
            $order = Order::create([
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'activity_date' => $validated['activity_date'] ?? null,
                'activity_time' => $validated['activity_time'] ?? null,
                'dismantling_at' => $validated['dismantling_at'] ?? null,
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => $chargeAmount,
                'discount_total' => $discountTotal,
                'tax_amount' => $taxAmount,
                'amount_paid' => 0,
                'insurance_amount' => $insuranceTotal,
                'insurance_status' => $insuranceTotal > 0 ? 'pending' : 'none',
                'currency' => $validated['currency'],
                'payment_method' => $validated['payment_method'],
                'status' => $initialStatus,
                'payment_status' => 'pending',
                'items' => $itemsForOrder,
                'notes' => $validated['notes'] ?? null,
                'user_id' => $userId,
            ]);

            foreach ($validated['items'] as $item) {
                $productId = ! empty($item['product_id']) ? (int) $item['product_id'] : null;
                if (! $productId) {
                    continue;
                }
                $order->products()->attach($productId, [
                    'quantity' => (int) $item['quantity'],
                    'price' => (float) $item['unit_price'],
                    'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                    'insurance_amount' => (float) ($insurance['unit_by_product'][$productId] ?? 0),
                ]);
            }

            return $order;
        });

        if ($amountPaid > 0) {
            $proofImages = $this->storePaymentProofImages($request);

            try {
                app(OrderPaymentReceiptService::class)->recordPayment(
                    $order->fresh(),
                    $amountPaid,
                    $request->user(),
                    $validated['payment_method'],
                    'initial',
                    'سند قبض عند إنشاء الطلب — بانتظار اعتماد المحاسب',
                    $proofImages !== [] ? $proofImages : null,
                );
            } catch (\Throwable $e) {
                $this->deleteStoredPaymentProofs($proofImages);

                throw $e;
            }
        }

        $successMessage = $amountPaid > 0
            ? 'تم إنشاء الطلب وتسجيل المبلغ المدفوع. سيصدر أمر العمل بعد اعتماد المحاسب للمبلغ من صفحة سندات القبض.'
            : 'تم إنشاء الطلب بنجاح. سيصدر أمر العمل بعد تسجيل واعتماد الدفعة من المحاسب.';

        return redirect()
            ->route('orders.show', $order)
            ->with('success', $successMessage);
    }

    public function settlePayment(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'in:credit_card,cash,bank_transfer,paypal,noon'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'payment_proof' => ['nullable', 'array', 'max:10'],
            'payment_proof.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'amount.required' => 'مبلغ السداد مطلوب.',
            'amount.min' => 'مبلغ السداد يجب أن يكون أكبر من صفر.',
            'payment_proof.array' => 'مرفقات التحويل يجب أن تكون قائمة صور.',
            'payment_proof.max' => 'يمكن رفع 10 صور كحد أقصى.',
            'payment_proof.*.image' => 'مرفق التحويل يجب أن يكون صورة.',
            'payment_proof.*.mimes' => 'صيغ صورة التحويل المسموحة: jpg, jpeg, png, webp.',
            'payment_proof.*.max' => 'حجم صورة التحويل يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        $pendingSum = round((float) $order->paymentReceipts()
            ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
            ->sum('amount'), 2);
        $committed = round((float) ($order->amount_paid ?? 0) + $pendingSum, 2);
        $grandTotal = $this->orderChargeBreakdown($order)['grand'];
        $available = round(max(0, $grandTotal - $committed), 2);
        $amount = round((float) $validated['amount'], 2);

        if ($available <= 0) {
            return back()->with('error', 'لا يوجد مبلغ متبقٍ غير مسجّل على هذا الطلب.');
        }

        if ($amount > $available) {
            return back()->withErrors(['amount' => 'مبلغ السداد أكبر من المتبقي غير المسجّل ('.$available.').']);
        }

        $proofImages = $this->storePaymentProofImages($request);

        $accountNumber = isset($validated['account_number'])
            ? trim((string) $validated['account_number'])
            : null;
        if ($accountNumber === '') {
            $accountNumber = null;
        }

        $notes = $validated['notes'] ?? 'سداد من قائمة الطلبات — بانتظار اعتماد المحاسب';

        try {
            $receipt = app(OrderPaymentReceiptService::class)->recordPayment(
                $order,
                $amount,
                $request->user(),
                $validated['payment_method'] ?? $order->payment_method,
                'settlement',
                $notes,
                $proofImages !== [] ? $proofImages : null,
                $accountNumber,
            );
        } catch (\Throwable $e) {
            $this->deleteStoredPaymentProofs($proofImages);

            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'تم تسجيل المبلغ في سند القبض '.$receipt->receipt_number.' وبانتظار اعتماد المحاسب.'
        );
    }

    public function latestPaymentReceiptPdf(Order $order): Response
    {
        $service = app(OrderPaymentReceiptService::class);
        $receipt = $order->paymentReceipts()
            ->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)
            ->latest('id')
            ->first();

        if (! $receipt) {
            $receipt = $service->ensureInitialReceipt($order, request()->user());
        }

        if (! $receipt) {
            abort(404, 'لا يوجد سند قبض معتمد لهذا الطلب.');
        }

        $pdf = $service->renderPdf($receipt);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipt->receipt_number.'.pdf"',
        ]);
    }

    public function paymentReceiptPdf(Order $order, OrderPaymentReceipt $receipt): Response
    {
        abort_unless((int) $receipt->order_id === (int) $order->id, 404);
        abort_unless($receipt->isApproved(), 403, 'لا يمكن عرض سند القبض قبل اعتماد المحاسب.');

        $pdf = app(OrderPaymentReceiptService::class)->renderPdf($receipt);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipt->receipt_number.'.pdf"',
        ]);
    }

    /**
     * API: Create a new order (and invoice).
     *
     * Required: customer_name, total_amount, currency, payment_method, and either items or product_items.
     */
    public function apiStore(Request $request)
    {
        try {
            $request->validate([
                'customer_name' => 'required|string|max:255',
                'customer_email' => 'nullable|email|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:1000',
                'activity_date' => 'nullable|date',
                'total_amount' => 'required|numeric|min:0',
                'currency' => 'required|string|in:SAR,USD,EUR',
                'payment_method' => 'required|string|in:credit_card,cash,bank_transfer,paypal,noon',
                'payment_id' => 'nullable|string|max:255',
                'status' => 'sometimes|string|in:pending,processing,paid,cancelled,refunded',
                'items' => 'required_without:product_items|array|min:1',
                'items.*.name' => 'required_with:items|string|max:255',
                'items.*.quantity' => 'required_with:items|integer|min:1',
                'items.*.price' => 'required_with:items|numeric|min:0',
                'product_items' => 'required_without:items|array|min:1',
                'product_items.*.product_id' => ['required_with:product_items', Product::storefrontExistsRule()],
                'product_items.*.quantity' => 'required_with:product_items|integer|min:1',
                'product_items.*.price' => 'required_with:product_items|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
                'user_id' => 'nullable|exists:users,id',
            ], [
                'product_items.*.product_id.exists' => 'أحد المنتجات غير متاح للطلب من التطبيق.',
            ]);

            $itemsForOrder = $request->items;
            $insuranceTotal = 0.0;
            $insuranceUnits = [];

            if ($request->has('product_items') && is_array($request->product_items)) {
                $productIds = array_column($request->product_items, 'product_id');
                $products = Product::whereIn('id', $productIds)->pluck('product_name', 'id');
                $insurance = OrderInsuranceCalculator::fromLines($request->product_items);
                $insuranceTotal = $insurance['total'];
                $insuranceUnits = $insurance['unit_by_product'];
                $itemsForOrder = [];
                foreach ($request->product_items as $productItem) {
                    $name = $products[$productItem['product_id']] ?? 'Product #' . $productItem['product_id'];
                    $qty = (int) $productItem['quantity'];
                    $price = (float) $productItem['price'];
                    $itemsForOrder[] = [
                        'name' => $name,
                        'quantity' => $qty,
                        'price' => $price,
                        'amount' => $price * $qty,
                        'insurance_amount' => (float) ($insuranceUnits[(int) $productItem['product_id']] ?? 0),
                    ];
                }
            }

            $chargeAmount = round((float) $request->total_amount + $insuranceTotal, 2);

            $orderData = [
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'address' => $request->address,
                'activity_date' => $request->activity_date,
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => $chargeAmount,
                'amount_paid' => $request->status === 'paid' ? $chargeAmount : 0,
                'insurance_amount' => $insuranceTotal,
                'insurance_status' => $insuranceTotal > 0 ? 'pending' : 'none',
                'currency' => $request->currency,
                'payment_method' => $request->payment_method,
                'payment_id' => $request->payment_id,
                'status' => $request->status ?? 'pending',
                'payment_status' => $request->status === 'paid' ? 'paid' : 'pending',
                'items' => $itemsForOrder,
                'notes' => $request->notes,
                'user_id' => $request->user_id ?? 1,
            ];

            $order = Order::create($orderData);

            if ($request->has('product_items') && is_array($request->product_items)) {
                foreach ($request->product_items as $productItem) {
                    $productId = (int) $productItem['product_id'];
                    $order->products()->attach($productId, [
                        'quantity' => (int) $productItem['quantity'],
                        'price' => (float) $productItem['price'],
                        'insurance_amount' => (float) ($insuranceUnits[$productId] ?? 0),
                    ]);
                }
            }

            $invoice = $request->status === 'paid'
                ? app(\App\Services\OrderInvoiceService::class)->ensureFinalInvoice($order)
                : null;

            return response()->json([
                'success' => true,
                'message' => $invoice
                    ? 'تم إنشاء الطلب والفاتورة النهائية بنجاح'
                    : 'تم إنشاء الطلب بنجاح، وستصدر الفاتورة بعد السداد الكامل',
                'data' => [
                    'order' => $order->load(['user', 'invoice', 'products']),
                    'invoice' => $invoice,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المرسلة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الطلب',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiIndex(Request $request)
    {
        $query = Order::with(['user', 'invoice']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('payment_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Payment method filter
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Currency filter
        if ($request->has('currency') && $request->currency !== 'all') {
            $query->where('currency', $request->currency);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * API: Get all orders for a specific user.
     * GET /api/users/{user_id}/orders
     * Optional query: ?status=paid&per_page=10
     */
    public function apiUserOrders(Request $request, int $userId)
    {
        $query = Order::with(['products', 'invoice'])
            ->where('user_id', $userId);

        // Filter by payment/order status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by payment_status (paid / pending / failed)
        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        $perPage = min((int) ($request->query('per_page', 15)), 50);
        $orders  = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $orders->getCollection()->transform(function (Order $order) {
            return $this->formatOrder($order);
        });

        return response()->json([
            'success' => true,
            'data'    => $orders->items(),
            'meta'    => [
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Format a single Order for the mobile API response.
     */
    private function formatOrder(Order $order): array
    {
        return [
            'id'                      => $order->id,
            'order_number'            => $order->order_number,
            'status'                  => $order->status,
            'payment_status'          => $order->payment_status ?? 'pending',
            'payment_method'          => $order->payment_method,
            'payment_order_reference' => $order->payment_order_reference,
            'total_amount'            => (float) $order->total_amount,
            'currency'                => $order->currency ?? 'SAR',
            'customer_name'           => $order->customer_name,
            'customer_email'          => $order->customer_email,
            'customer_phone'          => $order->customer_phone,
            'address'                 => $order->address,
            'activity_date'           => $order->activity_date?->format('Y-m-d'),
            'notes'                   => $order->notes,
            'items'                   => $order->items ?? [],
            'products'                => $order->products->map(fn ($p) => [
                'id'           => $p->id,
                'product_name' => $p->product_name,
                'price'        => (float) $p->pivot->price,
                'quantity'     => (int) $p->pivot->quantity,
                'subtotal'     => (float) ($p->pivot->price * $p->pivot->quantity),
            ])->values()->toArray(),
            'invoice'   => $order->invoice ? [
                'id'             => $order->invoice->id,
                'invoice_number' => $order->invoice->invoice_number,
                'amount'         => (float) $order->invoice->amount,
                'status'         => $order->invoice->status,
                'issued_at'      => $order->invoice->issued_at?->format('Y-m-d H:i:s'),
            ] : null,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $order->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    public function apiShow(Order $order)
    {
        $order->load(['user', 'invoice', 'products']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatOrder($order),
        ]);
    }

    /**
     * API endpoint to update order status
     */
    public function apiUpdateStatus(Order $order, Request $request)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,processing,paid,cancelled,refunded',
            ]);

            if ($request->status === 'paid' && $order->remaining_amount > 0.009) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن اعتبار الطلب مدفوعاً قبل سداد كامل المبلغ المستحق.',
                ], 422);
            }

            $order->update([
                'status' => $request->status,
                'payment_status' => $request->status === 'paid'
                    ? 'paid'
                    : $order->payment_status,
            ]);

            if ($request->status === 'paid') {
                app(\App\Services\OrderInvoiceService::class)->ensureFinalInvoice($order);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الطلب بنجاح',
                'data' => $order->load(['user', 'invoice']),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المرسلة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة الطلب',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Delete an order (soft/hard depending on model). Invoice is not deleted.
     */
    public function apiDestroy(Order $order)
    {
        try {
            $order->products()->detach();
            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الطلب بنجاح',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل حذف الطلب',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateActivityTime(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user && $user->hasAnyRole(User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER),
            403
        );

        if (! blank($order->getAttributes()['activity_time'] ?? null)) {
            return back()->with('error', 'وقت الفعالية محدد مسبقاً لهذا الطلب.');
        }

        $validated = $request->validate([
            'activity_time' => ['required', 'date_format:H:i'],
        ], [
            'activity_time.required' => 'وقت الفعالية مطلوب.',
            'activity_time.date_format' => 'صيغة وقت الفعالية غير صحيحة.',
        ]);

        $order->activity_time = $validated['activity_time'];
        $order->save();

        $display = \Carbon\Carbon::createFromFormat('H:i', $validated['activity_time'])->format('g:i A');

        return back()->with(
            'success',
            'تم تحديد وقت الفعالية للطلب '.$order->order_number.' إلى '.$display.'.'
        );
    }

    public function updateStatus(Order $order, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,paid,cancelled,refunded',
        ]);

        if ($request->status === 'paid' && $order->remaining_amount > 0.009) {
            return back()->withErrors([
                'status' => 'لا يمكن اعتبار الطلب مدفوعاً قبل سداد كامل المبلغ المستحق.',
            ]);
        }

        $order->update([
            'status' => $request->status,
            'payment_status' => $request->status === 'paid'
                ? 'paid'
                : $order->payment_status,
        ]);

        if ($request->status === 'paid') {
            app(\App\Services\OrderInvoiceService::class)->ensureFinalInvoice($order);
        }

        return redirect()->back()->with('success', 'Order status updated successfully');
    }

    /**
     * Delete an order
     */
    public function destroy(Order $order)
    {
        $user = request()->user();
        if (! $user || ! $user->hasAnyRole(User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER)) {
            abort(403);
        }

        try {
            $order->delete();

            return redirect()
                ->route('orders.index')
                ->with('success', 'تم حذف الطلب '.$order->order_number.' بنجاح');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'فشل حذف الطلب: '.$e->getMessage());
        }
    }

    /**
     * Charge breakdown matching Orders/Edit: subtotal + 15% VAT + insurance.
     *
     * @return array{subtotal: float, tax: float, insurance: float, grand: float}
     */
    private function orderChargeBreakdown(Order $order): array
    {
        $subtotal = 0.0;

        // Prefer JSON line items so custom (non-catalog) products are included.
        if (is_array($order->items) && $order->items !== []) {
            foreach ($order->items as $item) {
                if (isset($item['amount'])) {
                    $subtotal += (float) $item['amount'];
                } else {
                    $qty = (int) ($item['quantity'] ?? 0);
                    $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                    $discount = (float) ($item['discount_amount'] ?? 0);
                    $subtotal += $qty * max(0, $price - $discount);
                }
            }
        } elseif ($order->relationLoaded('products') && $order->products->isNotEmpty()) {
            foreach ($order->products as $product) {
                $qty = (int) ($product->pivot->quantity ?? 0);
                $price = (float) ($product->pivot->price ?? 0);
                $discount = (float) ($product->pivot->discount_amount ?? 0);
                $subtotal += $qty * max(0, $price - $discount);
            }
        }

        $subtotal = round($subtotal, 2);
        $insurance = round((float) ($order->insurance_amount ?? 0), 2);
        $tax = round((float) ($order->tax_amount ?? 0), 2);

        if ($tax <= 0.009 && $subtotal > 0) {
            $tax = round($subtotal * 0.15, 2);
        }

        $storedTotal = round((float) $order->total_amount, 2);
        $netPlusInsurance = round($subtotal + $insurance, 2);
        $grandFromParts = round($subtotal + $tax + $insurance, 2);

        if ($subtotal <= 0.009) {
            $grand = $storedTotal;
        } elseif (abs($storedTotal - $netPlusInsurance) <= 0.05) {
            // Stored total is net only (tax omitted) — add VAT like Edit.
            $grand = $grandFromParts;
        } elseif (abs($storedTotal - $grandFromParts) <= 0.05) {
            $grand = $storedTotal;
        } else {
            // Prefer the higher coherent charge so due/settle are not understated.
            $grand = max($storedTotal, $grandFromParts);
        }

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'insurance' => $insurance,
            'grand' => round($grand, 2),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     progress_done: int,
     *     progress_total: int,
     *     approved_at: string|null,
     *     has_photos: bool,
     *     can_review_photos: bool,
     *     photos: list<array{product_name: string, url: string}>
     * }
     */
    private function installationColumnMeta(Order $order, bool $canReviewPhotos): array
    {
        $lines = $order->relationLoaded('workerOrders')
            ? $order->workerOrders
            : $order->workerOrders()->orderBy('line_index')->get();

        $total = $lines->count();
        $done = $lines->where('status', 'completed')->count();
        $isApproved = filled($order->work_order_approved_at);

        $photos = $lines
            ->filter(fn ($line) => filled($line->installation_photo))
            ->map(fn ($line) => [
                'product_name' => (string) $line->product_name,
                'url' => (string) $line->installation_photo_url,
            ])
            ->values()
            ->all();

        if ($total === 0) {
            $status = 'none';
            $label = '—';
        } elseif ($isApproved) {
            $status = 'completed';
            $label = 'تم التركيب';
        } elseif ($done > 0) {
            $status = 'in_progress';
            $label = "قيد التركيب ({$done}/{$total})";
        } else {
            $status = 'pending';
            $label = 'بانتظار التركيب';
        }

        return [
            'status' => $status,
            'label' => $label,
            'progress_done' => $done,
            'progress_total' => $total,
            'approved_at' => $order->work_order_approved_at?->toIso8601String(),
            'has_photos' => $photos !== [],
            'can_review_photos' => $canReviewPhotos && $photos !== [],
            'photos' => $photos,
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     scheduled_at: string|null,
     *     progress_done: int,
     *     progress_total: int
     * }
     */
    private function dismantlingColumnMeta(Order $order): array
    {
        $lines = $order->relationLoaded('workerOrders')
            ? $order->workerOrders
            : $order->workerOrders()->orderBy('line_index')->get();

        $total = $lines->count();
        $pickedUp = $lines->filter(fn ($line) => filled($line->pickup_photo))->count();
        $scheduledAt = $order->dismantling_at?->format('Y-m-d H:i');

        if (filled($order->warehouse_returned_at)) {
            return [
                'status' => 'returned',
                'label' => 'تم الفك والاسترجاع',
                'scheduled_at' => $scheduledAt,
                'progress_done' => $pickedUp,
                'progress_total' => $total,
            ];
        }

        if (filled($order->warehouse_rejected_at)) {
            $reason = trim((string) ($order->warehouse_rejection_reason ?? ''));

            return [
                'status' => 'rejected',
                'label' => $reason !== '' ? 'تم الرفض — '.$reason : 'تم الرفض',
                'scheduled_at' => $scheduledAt,
                'progress_done' => $pickedUp,
                'progress_total' => $total,
            ];
        }

        if ($total === 0) {
            return [
                'status' => 'none',
                'label' => '—',
                'scheduled_at' => $scheduledAt,
                'progress_done' => 0,
                'progress_total' => 0,
            ];
        }

        if ($pickedUp >= $total) {
            $status = 'completed';
            $label = 'تم الفك';
        } elseif ($pickedUp > 0) {
            $status = 'in_progress';
            $label = "قيد الفك ({$pickedUp}/{$total})";
        } elseif (filled($order->work_order_approved_at) || $lines->where('status', 'completed')->isNotEmpty()) {
            $status = 'pending';
            $label = 'بانتظار الفك';
        } else {
            $status = 'waiting_install';
            $label = 'بعد التركيب';
        }

        return [
            'status' => $status,
            'label' => $label,
            'scheduled_at' => $scheduledAt,
            'progress_done' => $pickedUp,
            'progress_total' => $total,
        ];
    }

    /**
     * @return list<string>
     */
    private function storePaymentProofImages(Request $request): array
    {
        if (! $request->hasFile('payment_proof')) {
            return [];
        }

        $files = $request->file('payment_proof');
        if (! is_array($files)) {
            $files = [$files];
        }

        $paths = [];
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $paths[] = $file->store('payment-proofs', 'public');
        }

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteStoredPaymentProofs(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }
}

