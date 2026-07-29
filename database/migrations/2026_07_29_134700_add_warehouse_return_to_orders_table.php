<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'warehouse_returned_at')) {
                $table->timestamp('warehouse_returned_at')->nullable()->after('work_order_approved_at');
            }

            if (! Schema::hasColumn('orders', 'warehouse_returned_by')) {
                $table->foreignId('warehouse_returned_by')
                    ->nullable()
                    ->after('warehouse_returned_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'warehouse_returned_by')) {
                $table->dropConstrainedForeignId('warehouse_returned_by');
            }

            if (Schema::hasColumn('orders', 'warehouse_returned_at')) {
                $table->dropColumn('warehouse_returned_at');
            }
        });
    }
};
