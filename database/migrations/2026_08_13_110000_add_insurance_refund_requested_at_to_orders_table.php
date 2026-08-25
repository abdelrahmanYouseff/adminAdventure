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
            if (! Schema::hasColumn('orders', 'insurance_refund_requested_at')) {
                $table->timestamp('insurance_refund_requested_at')
                    ->nullable()
                    ->after('insurance_refunded_at');
            }

            if (! Schema::hasColumn('orders', 'insurance_refund_requested_by')) {
                $table->foreignId('insurance_refund_requested_by')
                    ->nullable()
                    ->after('insurance_refund_requested_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // Preserve history and in-progress approval chains; virgin returned
        // deposits stay hidden until a refund request is created.
        if (Schema::hasColumn('orders', 'insurance_refund_requested_at')) {
            DB::table('orders')
                ->whereNull('insurance_refund_requested_at')
                ->whereNotNull('work_order_approved_at')
                ->whereNotNull('warehouse_returned_at')
                ->where(function ($query) {
                    $query->whereIn('insurance_status', ['refunded', 'withheld'])
                        ->orWhereNotNull('insurance_manager_approved_at')
                        ->orWhereNotNull('insurance_gm_approved_at')
                        ->orWhereNotNull('insurance_accounts_approved_at');
                })
                ->update([
                    'insurance_refund_requested_at' => DB::raw("COALESCE(warehouse_returned_at, '".now()->toDateTimeString()."')"),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'insurance_refund_requested_by')) {
                $table->dropConstrainedForeignId('insurance_refund_requested_by');
            }

            if (Schema::hasColumn('orders', 'insurance_refund_requested_at')) {
                $table->dropColumn('insurance_refund_requested_at');
            }
        });
    }
};
