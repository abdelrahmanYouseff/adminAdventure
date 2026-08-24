<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 100);
            $table->string('status', 30);
            $table->string('subject')->nullable();
            $table->string('order_number')->nullable();
            $table->json('recipients')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['order_id', 'created_at']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
