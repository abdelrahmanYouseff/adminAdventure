<?php

namespace App\Http\Controllers\MainApp;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyClient;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\Package;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Models\WhatsappNotificationRecipient;
use App\Support\MainAppModules;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Modules', [
            'modules' => MainAppModules::forUser($user),
            'user' => [
                'name' => $user->customer_name ?: $user->name ?: $user->email,
                'role_label' => $user->roleLabel(),
            ],
        ]);
    }

    public function show(Request $request, string $module): Response
    {
        $user = $request->user();
        $meta = MainAppModules::findForUser($module, $user);

        abort_unless($meta !== null, 403, 'ليست لديك صلاحية لهذه الوحدة.');

        $search = trim((string) $request->query('search', ''));
        $pageData = $this->resolveModuleData($module, $search, $user);

        return Inertia::render('ModuleShow', [
            'module' => $meta,
            'search' => $search,
            'stats' => $pageData['stats'] ?? [],
            'items' => $pageData['items'] ?? [],
            'empty_message' => $pageData['empty_message'] ?? 'لا توجد عناصر حالياً.',
            'desktop_path' => $meta['desktop_path'],
            'can_open_desktop' => true,
        ]);
    }

    public function more(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('More', [
            'user' => [
                'id' => $user->id,
                'name' => $user->customer_name ?: $user->name ?: $user->email,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => $user->roleLabel(),
            ],
        ]);
    }

    /**
     * @return array{stats: list<array{label: string, value: int|string}>, items: list<array<string, mixed>>, empty_message?: string}
     */
    private function resolveModuleData(string $key, string $search, User $user): array
    {
        return match ($key) {
            'dashboard' => $this->dashboardData(),
            'products' => $this->productsData($search),
            'returns' => $this->returnsData($search),
            'categories' => $this->categoriesData($search),
            'brands' => $this->brandsData($search),
            'packages' => $this->packagesData($search),
            'orders' => $this->ordersData($search),
            'payment-receipts' => $this->paymentReceiptsData($search),
            'worker-orders' => $this->workerOrdersData($search),
            'customers' => $this->customersData($search),
            'users' => $this->usersData($search),
            'invoices' => $this->invoicesData($search),
            'insurance-deposits' => $this->insuranceData($search),
            'quotations' => $this->quotationsData($search),
            'whatsapp' => $this->whatsappData(),
            default => ['stats' => [], 'items' => []],
        };
    }

    private function dashboardData(): array
    {
        return [
            'stats' => [
                ['label' => 'المنتجات', 'value' => Product::query()->count()],
                ['label' => 'الطلبات', 'value' => Order::query()->count()],
                ['label' => 'الفواتير', 'value' => Invoice::query()->count()],
                ['label' => 'عروض الأسعار', 'value' => Quotation::query()->count()],
            ],
            'items' => Order::query()
                ->latest('id')
                ->limit(12)
                ->get(['id', 'order_number', 'customer_name', 'total_amount', 'status', 'currency', 'created_at'])
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'title' => $order->order_number,
                    'subtitle' => $order->customer_name ?: '—',
                    'meta' => number_format((float) $order->total_amount, 2).' '.($order->currency ?: 'SAR'),
                    'badge' => $order->status,
                    'badge_tone' => $order->status === 'paid' ? 'emerald' : 'amber',
                    'href' => '/orders/'.$order->id,
                ])
                ->all(),
            'empty_message' => 'لا توجد طلبات حديثة.',
        ];
    }

    private function productsData(string $search): array
    {
        $query = Product::query()->with('category:id,category_name')->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $items = $query->limit(40)->get()->map(fn (Product $product) => [
            'id' => $product->id,
            'title' => $product->product_name,
            'subtitle' => $product->category?->category_name ?: 'بدون صنف',
            'meta' => number_format((float) ($product->price ?? 0), 2).' SAR',
            'badge' => null,
            'badge_tone' => 'slate',
            'href' => '/products/'.$product->id.'/edit',
            'image' => $product->image_url ?? null,
        ])->all();

        return [
            'stats' => [
                ['label' => 'الإجمالي', 'value' => Product::query()->count()],
            ],
            'items' => $items,
        ];
    }

    private function returnsData(string $search): array
    {
        $query = Order::query()
            ->whereNotNull('work_order_approved_at')
            ->whereNull('warehouse_returned_at')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->with('warehouseReturnedBy:id,customer_name')
            ->latest('updated_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $pending = (clone $query)->whereNull('warehouse_returned_at')->count();

        $items = (clone $query)
            ->whereNull('warehouse_returned_at')
            ->limit(40)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'title' => $order->order_number,
                'subtitle' => $order->customer_name ?: '—',
                'meta' => 'بانتظار الاسترجاع',
                'badge' => 'معلّق',
                'badge_tone' => 'amber',
                'href' => '/returns',
            ])->all();

        return [
            'stats' => [
                ['label' => 'معلّق', 'value' => $pending],
            ],
            'items' => $items,
        ];
    }

    private function categoriesData(string $search): array
    {
        $query = Category::query()->with('brand:id,name')->latest('id');

        if ($search !== '') {
            $query->where('category_name', 'like', "%{$search}%");
        }

        return [
            'stats' => [['label' => 'الأصناف', 'value' => Category::query()->count()]],
            'items' => $query->limit(40)->get()->map(fn (Category $category) => [
                'id' => $category->id,
                'title' => $category->category_name,
                'subtitle' => $category->brand?->name ?: '—',
                'meta' => null,
                'badge' => null,
                'href' => '/categories',
            ])->all(),
        ];
    }

    private function brandsData(string $search): array
    {
        $query = Brand::query()->latest('id');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return [
            'stats' => [['label' => 'البراندات', 'value' => Brand::query()->count()]],
            'items' => $query->limit(40)->get()->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'title' => $brand->name,
                'subtitle' => $brand->slug ?? null,
                'meta' => null,
                'badge' => null,
                'href' => '/brands',
            ])->all(),
        ];
    }

    private function packagesData(string $search): array
    {
        $query = Package::query()->latest('id');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return [
            'stats' => [['label' => 'الباقات', 'value' => Package::query()->count()]],
            'items' => $query->limit(40)->get()->map(fn (Package $package) => [
                'id' => $package->id,
                'title' => $package->name,
                'subtitle' => $package->description ? \Illuminate\Support\Str::limit(strip_tags($package->description), 60) : null,
                'meta' => isset($package->price) ? number_format((float) $package->price, 2).' SAR' : null,
                'badge' => null,
                'href' => '/packages/'.$package->id.'/edit',
            ])->all(),
        ];
    }

    private function ordersData(string $search): array
    {
        $query = Order::query()->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        return [
            'stats' => [
                ['label' => 'الكل', 'value' => Order::query()->count()],
                ['label' => 'اليوم', 'value' => Order::query()->whereDate('created_at', today())->count()],
            ],
            'items' => $query->limit(40)->get()->map(fn (Order $order) => [
                'id' => $order->id,
                'title' => $order->order_number,
                'subtitle' => $order->customer_name ?: '—',
                'meta' => number_format((float) $order->total_amount, 2).' '.($order->currency ?: 'SAR'),
                'badge' => $order->status,
                'badge_tone' => $order->status === 'paid' ? 'emerald' : 'amber',
                'href' => '/orders/'.$order->id,
            ])->all(),
        ];
    }

    private function paymentReceiptsData(string $search): array
    {
        $query = OrderPaymentReceipt::query()
            ->with('order:id,order_number,customer_name,currency')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%");
                    });
            });
        }

        return [
            'stats' => [
                ['label' => 'معلّق', 'value' => OrderPaymentReceipt::query()->where('approval_status', 'pending')->count()],
                ['label' => 'معتمد', 'value' => OrderPaymentReceipt::query()->where('approval_status', 'approved')->count()],
                ['label' => 'مرفوض', 'value' => OrderPaymentReceipt::query()->where('approval_status', 'rejected')->count()],
            ],
            'items' => $query->limit(40)->get()->map(fn (OrderPaymentReceipt $receipt) => [
                'id' => $receipt->id,
                'title' => $receipt->receipt_number,
                'subtitle' => ($receipt->order?->customer_name ?: '—').' · '.($receipt->order?->order_number ?: ''),
                'meta' => number_format((float) $receipt->amount, 2).' '.($receipt->order?->currency ?: 'SAR'),
                'badge' => match ($receipt->approval_status) {
                    'approved' => 'معتمد',
                    'rejected' => 'مرفوض',
                    default => 'معلّق',
                },
                'badge_tone' => match ($receipt->approval_status) {
                    'approved' => 'emerald',
                    'rejected' => 'rose',
                    default => 'amber',
                },
                'href' => '/payment-receipts',
            ])->all(),
        ];
    }

    private function workerOrdersData(string $search): array
    {
        $query = Order::query()
            ->whereHas('workerOrders')
            ->withCount('workerOrders')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        return [
            'stats' => [
                ['label' => 'أوامر العمل', 'value' => Order::query()->whereHas('workerOrders')->count()],
            ],
            'items' => $query->limit(40)->get()->map(fn (Order $order) => [
                'id' => $order->id,
                'title' => $order->order_number,
                'subtitle' => $order->customer_name ?: '—',
                'meta' => ($order->worker_orders_count ?? 0).' بند',
                'badge' => $order->work_order_approved_at ? 'معتمد' : 'بانتظار',
                'badge_tone' => $order->work_order_approved_at ? 'emerald' : 'amber',
                'href' => '/worker-orders',
            ])->all(),
        ];
    }

    private function customersData(string $search): array
    {
        $query = User::query()
            ->where(function ($q) {
                $q->whereNull('role')->orWhereNotIn('role', User::STAFF_ROLES);
            })
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $individuals = $query->limit(25)->get()->map(fn (User $customer) => [
            'id' => 'u-'.$customer->id,
            'title' => $customer->customer_name ?: $customer->email,
            'subtitle' => $customer->phone ?: $customer->email,
            'meta' => 'فرد',
            'badge' => null,
            'href' => '/customers/individual/'.$customer->id,
        ]);

        $companiesQuery = CompanyClient::query()->latest('id');
        if ($search !== '') {
            $companiesQuery->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $companies = $companiesQuery->limit(25)->get()->map(fn (CompanyClient $client) => [
            'id' => 'c-'.$client->id,
            'title' => $client->company_name,
            'subtitle' => $client->contact_name ?: $client->phone,
            'meta' => 'شركة',
            'badge' => 'شركة',
            'badge_tone' => 'sky',
            'href' => '/company-clients',
        ]);

        return [
            'stats' => [
                ['label' => 'أفراد', 'value' => User::query()->where(function ($q) {
                    $q->whereNull('role')->orWhereNotIn('role', User::STAFF_ROLES);
                })->count()],
                ['label' => 'شركات', 'value' => CompanyClient::query()->count()],
            ],
            'items' => $individuals->concat($companies)->values()->all(),
        ];
    }

    private function usersData(string $search): array
    {
        $query = User::query()->whereIn('role', User::STAFF_ROLES)->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return [
            'stats' => [['label' => 'الموظفين', 'value' => User::query()->whereIn('role', User::STAFF_ROLES)->count()]],
            'items' => $query->limit(40)->get()->map(fn (User $staff) => [
                'id' => $staff->id,
                'title' => $staff->customer_name ?: $staff->email,
                'subtitle' => $staff->email,
                'meta' => $staff->roleLabel(),
                'badge' => $staff->roleLabel(),
                'badge_tone' => 'slate',
                'href' => '/users',
            ])->all(),
        ];
    }

    private function invoicesData(string $search): array
    {
        $query = Invoice::query()->with('user:id,customer_name,email')->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return [
            'stats' => [['label' => 'الفواتير', 'value' => Invoice::query()->count()]],
            'items' => $query->limit(40)->get()->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'title' => $invoice->invoice_number,
                'subtitle' => $invoice->user?->customer_name ?: ($invoice->user?->email ?: '—'),
                'meta' => number_format((float) ($invoice->amount ?? 0), 2).' SAR',
                'badge' => $invoice->status ?? null,
                'badge_tone' => ($invoice->status ?? '') === 'paid' ? 'emerald' : 'amber',
                'href' => '/invoices/'.$invoice->id,
            ])->all(),
        ];
    }

    private function insuranceData(string $search): array
    {
        $query = Order::query()
            ->whereNotNull('insurance_refund_requested_at')
            ->whereNotNull('warehouse_returned_at')
            ->whereNotNull('work_order_approved_at')
            ->where(function ($q) {
                $q->where('insurance_amount', '>', 0)
                    ->orWhere('insurance_original_amount', '>', 0);
            })
            ->latest('insurance_refund_requested_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        return [
            'stats' => [
                ['label' => 'معلّق', 'value' => Order::query()
                    ->whereNotNull('insurance_refund_requested_at')
                    ->where('insurance_status', 'pending')
                    ->where(function ($q) {
                        $q->where('insurance_amount', '>', 0)
                            ->orWhere('insurance_original_amount', '>', 0);
                    })
                    ->count()],
            ],
            'items' => $query->limit(40)->get()->map(fn (Order $order) => [
                'id' => $order->id,
                'title' => $order->order_number,
                'subtitle' => $order->customer_name ?: '—',
                'meta' => number_format((float) $order->insurance_amount, 2).' SAR',
                'badge' => $order->insurance_status ?: 'pending',
                'badge_tone' => match ($order->insurance_status) {
                    'refunded' => 'emerald',
                    'withheld' => 'rose',
                    default => 'amber',
                },
                'href' => '/insurance-deposits/'.$order->id,
            ])->all(),
        ];
    }

    private function quotationsData(string $search): array
    {
        $query = Quotation::query()->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        return [
            'stats' => [['label' => 'العروض', 'value' => Quotation::query()->count()]],
            'items' => $query->limit(40)->get()->map(fn (Quotation $quotation) => [
                'id' => $quotation->id,
                'title' => $quotation->quotation_number,
                'subtitle' => $quotation->customer_name ?: '—',
                'meta' => number_format((float) ($quotation->total_amount ?? 0), 2).' SAR',
                'badge' => $quotation->status ?? null,
                'badge_tone' => 'teal',
                'href' => '/quotations/'.$quotation->id.'/edit',
            ])->all(),
        ];
    }

    private function whatsappData(): array
    {
        $recipients = WhatsappNotificationRecipient::query()->latest('id')->limit(40)->get();

        return [
            'stats' => [['label' => 'المستلمون', 'value' => WhatsappNotificationRecipient::query()->count()]],
            'items' => $recipients->map(fn (WhatsappNotificationRecipient $row) => [
                'id' => $row->id,
                'title' => $row->label ?: $row->phone,
                'subtitle' => $row->phone,
                'meta' => null,
                'badge' => $row->is_active ? 'نشط' : 'متوقف',
                'badge_tone' => $row->is_active ? 'emerald' : 'slate',
                'href' => '/settings/whatsapp',
            ])->all(),
            'empty_message' => 'لا يوجد مستلمون بعد — افتح الإعدادات الكاملة للإضافة.',
        ];
    }
}
