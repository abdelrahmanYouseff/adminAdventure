<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'warehouse_keeper_approved_at')) {
                $table->timestamp('warehouse_keeper_approved_at')
                    ->nullable()
                    ->after('warehouse_returned_by');
            }

            if (! Schema::hasColumn('orders', 'warehouse_keeper_approved_by')) {
                $table->foreignId('warehouse_keeper_approved_by')
                    ->nullable()
                    ->after('warehouse_keeper_approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'warehouse_keeper_approved_by')) {
                $table->dropConstrainedForeignId('warehouse_keeper_approved_by');
            }

            if (Schema::hasColumn('orders', 'warehouse_keeper_approved_at')) {
                $table->dropColumn('warehouse_keeper_approved_at');
            }
        });
    }
};
