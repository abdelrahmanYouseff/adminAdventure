<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payment_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('order_payment_receipts', 'approval_status')) {
                $table->string('approval_status')->default('pending')->after('type');
            }

            if (! Schema::hasColumn('order_payment_receipts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approval_status');
            }

            if (! Schema::hasColumn('order_payment_receipts', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->after('approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // Existing receipts predate the approval workflow: treat them as approved
        // so historical orders and their work orders keep functioning.
        DB::table('order_payment_receipts')
            ->where('approval_status', 'pending')
            ->update([
                'approval_status' => 'approved',
                'approved_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('order_payment_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('order_payment_receipts', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('order_payment_receipts', 'approved_at')) {
                $table->dropColumn('approved_at');
            }

            if (Schema::hasColumn('order_payment_receipts', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
};
