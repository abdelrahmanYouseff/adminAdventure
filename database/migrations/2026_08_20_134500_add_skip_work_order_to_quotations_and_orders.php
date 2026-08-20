<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->boolean('skip_work_order')->default(false)->after('show_online_payment');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('skip_work_order')->default(false)->after('operations_released_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('skip_work_order');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('skip_work_order');
        });
    }
};
