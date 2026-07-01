<?php

namespace Database\Seeders;

use App\Models\WhatsappNotificationRecipient;
use Illuminate\Database\Seeder;

class WhatsappNotificationRecipientSeeder extends Seeder
{
    public function run(): void
    {
        $recipients = [
            [
                'phone' => '966538388299',
                'label' => 'رقم النشاط التجاري',
                'is_active' => true,
            ],
            [
                'phone' => '966538778559',
                'label' => 'مدير المتجر',
                'is_active' => true,
            ],
        ];

        foreach ($recipients as $recipient) {
            WhatsappNotificationRecipient::updateOrCreate(
                ['phone' => $recipient['phone']],
                [
                    'label' => $recipient['label'],
                    'is_active' => $recipient['is_active'],
                ]
            );
        }
    }
}
