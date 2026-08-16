<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'show_on_storefront')) {
                $table->boolean('show_on_storefront')
                    ->default(true)
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'show_on_storefront')) {
                $table->dropColumn('show_on_storefront');
            }
        });
    }
};
