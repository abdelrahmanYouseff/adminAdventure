<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'brand_id')) {
                $table->foreignId('brand_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('brands')
                    ->nullOnDelete();
            }
        });

        $brandId = DB::table('brands')->where('slug', 'adventure-world')->value('id');

        if (! $brandId) {
            $brandId = DB::table('brands')->insertGetId([
                'name' => 'شركة عالم المغامرة',
                'slug' => 'adventure-world',
                'description' => 'المنتجات الخاصة بشركة عالم المغامرة',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('invoices')->whereNull('brand_id')->update(['brand_id' => $brandId]);

        $orderBrandRows = DB::table('orders')
            ->join('order_product', 'orders.id', '=', 'order_product.order_id')
            ->join('products', 'order_product.product_id', '=', 'products.id')
            ->whereNotNull('orders.invoice_id')
            ->whereNotNull('products.brand_id')
            ->select('orders.invoice_id', 'products.brand_id')
            ->orderBy('order_product.id')
            ->get()
            ->unique('invoice_id');

        foreach ($orderBrandRows as $row) {
            DB::table('invoices')
                ->where('id', $row->invoice_id)
                ->update(['brand_id' => $row->brand_id]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
        });
    }
};
