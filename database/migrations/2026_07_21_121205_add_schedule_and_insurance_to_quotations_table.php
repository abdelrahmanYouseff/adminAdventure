<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'activity_at')) {
                $table->dateTime('activity_at')->nullable()->after('valid_until');
            }
            if (! Schema::hasColumn('quotations', 'installation_at')) {
                $table->dateTime('installation_at')->nullable()->after('activity_at');
            }
            if (! Schema::hasColumn('quotations', 'dismantling_at')) {
                $table->dateTime('dismantling_at')->nullable()->after('installation_at');
            }
            if (! Schema::hasColumn('quotations', 'insurance_amount')) {
                $table->decimal('insurance_amount', 10, 2)->default(0)->after('tax_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('quotations', 'activity_at') ? 'activity_at' : null,
                Schema::hasColumn('quotations', 'installation_at') ? 'installation_at' : null,
                Schema::hasColumn('quotations', 'dismantling_at') ? 'dismantling_at' : null,
                Schema::hasColumn('quotations', 'insurance_amount') ? 'insurance_amount' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
