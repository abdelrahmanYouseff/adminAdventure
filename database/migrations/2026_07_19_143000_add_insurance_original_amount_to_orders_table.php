<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'insurance_original_amount')) {
                $table->decimal('insurance_original_amount', 10, 2)
                    ->nullable()
                    ->after('insurance_amount');
            }
        });

        // نسخ المبلغ الحالي كأصل للسجلات الموجودة
        if (Schema::hasColumn('orders', 'insurance_original_amount')) {
            DB::table('orders')
                ->where('insurance_amount', '>', 0)
                ->whereNull('insurance_original_amount')
                ->update([
                    'insurance_original_amount' => DB::raw('insurance_amount'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'insurance_original_amount')) {
                $table->dropColumn('insurance_original_amount');
            }
        });
    }
};
