<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_total');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });
    }
};
