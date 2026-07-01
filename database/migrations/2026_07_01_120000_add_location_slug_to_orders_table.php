<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('location_slug', 12)->nullable()->unique()->after('order_number');
        });

        Order::query()->whereNull('location_slug')->each(function (Order $order) {
            $order->update(['location_slug' => Order::generateLocationSlug()]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('location_slug');
        });
    }
};
