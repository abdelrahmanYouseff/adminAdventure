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
