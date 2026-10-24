<?php

use App\Jobs\SendLowStockAlerts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:low-stock', function () {
    SendLowStockAlerts::dispatch();
    $this->info('Low-stock notification job added to the queue.');
})->purpose('Queue low-stock alerts for admins and managers');

Schedule::job(new SendLowStockAlerts)->dailyAt('08:00')->withoutOverlapping();
