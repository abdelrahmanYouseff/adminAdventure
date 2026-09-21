<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'insurance_workers_manager_approved_at')) {
                $table->timestamp('insurance_workers_manager_approved_at')
                    ->nullable()
                    ->after('insurance_refund_requested_by');
            }

            if (! Schema::hasColumn('orders', 'insurance_workers_manager_approved_by')) {
                $table->foreignId('insurance_workers_manager_approved_by')
                    ->nullable()
                    ->after('insurance_workers_manager_approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'insurance_accounts_received_at')) {
                $table->timestamp('insurance_accounts_received_at')
                    ->nullable()
                    ->after('insurance_workers_manager_approved_by');
            }

            if (! Schema::hasColumn('orders', 'insurance_accounts_received_by')) {
                $table->foreignId('insurance_accounts_received_by')
                    ->nullable()
                    ->after('insurance_accounts_received_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'insurance_admin_approved_at')) {
                $table->timestamp('insurance_admin_approved_at')
                    ->nullable()
                    ->after('insurance_accounts_received_by');
            }

            if (! Schema::hasColumn('orders', 'insurance_admin_approved_by')) {
                $table->foreignId('insurance_admin_approved_by')
                    ->nullable()
                    ->after('insurance_admin_approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('orders', 'insurance_accounts_approved_at')) {
            DB::table('orders')
                ->whereNotNull('insurance_accounts_approved_at')
                ->whereNull('insurance_workers_manager_approved_at')
                ->update([
                    'insurance_workers_manager_approved_at' => DB::raw('insurance_accounts_approved_at'),
                    'insurance_accounts_received_at' => DB::raw('insurance_accounts_approved_at'),
                    'insurance_admin_approved_at' => DB::raw('insurance_accounts_approved_at'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'insurance_admin_approved_by')) {
                $table->dropConstrainedForeignId('insurance_admin_approved_by');
            }
            if (Schema::hasColumn('orders', 'insurance_admin_approved_at')) {
                $table->dropColumn('insurance_admin_approved_at');
            }
            if (Schema::hasColumn('orders', 'insurance_accounts_received_by')) {
                $table->dropConstrainedForeignId('insurance_accounts_received_by');
            }
            if (Schema::hasColumn('orders', 'insurance_accounts_received_at')) {
                $table->dropColumn('insurance_accounts_received_at');
            }
            if (Schema::hasColumn('orders', 'insurance_workers_manager_approved_by')) {
                $table->dropConstrainedForeignId('insurance_workers_manager_approved_by');
            }
            if (Schema::hasColumn('orders', 'insurance_workers_manager_approved_at')) {
                $table->dropColumn('insurance_workers_manager_approved_at');
            }
        });
    }
};
