<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('worker_order_assemblers')) {
            return;
        }

        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            if (! Schema::hasColumn('worker_order_assemblers', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('worker_name')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('worker_order_assemblers')) {
            return;
        }

        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            if (Schema::hasColumn('worker_order_assemblers', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
