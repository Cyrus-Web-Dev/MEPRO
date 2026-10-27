<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mepro:check-machines')->everyMinute()->withoutOverlapping();
Schedule::command('mepro:prune-counts')->dailyAt('02:00');
