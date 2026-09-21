<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'insurance_payment_proof')) {
                $table->json('insurance_payment_proof')
                    ->nullable()
                    ->after('insurance_refund_requested_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'insurance_payment_proof')) {
                $table->dropColumn('insurance_payment_proof');
            }
        });
    }
};
