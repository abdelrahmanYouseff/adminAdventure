<?php

namespace App\Http\Controllers;

use App\Models\CompanyClient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index()
    {
        $quotations = Quotation::query()
            ->with([
                'items:id,quotation_id,product_name,description,quantity,unit_price,total_price',
                'user:id,customer_name',
            ])
            ->orderByDesc('created_at')
            ->get();

        $individuals = User::query()
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereNotIn('role', User::STAFF_ROLES);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => [
                'key' => 'individual-'.$user->id,
                'id' => $user->id,
                'type' => 'individual',
                'name' => $user->name,
                'contact_name' => null,
                'phone' => $user->phone,
                'phone_secondary' => $user->phone_secondary,
                'email' => $user->email,
                'address' => null,
                'tax_number' => null,
                'iban' => $user->iban,
                'iban_image_url' => $user->iban_image_url,
                'notes' => null,
                'country' => $user->country,
                'gender' => $user->gender,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'created_at' => $user->created_at?->toIso8601String(),
                'quotations_count' => 0,
                'quotations' => [],
            ]);

        $companies = CompanyClient::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(function (CompanyClient $client) use ($quotations) {
                $matched = $this->matchQuotationsForClient($client, $quotations);

                return [
                    'key' => 'company-'.$client->id,
                    'id' => $client->id,
                    'type' => 'company',
                    'name' => $client->company_name,
                    'contact_name' => $client->contact_name,
                    'phone' => $client->phone,
                    'phone_secondary' => $client->phone_secondary,
                    'email' => $client->email,
                    'address' => $client->address,
                    'tax_number' => $client->tax_number,
                    'iban' => $client->iban,
                    'iban_image_url' => $client->iban_image_url,
                    'notes' => $client->notes,
                    'country' => null,
                    'gender' => null,
                    'date_of_birth' => null,
                    'created_at' => $client->created_at?->toIso8601String(),
                    'quotations_count' => $matched->count(),
                    'quotations' => $matched->map(fn (Quotation $quotation) => $this->formatQuotation($quotation))->values(),
                ];
            });

        $customers = $individuals
            ->concat($companies)
            ->sortByDesc(fn (array $row) => $row['created_at'] ?? '')
            ->values()
            ->all();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
        ]);
    }

    public function show(string $type, int $id): Response
    {
        abort_unless(in_array($type, ['individual', 'company'], true), 404);

        if ($type === 'individual') {
            return $this->showIndividual($id);
        }

        return $this->showCompany($id);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['individual', 'company'], true), 404);

        if ($type === 'individual') {
            return $this->updateIndividual($request, $id);
        }

        return $this->updateCompany($request, $id);
    }

    public function updateBank(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['individual', 'company'], true), 404);

        $validated = $request->validate([
            'phone_secondary' => ['nullable', 'string', 'max:20'],
            'iban' => ['nullable', 'string', 'max:34'],
            'iban_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_iban_image' => ['nullable', 'boolean'],
        ], [
            'iban_image.image' => 'ملف الآيبان يجب أن يكون صورة.',
            'iban_image.mimes' => 'صيغ صورة الآيبان المسموحة: jpg, jpeg, png, webp.',
            'iban_image.max' => 'حجم صورة الآيبان يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        $iban = isset($validated['iban']) && $validated['iban'] !== ''
            ? strtoupper(preg_replace('/\s+/', '', $validated['iban']) ?? '')
            : null;

        $phoneSecondary = isset($validated['phone_secondary']) && $validated['phone_secondary'] !== ''
            ? trim($validated['phone_secondary'])
            : null;

        if ($type === 'individual') {
            $user = User::query()->findOrFail($id);
            abort_unless(
                $user->role === null || ! in_array($user->role, User::STAFF_ROLES, true),
                404
            );

            $user->phone_secondary = $phoneSecondary;
            $user->iban = $iban;

            if ($request->boolean('remove_iban_image') && $user->iban_image) {
                Storage::disk('public')->delete($user->iban_image);
                $user->iban_image = null;
            }

            if ($request->hasFile('iban_image')) {
                if ($user->iban_image) {
                    Storage::disk('public')->delete($user->iban_image);
                }
                $user->iban_image = $request->file('iban_image')->store('customer-ibans', 'public');
            }

            $user->save();
        } else {
            $client = CompanyClient::query()->findOrFail($id);
            $client->phone_secondary = $phoneSecondary;
            $client->iban = $iban;

            if ($request->boolean('remove_iban_image') && $client->iban_image) {
                Storage::disk('public')->delete($client->iban_image);
                $client->iban_image = null;
            }

            if ($request->hasFile('iban_image')) {
                if ($client->iban_image) {
                    Storage::disk('public')->delete($client->iban_image);
                }
                $client->iban_image = $request->file('iban_image')->store('customer-ibans', 'public');
            }

            $client->save();
        }

        return redirect()
            ->route('customers.show', ['type' => $type, 'id' => $id])
            ->with('success', 'تم حفظ بيانات التواصل والحساب البنكي.');
    }

    public function apiCheckPhone(Request $request)
    {
        $phone = $request->query('phone');
        $exists = false;
        if ($phone) {
            $exists = User::where('phone', $phone)->exists();
        }

        return response()->json(['exists' => $exists]);
    }

    private function updateIndividual(Request $request, int $id): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(
            $user->role === null || ! in_array($user->role, User::STAFF_ROLES, true),
            404
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone_secondary' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'iban' => ['nullable', 'string', 'max:34'],
            'iban_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_iban_image' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'اسم العميل مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'iban_image.image' => 'ملف الآيبان يجب أن يكون صورة.',
            'iban_image.mimes' => 'صيغ صورة الآيبان المسموحة: jpg, jpeg, png, webp.',
            'iban_image.max' => 'حجم صورة الآيبان يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        $user->customer_name = $validated['name'];
        $user->phone = $validated['phone'] ?: null;
        $user->phone_secondary = $validated['phone_secondary'] ?: null;
        $user->email = $validated['email'] ?: null;
        $user->country = $validated['country'] ?: null;
        $user->gender = $validated['gender'] ?? null;
        $user->date_of_birth = $validated['date_of_birth'] ?? null;
        $user->iban = ! empty($validated['iban'])
            ? strtoupper(preg_replace('/\s+/', '', $validated['iban']) ?? '')
            : null;

        if ($request->boolean('remove_iban_image') && $user->iban_image) {
            Storage::disk('public')->delete($user->iban_image);
            $user->iban_image = null;
        }

        if ($request->hasFile('iban_image')) {
            if ($user->iban_image) {
                Storage::disk('public')->delete($user->iban_image);
            }
            $user->iban_image = $request->file('iban_image')->store('customer-ibans', 'public');
        }

        $user->save();

        return redirect()
            ->route('customers')
            ->with('success', 'تم تحديث بيانات العميل بنجاح.');
    }

    private function updateCompany(Request $request, int $id): RedirectResponse
    {
        $client = CompanyClient::query()->findOrFail($id);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone_secondary' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:34'],
            'iban_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_iban_image' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'company_name.required' => 'اسم الشركة مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'iban_image.image' => 'ملف الآيبان يجب أن يكون صورة.',
            'iban_image.mimes' => 'صيغ صورة الآيبان المسموحة: jpg, jpeg, png, webp.',
            'iban_image.max' => 'حجم صورة الآيبان يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        $client->company_name = $validated['company_name'];
        $client->contact_name = $validated['contact_name'] ?: null;
        $client->phone = $validated['phone'] ?: null;
        $client->phone_secondary = $validated['phone_secondary'] ?: null;
        $client->email = $validated['email'] ?: null;
        $client->address = $validated['address'] ?: null;
        $client->tax_number = $validated['tax_number'] ?: null;
        $client->notes = $validated['notes'] ?: null;
        $client->iban = ! empty($validated['iban'])
            ? strtoupper(preg_replace('/\s+/', '', $validated['iban']) ?? '')
            : null;

        if ($request->boolean('remove_iban_image') && $client->iban_image) {
            Storage::disk('public')->delete($client->iban_image);
            $client->iban_image = null;
        }

        if ($request->hasFile('iban_image')) {
            if ($client->iban_image) {
                Storage::disk('public')->delete($client->iban_image);
            }
            $client->iban_image = $request->file('iban_image')->store('customer-ibans', 'public');
        }

        $client->save();

        return redirect()
            ->route('customers')
            ->with('success', 'تم تحديث بيانات عميل الشركة بنجاح.');
    }

    private function showIndividual(int $id): Response
    {
        $user = User::query()->findOrFail($id);

        abort_unless(
            $user->role === null || ! in_array($user->role, User::STAFF_ROLES, true),
            404
        );

        $quotations = Quotation::query()
            ->with([
                'items:id,quotation_id,product_name,description,quantity,unit_price,total_price',
                'user:id,customer_name',
            ])
            ->orderByDesc('created_at')
            ->get();

        $matchedQuotations = $this->matchQuotationsForContact(
            $quotations,
            $user->phone,
            $user->email,
            $user->name
        );

        $orders = $this->ordersForContact($user->id, $user->phone, $user->email)
            ->map(fn (Order $order) => $this->formatOrder($order))
            ->values()
            ->all();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'key' => 'individual-'.$user->id,
                'id' => $user->id,
                'type' => 'individual',
                'name' => $user->name,
                'contact_name' => null,
                'phone' => $user->phone,
                'phone_secondary' => $user->phone_secondary,
                'email' => $user->email,
                'address' => null,
                'tax_number' => null,
                'notes' => null,
                'country' => $user->country,
                'gender' => $user->gender,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'profile_completed' => (bool) $user->profile_completed,
                'iban' => $user->iban,
                'iban_image_url' => $user->iban_image_url,
                'created_at' => $user->created_at?->toIso8601String(),
                'quotations_count' => $matchedQuotations->count(),
                'quotations' => $matchedQuotations->map(fn (Quotation $q) => $this->formatQuotation($q))->values()->all(),
                'orders' => $orders,
                'orders_count' => count($orders),
            ],
        ]);
    }

    private function showCompany(int $id): Response
    {
        $client = CompanyClient::query()->findOrFail($id);

        $quotations = Quotation::query()
            ->with([
                'items:id,quotation_id,product_name,description,quantity,unit_price,total_price',
                'user:id,customer_name',
            ])
            ->orderByDesc('created_at')
            ->get();

        $matchedQuotations = $this->matchQuotationsForClient($client, $quotations);

        $orders = $this->ordersForContact(null, $client->phone, $client->email)
            ->map(fn (Order $order) => $this->formatOrder($order))
            ->values()
            ->all();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'key' => 'company-'.$client->id,
                'id' => $client->id,
                'type' => 'company',
                'name' => $client->company_name,
                'contact_name' => $client->contact_name,
                'phone' => $client->phone,
                'phone_secondary' => $client->phone_secondary,
                'email' => $client->email,
                'address' => $client->address,
                'tax_number' => $client->tax_number,
                'notes' => $client->notes,
                'country' => null,
                'gender' => null,
                'date_of_birth' => null,
                'profile_completed' => null,
                'iban' => $client->iban,
                'iban_image_url' => $client->iban_image_url,
                'created_at' => $client->created_at?->toIso8601String(),
                'quotations_count' => $matchedQuotations->count(),
                'quotations' => $matchedQuotations->map(fn (Quotation $q) => $this->formatQuotation($q))->values()->all(),
                'orders' => $orders,
                'orders_count' => count($orders),
            ],
        ]);
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersForContact(?int $userId, ?string $phone, ?string $email): Collection
    {
        $normalizedPhone = $this->normalizePhoneDigits($phone);
        $normalizedEmail = $email ? mb_strtolower(trim($email)) : null;

        if ($userId === null && $normalizedPhone === '' && ! $normalizedEmail) {
            return collect();
        }

        return Order::query()
            ->with(['invoice:id,invoice_number', 'products', 'paymentReceipts' => fn ($q) => $q->latest('id')])
            ->where(function ($query) use ($userId, $normalizedPhone, $normalizedEmail) {
                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }

                if ($normalizedEmail) {
                    $query->orWhereRaw('LOWER(TRIM(customer_email)) = ?', [$normalizedEmail]);
                }

                if ($normalizedPhone !== '') {
                    $query->orWhere(function ($phoneQuery) use ($normalizedPhone) {
                        $phoneQuery
                            ->where('customer_phone', 'like', '%'.$normalizedPhone)
                            ->orWhere('customer_phone', 'like', '%0'.$normalizedPhone)
                            ->orWhere('customer_phone', 'like', '%966'.$normalizedPhone);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (Order $order) use ($userId, $normalizedPhone, $normalizedEmail) {
                if ($userId !== null && (int) $order->user_id === $userId) {
                    return true;
                }

                if ($normalizedEmail && $order->customer_email) {
                    if (mb_strtolower(trim($order->customer_email)) === $normalizedEmail) {
                        return true;
                    }
                }

                if ($normalizedPhone !== '') {
                    $orderPhone = $this->normalizePhoneDigits($order->customer_phone);
                    if ($orderPhone !== '' && $orderPhone === $normalizedPhone) {
                        return true;
                    }
                }

                return false;
            })
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, Quotation>  $quotations
     * @return Collection<int, Quotation>
     */
    private function matchQuotationsForClient(CompanyClient $client, Collection $quotations): Collection
    {
        return $this->matchQuotationsForContact(
            $quotations,
            $client->phone,
            $client->email,
            $client->company_name
        );
    }

    /**
     * @param  Collection<int, Quotation>  $quotations
     * @return Collection<int, Quotation>
     */
    private function matchQuotationsForContact(
        Collection $quotations,
        ?string $phone,
        ?string $email,
        ?string $name
    ): Collection {
        $clientPhone = $this->normalizePhoneDigits($phone);
        $normalizedEmail = $email ? mb_strtolower(trim($email)) : null;
        $displayName = trim((string) $name);

        return $quotations
            ->filter(function (Quotation $quotation) use ($clientPhone, $normalizedEmail, $displayName) {
                if ($clientPhone !== '') {
                    $quotationPhone = $this->normalizePhoneDigits($quotation->customer_phone);
                    if ($quotationPhone !== '' && $quotationPhone === $clientPhone) {
                        return true;
                    }
                }

                if ($normalizedEmail && $quotation->customer_email) {
                    if (mb_strtolower(trim($quotation->customer_email)) === $normalizedEmail) {
                        return true;
                    }
                }

                if ($displayName !== '' && $quotation->customer_name) {
                    if (str_contains($quotation->customer_name, $displayName)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatQuotation(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'customer_name' => $quotation->customer_name,
            'customer_email' => $quotation->customer_email,
            'customer_phone' => $quotation->customer_phone,
            'customer_address' => $quotation->customer_address,
            'company_tax_number' => $quotation->company_tax_number,
            'valid_until' => $quotation->valid_until?->toDateString(),
            'notes' => $quotation->notes,
            'subtotal' => (float) $quotation->subtotal,
            'discount_total' => (float) ($quotation->discount_total ?? 0),
            'tax_amount' => (float) $quotation->tax_amount,
            'insurance_amount' => (float) ($quotation->insurance_amount ?? 0),
            'total_amount' => (float) $quotation->total_amount,
            'status' => $quotation->status,
            'created_at' => $quotation->created_at?->toIso8601String(),
            'user' => $quotation->user ? [
                'id' => $quotation->user->id,
                'name' => $quotation->user->name,
            ] : null,
            'items' => $quotation->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'description' => $item->description,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) ($item->discount_amount ?? 0),
                'total_price' => (float) $item->total_price,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrder(Order $order): array
    {
        $items = [];
        $productImages = $order->products
            ->keyBy('id')
            ->map(fn ($product) => $product->image_url);

        if ($order->products->isEmpty() && is_array($order->items) && $order->items !== []) {
            $productIds = collect($order->items)
                ->pluck('product_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($productIds !== []) {
                $productImages = Product::query()
                    ->whereIn('id', $productIds)
                    ->get()
                    ->mapWithKeys(fn (Product $product) => [$product->id => $product->image_url]);
            }
        }

        if ($order->products->isNotEmpty()) {
            foreach ($order->products as $product) {
                $qty = (int) ($product->pivot->quantity ?? 0);
                $price = (float) ($product->pivot->price ?? 0);
                $discount = (float) ($product->pivot->discount_amount ?? 0);

                $items[] = [
                    'product_id' => $product->id,
                    'name' => $product->product_name,
                    'image_url' => $product->image_url,
                    'quantity' => $qty,
                    'price' => $price,
                    'discount_amount' => $discount,
                    'total' => round($qty * ($price - $discount), 2),
                ];
            }
        } elseif (is_array($order->items) && $order->items !== []) {
            foreach ($order->items as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $discount = (float) ($item['discount_amount'] ?? 0);
                $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
                $total = isset($item['amount'])
                    ? (float) $item['amount']
                    : round($qty * ($price - $discount), 2);

                $items[] = [
                    'product_id' => $productId,
                    'name' => (string) ($item['name'] ?? $item['product_name'] ?? 'منتج'),
                    'image_url' => $productId ? ($productImages[$productId] ?? null) : null,
                    'quantity' => $qty,
                    'price' => $price,
                    'discount_amount' => $discount,
                    'total' => $total,
                ];
            }
        }

        $total = (float) $order->total_amount;
        $paid = (float) ($order->amount_paid ?? 0);
        $remaining = round(max(0, $total - $paid), 2);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'invoice_number' => $order->invoice?->invoice_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'address' => $order->address,
            'activity_date' => $order->activity_date?->format('Y-m-d'),
            'currency' => $order->currency ?: 'SAR',
            'total_amount' => $total,
            'discount_total' => (float) ($order->discount_total ?? 0),
            'amount_paid' => $paid,
            'remaining_amount' => $remaining,
            'insurance_amount' => (float) ($order->insurance_amount ?? 0),
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $items,
            'products_count' => count($items),
            'latest_receipt_id' => $order->paymentReceipts->first()?->id,
            'receipts' => $order->paymentReceipts->map(fn ($receipt) => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'amount' => (float) $receipt->amount,
                'amount_paid_after' => (float) $receipt->amount_paid_after,
                'remaining_after' => (float) $receipt->remaining_after,
                'created_at' => $receipt->created_at?->toIso8601String(),
            ])->values()->all(),
            'can_settle' => $remaining > 0.009,
            'payment_receipt_url' => route('orders.payment-receipt', $order),
        ];
    }

    private function normalizePhoneDigits(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }
}
