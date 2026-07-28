<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'quotation_id')) {
                $table->foreignId('quotation_id')
                    ->nullable()
                    ->after('invoice_id')
                    ->constrained('quotations')
                    ->nullOnDelete();
                $table->unique('quotation_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'quotation_id')) {
                $table->dropUnique(['quotation_id']);
                $table->dropConstrainedForeignId('quotation_id');
            }
        });
    }
};
