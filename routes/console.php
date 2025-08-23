<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-daily-sales-report --hour=18')
    ->dailyAt('18:00');

Schedule::command('app:check-out-of-stock')
    ->daily();

// Ya no necesario: verificación automática por middleware y repository
// Schedule::command('sellers:update-expired-featured')->hourly();
