<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payment_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('order_payment_receipts', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('order_payment_receipts', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('order_payment_receipts', 'rejected_by')) {
                $table->foreignId('rejected_by')
                    ->nullable()
                    ->after('rejected_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_payment_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('order_payment_receipts', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            if (Schema::hasColumn('order_payment_receipts', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }

            if (Schema::hasColumn('order_payment_receipts', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
