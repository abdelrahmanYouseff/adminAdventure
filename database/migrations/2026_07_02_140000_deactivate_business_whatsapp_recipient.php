<?php

use App\Models\WhatsappNotificationRecipient;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('whatsapp_notification_recipients')) {
            return;
        }

        WhatsappNotificationRecipient::query()
            ->where('phone', '966538388299')
            ->update([
                'is_active' => false,
                'label' => 'رقم النشاط التجاري (لا يُستخدم للاستقبال)',
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
