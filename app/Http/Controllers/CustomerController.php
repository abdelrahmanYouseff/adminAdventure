<?php

namespace App\Http\Controllers;

use App\Models\CompanyClient;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

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
                'email' => $user->email,
                'address' => null,
                'tax_number' => null,
                'notes' => null,
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
                    'email' => $client->email,
                    'address' => $client->address,
                    'tax_number' => $client->tax_number,
                    'notes' => $client->notes,
                    'created_at' => $client->created_at?->toIso8601String(),
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
                            'total_price' => (float) $item->total_price,
                        ])->values(),
                    ])->values(),
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

    public function apiCheckPhone(Request $request)
    {
        $phone = $request->query('phone');
        $exists = false;
        if ($phone) {
            $exists = User::where('phone', $phone)->exists();
        }

        return response()->json(['exists' => $exists]);
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
