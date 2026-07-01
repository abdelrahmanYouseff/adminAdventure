<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('phone');
        });

        $defaults = array_filter(array_unique(array_merge(
            [(string) config('services.whatsapp.to')],
            array_map('trim', explode(',', (string) config('services.whatsapp.extra_to', '')))
        )));

        $now = now();
        foreach ($defaults as $phone) {
            if ($phone === '') {
                continue;
            }

            $normalized = preg_replace('/\D/', '', $phone) ?? '';
            if (str_starts_with($normalized, '0')) {
                $normalized = '966'.substr($normalized, 1);
            }
            if (strlen($normalized) === 9 && str_starts_with($normalized, '5')) {
                $normalized = '966'.$normalized;
            }

            if ($normalized === '') {
                continue;
            }

            DB::table('whatsapp_notification_recipients')->insertOrIgnore([
                'phone' => $normalized,
                'label' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notification_recipients');
    }
};
