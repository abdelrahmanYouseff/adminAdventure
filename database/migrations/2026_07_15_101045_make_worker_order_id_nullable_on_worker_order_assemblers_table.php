<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            $table->dropForeign(['worker_order_id']);
            $table->foreignId('worker_order_id')->nullable()->change();
            $table->foreign('worker_order_id')
                ->references('id')
                ->on('worker_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            $table->dropForeign(['worker_order_id']);
            $table->foreignId('worker_order_id')->nullable(false)->change();
            $table->foreign('worker_order_id')
                ->references('id')
                ->on('worker_orders')
                ->cascadeOnDelete();
        });
    }
};
