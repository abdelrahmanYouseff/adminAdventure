<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('user_id');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('accountant_approved_at')->nullable()->after('approved_by');
            $table->foreignId('accountant_approved_by')->nullable()->after('accountant_approved_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('operations_released_at')->nullable()->after('quotation_id');
            $table->foreignId('operations_released_by')->nullable()->after('operations_released_at')->constrained('users')->nullOnDelete();
        });

        // Existing quotation orders are already on the orders page — keep them visible.
        DB::table('orders')
            ->whereNotNull('quotation_id')
            ->whereNull('operations_released_at')
            ->update([
                'operations_released_at' => DB::raw('created_at'),
            ]);

        $released = DB::table('orders')
            ->whereNotNull('quotation_id')
            ->whereNotNull('operations_released_at')
            ->get(['quotation_id', 'created_at', 'operations_released_at']);

        foreach ($released as $order) {
            $quotation = DB::table('quotations')->where('id', $order->quotation_id)->first();
            if (! $quotation) {
                continue;
            }

            $payload = [];
            if (blank($quotation->approved_at)) {
                $payload['approved_at'] = $order->created_at;
            }
            if (blank($quotation->accountant_approved_at)) {
                $payload['accountant_approved_at'] = $order->operations_released_at;
            }

            if ($payload !== []) {
                DB::table('quotations')->where('id', $quotation->id)->update($payload);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operations_released_by');
            $table->dropColumn('operations_released_at');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accountant_approved_by');
            $table->dropColumn('accountant_approved_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
