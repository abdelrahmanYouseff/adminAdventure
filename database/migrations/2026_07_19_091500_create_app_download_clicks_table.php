<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_download_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20); // ios | android
            $table->string('session_id', 100)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('clicked_at')->useCurrent()->index();
            $table->timestamps();

            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_download_clicks');
    }
};
