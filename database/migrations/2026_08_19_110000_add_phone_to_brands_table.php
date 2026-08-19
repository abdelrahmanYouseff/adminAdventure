<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'phone')) {
                $table->string('phone', 80)->nullable()->after('logo');
            }
        });

        DB::table('brands')->whereNull('phone')->update([
            'phone' => '0114101840 - 0559668015',
        ]);
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
