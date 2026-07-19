<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'insurance_manager_approved_at')) {
                $table->timestamp('insurance_manager_approved_at')->nullable()->after('work_order_approved_by');
            }

            if (! Schema::hasColumn('orders', 'insurance_manager_approved_by')) {
                $table->foreignId('insurance_manager_approved_by')
                    ->nullable()
                    ->after('insurance_manager_approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'insurance_gm_approved_at')) {
                $table->timestamp('insurance_gm_approved_at')->nullable()->after('insurance_manager_approved_by');
            }

            if (! Schema::hasColumn('orders', 'insurance_gm_approved_by')) {
                $table->foreignId('insurance_gm_approved_by')
                    ->nullable()
                    ->after('insurance_gm_approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'insurance_accounts_approved_at')) {
                $table->timestamp('insurance_accounts_approved_at')->nullable()->after('insurance_gm_approved_by');
            }

            if (! Schema::hasColumn('orders', 'insurance_accounts_approved_by')) {
                $table->foreignId('insurance_accounts_approved_by')
                    ->nullable()
                    ->after('insurance_accounts_approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'insurance_accounts_approved_by')) {
                $table->dropConstrainedForeignId('insurance_accounts_approved_by');
            }
            if (Schema::hasColumn('orders', 'insurance_accounts_approved_at')) {
                $table->dropColumn('insurance_accounts_approved_at');
            }
            if (Schema::hasColumn('orders', 'insurance_gm_approved_by')) {
                $table->dropConstrainedForeignId('insurance_gm_approved_by');
            }
            if (Schema::hasColumn('orders', 'insurance_gm_approved_at')) {
                $table->dropColumn('insurance_gm_approved_at');
            }
            if (Schema::hasColumn('orders', 'insurance_manager_approved_by')) {
                $table->dropConstrainedForeignId('insurance_manager_approved_by');
            }
            if (Schema::hasColumn('orders', 'insurance_manager_approved_at')) {
                $table->dropColumn('insurance_manager_approved_at');
            }
        });
    }
};
