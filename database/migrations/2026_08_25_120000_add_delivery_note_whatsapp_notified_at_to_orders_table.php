<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_note_whatsapp_notified_at')) {
                $table->timestamp('delivery_note_whatsapp_notified_at')
                    ->nullable()
                    ->after('work_order_approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_note_whatsapp_notified_at')) {
                $table->dropColumn('delivery_note_whatsapp_notified_at');
            }
        });
    }
};
