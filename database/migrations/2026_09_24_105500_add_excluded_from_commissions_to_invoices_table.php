<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'excluded_from_commissions')) {
                $table->boolean('excluded_from_commissions')
                    ->default(false)
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'excluded_from_commissions')) {
                $table->dropColumn('excluded_from_commissions');
            }
        });
    }
};
