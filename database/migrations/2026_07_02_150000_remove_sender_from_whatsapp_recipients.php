<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_notification_recipients')) {
            return;
        }

        DB::table('whatsapp_notification_recipients')
            ->where('phone', '966538388299')
            ->delete();
    }

    public function down(): void
    {
        // no-op
    }
};
