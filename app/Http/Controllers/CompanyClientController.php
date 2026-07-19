<?php

namespace App\Http\Controllers;

use App\Models\CompanyClient;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class CompanyClientController extends Controller
{
    public function index()
    {
        $clients = CompanyClient::query()
            ->orderByDesc('created_at')
            ->get();

        $quotations = Quotation::query()
            ->with([
                'items:id,quotation_id,product_name,description,quantity,unit_price,total_price',
                'user:id,customer_name',
            ])
            ->orderByDesc('created_at')
            ->get();

        $clientsWithQuotations = $clients->map(function (CompanyClient $client) use ($quotations) {
            $matched = $this->matchQuotationsForClient($client, $quotations);

            return [
                'id' => $client->id,
                'company_name' => $client->company_name,
                'contact_name' => $client->contact_name,
                'phone' => $client->phone,
                'email' => $client->email,
                'address' => $client->address,
                'tax_number' => $client->tax_number,
                'notes' => $client->notes,
                'created_at' => $client->created_at,
                'quotations_count' => $matched->count(),
                'quotations' => $matched->map(fn (Quotation $quotation) => [
                    'id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                    'customer_name' => $quotation->customer_name,
                    'customer_email' => $quotation->customer_email,
                    'customer_phone' => $quotation->customer_phone,
                    'customer_address' => $quotation->customer_address,
                    'valid_until' => $quotation->valid_until?->toDateString(),
                    'notes' => $quotation->notes,
                    'subtotal' => (float) $quotation->subtotal,
                    'tax_amount' => (float) $quotation->tax_amount,
                    'total_amount' => (float) $quotation->total_amount,
                    'status' => $quotation->status,
                    'created_at' => $quotation->created_at,
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
                        'total_price' => (float) $item->total_price,
                    ])->values(),
                ])->values(),
            ];
        });

        return Inertia::render('CompanyClients/Index', [
            'clients' => $clientsWithQuotations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'company_name.required' => 'اسم الشركة مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
        ]);

        CompanyClient::create($validated);

        return redirect()
            ->route('company-clients.index')
            ->with('success', 'تم إضافة عميل الشركة بنجاح.');
    }

    public function destroy(CompanyClient $companyClient)
    {
        $companyClient->delete();

        return redirect()
            ->route('company-clients.index')
            ->with('success', 'تم حذف عميل الشركة.');
    }

    /**
     * @param  Collection<int, Quotation>  $quotations
     * @return Collection<int, Quotation>
     */
    private function matchQuotationsForClient(CompanyClient $client, Collection $quotations): Collection
    {
        $clientPhone = $this->normalizePhoneDigits($client->phone);
        $email = $client->email ? mb_strtolower(trim($client->email)) : null;
        $companyName = trim($client->company_name);

        return $quotations
            ->filter(function (Quotation $quotation) use ($clientPhone, $email, $companyName) {
                if ($clientPhone !== '') {
                    $quotationPhone = $this->normalizePhoneDigits($quotation->customer_phone);
                    if ($quotationPhone !== '' && $quotationPhone === $clientPhone) {
                        return true;
                    }
                }

                if ($email && $quotation->customer_email) {
                    if (mb_strtolower(trim($quotation->customer_email)) === $email) {
                        return true;
                    }
                }

                if ($companyName !== '' && $quotation->customer_name) {
                    if (str_contains($quotation->customer_name, $companyName)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
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
