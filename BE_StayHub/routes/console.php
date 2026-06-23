<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('contracts:check-expired')->daily();
Schedule::command('invoices:send-debt-reminders')
    ->monthlyOn(7, '07:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();
