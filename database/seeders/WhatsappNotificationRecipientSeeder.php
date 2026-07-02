<?php

namespace Database\Seeders;

use App\Models\WhatsappNotificationRecipient;
use Illuminate\Database\Seeder;

class WhatsappNotificationRecipientSeeder extends Seeder
{
    public function run(): void
    {
        // رقم الإرسال 966538388299 لا يُضاف كمستلم — يُدار من WHATSAPP_BUSINESS_PHONE فقط
        WhatsappNotificationRecipient::query()
            ->where('phone', '966538388299')
            ->delete();

        WhatsappNotificationRecipient::updateOrCreate(
            ['phone' => '966538778559'],
            [
                'label' => 'مدير المتجر',
                'is_active' => true,
            ]
        );
    }
}
