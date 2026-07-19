<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'insurance_amount')) {
                $table->decimal('insurance_amount', 10, 2)
                    ->default(0)
                    ->after('total_amount');
            }

            if (! Schema::hasColumn('orders', 'insurance_status')) {
                $table->string('insurance_status', 20)
                    ->default('pending')
                    ->after('insurance_amount');
            }

            if (! Schema::hasColumn('orders', 'insurance_refunded_at')) {
                $table->timestamp('insurance_refunded_at')
                    ->nullable()
                    ->after('insurance_status');
            }
        });

        if (Schema::hasTable('order_product') && ! Schema::hasColumn('order_product', 'insurance_amount')) {
            Schema::table('order_product', function (Blueprint $table) {
                $table->decimal('insurance_amount', 10, 2)
                    ->default(0)
                    ->after('price');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'insurance_refunded_at')) {
                $table->dropColumn('insurance_refunded_at');
            }
            if (Schema::hasColumn('orders', 'insurance_status')) {
                $table->dropColumn('insurance_status');
            }
            if (Schema::hasColumn('orders', 'insurance_amount')) {
                $table->dropColumn('insurance_amount');
            }
        });

        if (Schema::hasTable('order_product') && Schema::hasColumn('order_product', 'insurance_amount')) {
            Schema::table('order_product', function (Blueprint $table) {
                $table->dropColumn('insurance_amount');
            });
        }
    }
};
