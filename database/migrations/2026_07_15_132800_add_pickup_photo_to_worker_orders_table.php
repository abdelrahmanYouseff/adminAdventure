<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_orders', function (Blueprint $table) {
            $table->string('pickup_photo')->nullable()->after('completed_at');
            $table->foreignId('pickup_by')->nullable()->after('pickup_photo')->constrained('users')->nullOnDelete();
            $table->timestamp('pickup_at')->nullable()->after('pickup_by');
        });
    }

    public function down(): void
    {
        Schema::table('worker_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_by');
            $table->dropColumn(['pickup_photo', 'pickup_at']);
        });
    }
};
