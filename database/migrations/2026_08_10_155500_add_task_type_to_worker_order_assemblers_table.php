<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            if (! Schema::hasColumn('worker_order_assemblers', 'task_type')) {
                $table->string('task_type', 32)
                    ->default('installation')
                    ->after('worker_name')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('worker_order_assemblers', function (Blueprint $table) {
            if (Schema::hasColumn('worker_order_assemblers', 'task_type')) {
                $table->dropIndex(['task_type']);
                $table->dropColumn('task_type');
            }
        });
    }
};
