<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyClient;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Product;
use App\Models\User;
use App\Services\QuotationPdfService;
use App\Support\OrderInsuranceCalculator;
use App\Support\QuotationPdfData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class QuotationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $brandId = $request->query('brand');
            $search = trim((string) $request->query('search', ''));
            $status = (string) $request->query('status', 'all');
            $perPage = (int) $request->query('per_page', 15);
            $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
            $allowedStatuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

            $query = Quotation::with(['user', 'items', 'brand'])
                ->when($brandId, fn ($q) => $q->where('brand_id', $brandId));

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('quotation_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('customer_name', 'like', "%{$search}%");
                        });
                });
            }

            if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
                $query->where('status', $status);
            }

            $quotations = $query->orderBy('created_at', 'desc')
                ->paginate($perPage)
                ->withQueryString();

            $statusCountsBase = Quotation::query()
                ->when($brandId, fn ($q) => $q->where('brand_id', $brandId));

            $statusCounts = [
                'all' => (clone $statusCountsBase)->count(),
                'draft' => (clone $statusCountsBase)->where('status', 'draft')->count(),
                'sent' => (clone $statusCountsBase)->where('status', 'sent')->count(),
                'accepted' => (clone $statusCountsBase)->where('status', 'accepted')->count(),
                'rejected' => (clone $statusCountsBase)->where('status', 'rejected')->count(),
                'expired' => (clone $statusCountsBase)->where('status', 'expired')->count(),
            ];

            $brands = Brand::query()
                ->withCount('quotations')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);

            return Inertia::render('Quotations/Index', [
                'quotations' => $quotations,
                'brands' => $brands,
                'selectedBrandId' => $brandId ? (int) $brandId : null,
                'filters' => [
                    'search' => $search,
                    'status' => in_array($status, array_merge(['all'], $allowedStatuses), true) ? $status : 'all',
                    'per_page' => $perPage,
                ],
                'statusCounts' => $statusCounts,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in quotation index: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to load quotations.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->withCount([
                'products' => fn ($query) => $query->where('status', 'active'),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'logo', 'is_active']);

        $brandId = $request->query('brand');
        $selectedBrand = $brandId
            ? Brand::query()->where('is_active', true)->find($brandId)
            : null;

        $products = collect();
        $categories = collect();

        if ($selectedBrand) {
            $products = Product::query()
                ->where('status', 'active')
                ->where('brand_id', $selectedBrand->id)
                ->with('category:id,category_name')
                ->orderBy('product_name')
                ->get(['id', 'product_name', 'description', 'price', 'insurance_amount', 'category_id', 'brand_id']);

            $categories = Category::query()
                ->where('brand_id', $selectedBrand->id)
                ->whereHas('products', fn ($q) => $q->where('status', 'active')->where('brand_id', $selectedBrand->id))
                ->orderBy('category_name')
                ->get(['id', 'category_name', 'brand_id']);
        }

        return Inertia::render('Quotations/Create', [
            'brands' => $brands,
            'selectedBrand' => $selectedBrand,
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Lookup customer details by phone for quotation autofill.
     */
    public function lookupCustomer(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
        ]);

        $variants = $this->phoneLookupVariants($validated['phone']);

        if ($variants === []) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الجوال غير مكتمل.',
                'customer' => null,
            ]);
        }

        $companyClient = CompanyClient::query()
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('phone', $variant);
                }
            })
            ->orderByDesc('updated_at')
            ->first();

        if ($companyClient) {
            $displayName = $companyClient->company_name;
            if ($companyClient->contact_name) {
                $displayName = $companyClient->company_name.' — '.$companyClient->contact_name;
            }

            return response()->json([
                'success' => true,
                'message' => 'تم العثور على عميل شركة.',
                'customer' => [
                    'customer_name' => $displayName,
                    'customer_email' => $companyClient->email,
                    'customer_phone' => $this->normalizePhoneForForm($companyClient->phone ?: $validated['phone']),
                    'customer_address' => $companyClient->address,
                    'company_tax_number' => $companyClient->tax_number,
                    'customer_type' => 'company',
                    'source' => 'company_client',
                ],
            ]);
        }

        $user = User::query()
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('phone', $variant);
                }
            })
            ->orderByDesc('updated_at')
            ->first();

        if ($user) {
            $latestQuotation = Quotation::query()
                ->where(function ($query) use ($variants) {
                    foreach ($variants as $variant) {
                        $query->orWhere('customer_phone', $variant);
                    }
                })
                ->whereNotNull('customer_address')
                ->where('customer_address', '!=', '')
                ->latest()
                ->first();

            $latestOrder = Order::query()
                ->where(function ($query) use ($variants) {
                    foreach ($variants as $variant) {
                        $query->orWhere('customer_phone', $variant);
                    }
                })
                ->whereNotNull('address')
                ->where('address', '!=', '')
                ->latest()
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'تم العثور على العميل.',
                'customer' => [
                    'customer_name' => $user->customer_name ?: ($latestQuotation?->customer_name ?? $latestOrder?->customer_name),
                    'customer_email' => $user->email ?: ($latestQuotation?->customer_email ?? $latestOrder?->customer_email),
                    'customer_phone' => $this->normalizePhoneForForm($user->phone ?: $validated['phone']),
                    'customer_address' => $latestQuotation?->customer_address
                        ?: $latestOrder?->address
                        ?: null,
                    'company_tax_number' => null,
                    'customer_type' => 'individual',
                    'source' => 'user',
                ],
            ]);
        }

        $quotation = Quotation::query()
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('customer_phone', $variant);
                }
            })
            ->latest()
            ->first();

        if ($quotation) {
            return response()->json([
                'success' => true,
                'message' => 'تم العثور على بيانات من عرض سعر سابق.',
                'customer' => [
                    'customer_name' => $quotation->customer_name,
                    'customer_email' => $quotation->customer_email,
                    'customer_phone' => $this->normalizePhoneForForm($quotation->customer_phone),
                    'customer_address' => $quotation->customer_address,
                    'company_tax_number' => $quotation->company_tax_number,
                    'customer_type' => filled($quotation->company_tax_number) ? 'company' : 'individual',
                    'source' => 'quotation',
                ],
            ]);
        }

        $order = Order::query()
            ->where(function ($query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('customer_phone', $variant);
                }
            })
            ->latest()
            ->first();

        if ($order) {
            return response()->json([
                'success' => true,
                'message' => 'تم العثور على بيانات من طلب سابق.',
                'customer' => [
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'customer_phone' => $this->normalizePhoneForForm($order->customer_phone),
                    'customer_address' => $order->address,
                    'company_tax_number' => null,
                    'customer_type' => 'individual',
                    'source' => 'order',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'لا يوجد عميل بهذا الرقم.',
            'customer' => null,
        ]);
    }

    /**
     * @return list<string>
     */
    private function phoneLookupVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return [];
        }

        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $local = substr($digits, 1);
        } else {
            $local = $digits;
        }

        if ($local === '') {
            return [];
        }

        $variants = [
            $local,
            '0'.$local,
            '966'.$local,
            '+966'.$local,
        ];

        return array_values(array_unique(array_filter($variants)));
    }

    private function normalizePhoneForForm(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'company_tax_number' => 'nullable|string|max:100',
            'valid_until' => 'required|date|after:today',
            'activity_at' => 'nullable|date',
            'installation_at' => 'nullable|date',
            'dismantling_at' => 'nullable|date|after_or_equal:installation_at',
            'insurance_amount' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query->where('brand_id', $request->input('brand_id'))
                ),
            ],
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.description' => 'nullable|string|max:2000',
            'items.*.statement' => 'nullable|string|max:2000',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0|lte:items.*.unit_price',
        ], [
            'brand_id.required' => 'يجب اختيار البراند أولاً.',
            'brand_id.exists' => 'البراند المحدد غير موجود.',
            'customer_name.required' => 'اسم العميل مطلوب.',
            'customer_name.max' => 'اسم العميل طويل جداً.',
            'customer_email.email' => 'البريد الإلكتروني غير صالح.',
            'valid_until.required' => 'تاريخ الصلاحية مطلوب.',
            'valid_until.date' => 'تاريخ الصلاحية غير صالح.',
            'valid_until.after' => 'تاريخ الصلاحية يجب أن يكون بعد اليوم.',
            'activity_at.date' => 'تاريخ الفعالية غير صالح.',
            'installation_at.date' => 'تاريخ التركيب غير صالح.',
            'dismantling_at.date' => 'تاريخ الفك غير صالح.',
            'dismantling_at.after_or_equal' => 'تاريخ الفك يجب أن يكون بعد أو يساوي تاريخ التركيب.',
            'insurance_amount.numeric' => 'مبلغ التأمين غير صالح.',
            'insurance_amount.min' => 'مبلغ التأمين لا يمكن أن يكون سالباً.',
            'amount_paid.numeric' => 'المبلغ المدفوع غير صالح.',
            'amount_paid.min' => 'المبلغ المدفوع لا يمكن أن يكون سالباً.',
            'items.required' => 'يجب إضافة منتج واحد على الأقل.',
            'items.min' => 'يجب إضافة منتج واحد على الأقل.',
            'items.*.product_id.exists' => 'المنتج المحدد لا ينتمي إلى البراند المختار.',
            'items.*.product_name.max' => 'اسم الصنف طويل جداً.',
            'items.*.description.max' => 'الوصف طويل جداً.',
            'items.*.statement.max' => 'البيان طويل جداً.',
            'items.*.quantity.required' => 'الكمية مطلوبة.',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل.',
            'items.*.unit_price.required' => 'سعر الوحدة مطلوب.',
            'items.*.unit_price.min' => 'سعر الوحدة لا يمكن أن يكون سالباً.',
            'items.*.discount_amount.numeric' => 'مبلغ الخصم غير صالح.',
            'items.*.discount_amount.min' => 'مبلغ الخصم لا يمكن أن يكون سالباً.',
            'items.*.discount_amount.lte' => 'خصم الوحدة لا يمكن أن يتجاوز سعر الوحدة.',
        ]);

        foreach ($request->items as $index => $item) {
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

        try {
            DB::beginTransaction();

            $quotation = Quotation::create([
                'brand_id' => $request->brand_id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'company_tax_number' => $request->input('company_tax_number') ?: null,
                'valid_until' => $request->valid_until,
                'activity_at' => $request->input('activity_at') ?: null,
                'installation_at' => $request->input('installation_at') ?: null,
                'dismantling_at' => $request->input('dismantling_at') ?: null,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
            ]);

            $subtotal = 0;
            $discountTotal = 0;
            foreach ($request->items as $item) {
                $productId = ! empty($item['product_id']) ? (int) $item['product_id'] : null;
                $product = $productId ? Product::find($productId) : null;
                $quantity = (int) $item['quantity'];
                $unitPrice = round((float) $item['unit_price'], 2);
                $discountAmount = round((float) ($item['discount_amount'] ?? 0), 2);
                $totalPrice = round($quantity * ($unitPrice - $discountAmount), 2);
                $subtotal += $totalPrice;
                $discountTotal += round($quantity * $discountAmount, 2);

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $productId,
                    'product_name' => $product
                        ? $product->product_name
                        : trim((string) ($item['product_name'] ?? 'صنف مخصص')),
                    'description' => $product
                        ? $product->description
                        : ($item['description'] ?? null),
                    'statement' => trim((string) ($item['statement'] ?? '')) ?: null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'total_price' => $totalPrice,
                ]);
            }

            $insuranceAmount = $request->filled('insurance_amount')
                ? round((float) $request->insurance_amount, 2)
                : OrderInsuranceCalculator::fromLines($request->items)['total'];

            $taxAmount = round($subtotal * 0.15, 2);
            $totalAmount = round($subtotal + $taxAmount + $insuranceAmount, 2);
            $amountPaid = round((float) ($request->input('amount_paid') ?? 0), 2);
            $amountPaid = max(0, min($amountPaid, $totalAmount));

            $quotation->update([
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_amount' => $taxAmount,
                'insurance_amount' => $insuranceAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
            ]);

            DB::commit();

            $paymentSyncMessage = '';
            if ($amountPaid > 0) {
                try {
                    $sync = app(\App\Services\QuotationToOrderService::class)
                        ->syncPaymentFromQuotation($quotation->fresh(['items']), $request->user());

                    if ($sync['created_receipt'] && $sync['receipt']) {
                        $paymentSyncMessage = ' وتم إنشاء سند القبض '.$sync['receipt']->receipt_number.' بانتظار اعتماد المحاسب.';
                    } elseif ($sync['created_order']) {
                        $paymentSyncMessage = ' وتم تجهيز الطلب المرتبط وبانتظار اعتماد سند القبض.';
                    }
                } catch (\Throwable $e) {
                    Log::error('Quotation payment sync failed on store: '.$e->getMessage(), [
                        'quotation_id' => $quotation->id,
                    ]);
                    $paymentSyncMessage = ' لكن تعذر إنشاء سند القبض تلقائياً: '.$e->getMessage();
                }
            }

            return redirect()->route('quotations.index')
                ->with('success', 'تم إنشاء عرض السعر بنجاح.'.$paymentSyncMessage)
                ->with('open_pdf', $quotation->id);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quotation store failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'error' => 'فشل إنشاء عرض السعر: '.$e->getMessage(),
                ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Quotation $quotation)
    {
        try {
            $pdfUrl = '/quotations/'.$quotation->id.'/pdf';

            if ($request->header('X-Inertia')) {
                return Inertia::location($pdfUrl);
            }

            return redirect()->to($pdfUrl);
        } catch (\Exception $e) {
            Log::error('Error in quotation show: ' . $e->getMessage());
            return back()->withErrors(['error' => 'فشل تحميل عرض السعر.']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quotation $quotation)
    {
        $quotation->load(['items.product', 'brand']);

        $brandId = $quotation->brand_id;

        $products = Product::query()
            ->where('status', 'active')
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->with('category:id,category_name')
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'description', 'price', 'insurance_amount', 'category_id', 'brand_id']);

        $categories = Category::query()
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->whereHas('products', function ($q) use ($brandId) {
                $q->where('status', 'active');
                if ($brandId) {
                    $q->where('brand_id', $brandId);
                }
            })
            ->orderBy('category_name')
            ->get(['id', 'category_name', 'brand_id']);

        return Inertia::render('Quotations/Edit', [
            'quotation' => $quotation,
            'products' => $products,
            'categories' => $categories,
            'selectedBrand' => $quotation->brand,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Quotation $quotation)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'company_tax_number' => 'nullable|string|max:100',
            'valid_until' => 'required|date',
            'activity_at' => 'nullable|date',
            'installation_at' => 'nullable|date',
            'dismantling_at' => 'nullable|date|after_or_equal:installation_at',
            'insurance_amount' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.description' => 'nullable|string|max:2000',
            'items.*.statement' => 'nullable|string|max:2000',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0|lte:items.*.unit_price',
        ]);

        foreach ($request->items as $index => $item) {
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

        try {
            DB::beginTransaction();

            $quotation->update([
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'company_tax_number' => $request->input('company_tax_number') ?: null,
                'valid_until' => $request->valid_until,
                'activity_at' => $request->input('activity_at') ?: null,
                'installation_at' => $request->input('installation_at') ?: null,
                'dismantling_at' => $request->input('dismantling_at') ?: null,
                'notes' => $request->notes,
            ]);

            // Delete existing items
            $quotation->items()->delete();

            $subtotal = 0;
            $discountTotal = 0;
            foreach ($request->items as $item) {
                $productId = ! empty($item['product_id']) ? (int) $item['product_id'] : null;
                $product = $productId ? Product::find($productId) : null;
                $quantity = (int) $item['quantity'];
                $unitPrice = round((float) $item['unit_price'], 2);
                $discountAmount = round((float) ($item['discount_amount'] ?? 0), 2);
                $totalPrice = round($quantity * ($unitPrice - $discountAmount), 2);
                $subtotal += $totalPrice;
                $discountTotal += round($quantity * $discountAmount, 2);

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $productId,
                    'product_name' => $product
                        ? $product->product_name
                        : trim((string) ($item['product_name'] ?? 'صنف مخصص')),
                    'description' => $product
                        ? $product->description
                        : ($item['description'] ?? null),
                    'statement' => trim((string) ($item['statement'] ?? '')) ?: null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'total_price' => $totalPrice,
                ]);
            }

            $insuranceAmount = $request->filled('insurance_amount')
                ? round((float) $request->insurance_amount, 2)
                : OrderInsuranceCalculator::fromLines($request->items)['total'];

            $taxAmount = round($subtotal * 0.15, 2);
            $totalAmount = round($subtotal + $taxAmount + $insuranceAmount, 2);
            $amountPaid = round((float) ($request->input('amount_paid') ?? 0), 2);
            $amountPaid = max(0, min($amountPaid, $totalAmount));

            $quotation->update([
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_amount' => $taxAmount,
                'insurance_amount' => $insuranceAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
            ]);

            DB::commit();

            $paymentSyncMessage = '';
            if ($amountPaid > 0) {
                try {
                    $sync = app(\App\Services\QuotationToOrderService::class)
                        ->syncPaymentFromQuotation($quotation->fresh(['items']), $request->user());

                    if ($sync['created_receipt'] && $sync['receipt']) {
                        $paymentSyncMessage = ' وتم إنشاء/تحديث سند القبض '.$sync['receipt']->receipt_number.' بانتظار اعتماد المحاسب.';
                    } elseif ($sync['order']) {
                        $paymentSyncMessage = ' وسند القبض المرتبط جاهز لاعتماد المحاسب.';
                    }
                } catch (\Throwable $e) {
                    Log::error('Quotation payment sync failed on update: '.$e->getMessage(), [
                        'quotation_id' => $quotation->id,
                    ]);
                    $paymentSyncMessage = ' لكن تعذر مزامنة سند القبض تلقائياً: '.$e->getMessage();
                }
            }

            return redirect()->route('quotations.index')
                ->with('success', 'تم تحديث عرض السعر بنجاح.'.$paymentSyncMessage)
                ->with('open_pdf', $quotation->id);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'فشل تحديث عرض السعر.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quotation $quotation)
    {
        try {
            $quotation->delete();
            return redirect()->route('quotations.index')
                ->with('success', 'Quotation deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete quotation.']);
        }
    }

    /**
     * Generate PDF for the quotation
     */
    public function generatePdf(Quotation $quotation, QuotationPdfService $pdfService)
    {
        $data = QuotationPdfData::fromQuotation($quotation);
        $content = $pdfService->render($data);
        $filename = 'quotation-'.$quotation->quotation_number.'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Update quotation status
     */
    public function updateStatus(Request $request, Quotation $quotation)
    {
        $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
        ]);

        $quotation->update(['status' => $request->status]);

        return back()->with('success', 'تم تحديث حالة عرض السعر بنجاح.');
    }
}
