<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'insurance_amount')) {
                $table->decimal('insurance_amount', 10, 2)
                    ->default(0)
                    ->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'insurance_amount')) {
                $table->dropColumn('insurance_amount');
            }
        });
    }
};
