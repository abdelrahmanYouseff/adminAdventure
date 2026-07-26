<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_clients', function (Blueprint $table) {
            if (! Schema::hasColumn('company_clients', 'phone_secondary')) {
                $table->string('phone_secondary', 20)->nullable()->after('phone');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone_secondary')) {
                $table->string('phone_secondary', 20)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_clients', function (Blueprint $table) {
            if (Schema::hasColumn('company_clients', 'phone_secondary')) {
                $table->dropColumn('phone_secondary');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone_secondary')) {
                $table->dropColumn('phone_secondary');
            }
        });
    }
};
