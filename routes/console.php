<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// احتياطي: إعادة إرسال واتساب للطلبات التي فاتت (لا يحتاج queue:work)
Schedule::command('orders:retry-whatsapp-notifications')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// ملخص يومي لـ Fahad: طلبات جديدة + تركيب + فك (نهاية اليوم بتوقيت الرياض)
Schedule::command('reports:daily-operations-summary')
    ->dailyAt('23:00')
    ->timezone('Asia/Riyadh')
    ->withoutOverlapping();
