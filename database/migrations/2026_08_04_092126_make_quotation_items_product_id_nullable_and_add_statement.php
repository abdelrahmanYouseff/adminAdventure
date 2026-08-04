<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        DB::statement('ALTER TABLE quotation_items MODIFY product_id BIGINT UNSIGNED NULL');

        Schema::table('quotation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_items', 'statement')) {
                $table->text('statement')->nullable()->after('description');
            }

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);

            if (Schema::hasColumn('quotation_items', 'statement')) {
                $table->dropColumn('statement');
            }
        });

        DB::statement('ALTER TABLE quotation_items MODIFY product_id BIGINT UNSIGNED NOT NULL');

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }
};
