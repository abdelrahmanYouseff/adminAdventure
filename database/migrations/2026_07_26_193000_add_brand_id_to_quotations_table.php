<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'brand_id')) {
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

        DB::table('quotations')->whereNull('brand_id')->update(['brand_id' => $brandId]);
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
        });
    }
};
