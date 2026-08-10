<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'warehouse_rejection_reason')) {
                $table->text('warehouse_rejection_reason')->nullable()->after('warehouse_returned_by');
            }

            if (! Schema::hasColumn('orders', 'warehouse_rejected_at')) {
                $table->timestamp('warehouse_rejected_at')->nullable()->after('warehouse_rejection_reason');
            }

            if (! Schema::hasColumn('orders', 'warehouse_rejected_by')) {
                $table->foreignId('warehouse_rejected_by')
                    ->nullable()
                    ->after('warehouse_rejected_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'warehouse_rejected_by')) {
                $table->dropConstrainedForeignId('warehouse_rejected_by');
            }

            if (Schema::hasColumn('orders', 'warehouse_rejected_at')) {
                $table->dropColumn('warehouse_rejected_at');
            }

            if (Schema::hasColumn('orders', 'warehouse_rejection_reason')) {
                $table->dropColumn('warehouse_rejection_reason');
            }
        });
    }
};
