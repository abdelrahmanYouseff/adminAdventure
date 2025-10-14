<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new columns if they don't exist
        Schema::table('users', function (Blueprint $table) {
            // Add customer_name if not exists
            if (!Schema::hasColumn('users', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('id');
            }

            // Add password if not exists
            if (!Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->after('email');
            }

            // Add phone if not exists
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('password');
            }

            // Add country if not exists
            if (!Schema::hasColumn('users', 'country')) {
                $table->string('country')->nullable()->after('phone');
            }
        });

        // Migrate data from old columns to new ones
        if (Schema::hasColumn('users', 'name') || Schema::hasColumn('users', 'full_name')) {
            \DB::statement('UPDATE users SET customer_name = COALESCE(full_name, name) WHERE customer_name IS NULL');
        }

        // Make customer_name required after data migration
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'customer_name')) {
                $table->string('customer_name')->nullable(false)->change();
            }
            if (Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable(false)->change();
            }
        });

        // Drop old columns if they exist
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('users', 'full_name')) {
                $table->dropColumn('full_name');
            }
            if (Schema::hasColumn('users', 'address')) {
                $table->dropColumn('address');
            }
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
            if (Schema::hasColumn('users', 'remember_token')) {
                $table->dropColumn('remember_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore old columns
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->after('id');
            }
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });

        // Drop new columns
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'customer_name')) {
                $table->dropColumn('customer_name');
            }
            if (Schema::hasColumn('users', 'country')) {
                $table->dropColumn('country');
            }
        });
    }
};
