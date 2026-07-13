<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleLargeQuotationSeeder extends Seeder
{
    /**
     * Sample quotation with 25 line items for PDF template review.
     */
    public function run(): void
    {
        $user = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, 'admin'])
            ->first()
            ?? User::query()->first();

        if (! $user) {
            $this->command?->error('No user found. Run AdminUserSeeder first.');

            return;
        }

        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(25)
            ->get();

        if ($products->count() < 20) {
            $products = Product::query()->orderBy('id')->limit(25)->get();
        }

        if ($products->isEmpty()) {
            $this->command?->error('No products found.');

            return;
        }

        DB::transaction(function () use ($user, $products) {
            $quotation = Quotation::create([
                'quotation_number' => 'QT-'.date('Y').'-'.str_pad(
                    (string) (Quotation::whereYear('created_at', date('Y'))->count() + 1),
                    4,
                    '0',
                    STR_PAD_LEFT
                ),
                'customer_name' => 'Adventure World — Template Review Client',
                'customer_email' => 'review@adventureksa.com',
                'customer_phone' => '0559668015',
                'customer_address' => 'Ramah, Al-Murooj, Riyadh, Riyadh Province, Saudi Arabia 12272',
                'valid_until' => now()->addDays(30)->toDateString(),
                'notes' => 'Sample quotation with 25 products for PDF layout review.',
                'status' => 'draft',
                'user_id' => $user->id,
            ]);

            $subtotal = 0.0;

            foreach ($products as $index => $product) {
                $quantity = ($index % 5) + 1;
                $unitPrice = (float) ($product->price ?: (500 + ($index * 75)));
                $totalPrice = round($quantity * $unitPrice, 2);
                $subtotal += $totalPrice;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);
            }

            $taxAmount = round($subtotal * 0.15, 2);

            $quotation->update([
                'subtotal' => round($subtotal, 2),
                'tax_amount' => $taxAmount,
                'total_amount' => round($subtotal + $taxAmount, 2),
            ]);

            $this->command?->info("Created quotation {$quotation->quotation_number} (ID: {$quotation->id}) with {$products->count()} items.");
            $this->command?->info("PDF: /quotations/{$quotation->id}/pdf");
        });
    }
}
