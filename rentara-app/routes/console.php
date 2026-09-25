<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('identity-documents:purge-expired')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('tenancies:end-expired')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('invoices:generate-rent')->daily()->withoutOverlapping()->onOneServer();
