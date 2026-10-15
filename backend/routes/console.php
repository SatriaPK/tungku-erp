<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payment:expire')->everyFiveMinutes();
Schedule::command('payment:sync')->hourly();
Schedule::command('crm:purge-expired-data')->daily()->withoutOverlapping();
Schedule::command('operator-activities:record-overdue')->everyMinute()->withoutOverlapping();
