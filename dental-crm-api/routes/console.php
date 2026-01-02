<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule appointment reminders to run every hour
Schedule::command('appointments:send-reminders')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Precompute slots for the next 7 days (runs daily at 2 AM)
Schedule::command('slots:precompute --days=7')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();
