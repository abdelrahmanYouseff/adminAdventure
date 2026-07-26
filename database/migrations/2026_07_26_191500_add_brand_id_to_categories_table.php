<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'brand_id')) {
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

        DB::table('categories')->whereNull('brand_id')->update(['brand_id' => $brandId]);

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['brand_id', 'category_name'], 'categories_brand_id_category_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_brand_id_category_name_unique');
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
