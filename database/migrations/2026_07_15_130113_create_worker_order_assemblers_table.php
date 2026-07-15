<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_order_assemblers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('worker_order_id')->nullable()->constrained('worker_orders')->nullOnDelete();
            $table->string('worker_name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'worker_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_order_assemblers');
    }
};
