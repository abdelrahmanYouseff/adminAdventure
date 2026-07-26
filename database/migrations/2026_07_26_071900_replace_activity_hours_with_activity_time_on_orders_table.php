<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'activity_hours')) {
                $table->dropColumn('activity_hours');
            }

            if (! Schema::hasColumn('orders', 'activity_time')) {
                $table->time('activity_time')->nullable()->after('activity_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'activity_time')) {
                $table->dropColumn('activity_time');
            }

            if (! Schema::hasColumn('orders', 'activity_hours')) {
                $table->unsignedSmallInteger('activity_hours')->nullable()->after('activity_date');
            }
        });
    }
};
