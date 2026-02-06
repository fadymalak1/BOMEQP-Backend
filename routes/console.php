<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription expiration check daily
Schedule::command('subscriptions:check-expired')->daily();

// Schedule discount codes status check daily
Schedule::command('discount-codes:check-status')->daily();

// Schedule certificate expiration check daily
Schedule::command('certificates:check-expired')->daily();

// Schedule training class status update daily
Schedule::command('training-classes:update-status')->everyMinute();
