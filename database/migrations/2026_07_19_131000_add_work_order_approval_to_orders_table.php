<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'work_order_approved_at')) {
                $table->timestamp('work_order_approved_at')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('orders', 'work_order_approved_by')) {
                $table->foreignId('work_order_approved_by')
                    ->nullable()
                    ->after('work_order_approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'work_order_approved_by')) {
                $table->dropConstrainedForeignId('work_order_approved_by');
            }

            if (Schema::hasColumn('orders', 'work_order_approved_at')) {
                $table->dropColumn('work_order_approved_at');
            }
        });
    }
};
