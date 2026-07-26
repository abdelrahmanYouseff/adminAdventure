<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_clients', function (Blueprint $table) {
            if (! Schema::hasColumn('company_clients', 'iban')) {
                $table->string('iban', 34)->nullable()->after('tax_number');
            }
            if (! Schema::hasColumn('company_clients', 'iban_image')) {
                $table->string('iban_image')->nullable()->after('iban');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'iban')) {
                $table->string('iban', 34)->nullable()->after('gender');
            }
            if (! Schema::hasColumn('users', 'iban_image')) {
                $table->string('iban_image')->nullable()->after('iban');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_clients', function (Blueprint $table) {
            if (Schema::hasColumn('company_clients', 'iban_image')) {
                $table->dropColumn('iban_image');
            }
            if (Schema::hasColumn('company_clients', 'iban')) {
                $table->dropColumn('iban');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'iban_image')) {
                $table->dropColumn('iban_image');
            }
            if (Schema::hasColumn('users', 'iban')) {
                $table->dropColumn('iban');
            }
        });
    }
};
