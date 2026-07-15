<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create migration already makes worker_order_id nullable.
        // This only patches DBs that created the table before that change.
        if (! Schema::hasTable('worker_order_assemblers')) {
            return;
        }

        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            $table->dropForeign(['worker_order_id']);
        });

        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            $table->unsignedBigInteger('worker_order_id')->nullable()->change();
        });

        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            $table->foreign('worker_order_id')
                ->references('id')
                ->on('worker_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('worker_order_assemblers')) {
            return;
        }

        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            $table->dropForeign(['worker_order_id']);
        });

        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            $table->unsignedBigInteger('worker_order_id')->nullable(false)->change();
        });

        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            $table->foreign('worker_order_id')
                ->references('id')
                ->on('worker_orders')
                ->cascadeOnDelete();
        });
    }
};
