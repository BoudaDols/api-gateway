<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purge expired blacklisted tokens daily
Schedule::command('tokens:purge')->daily();

// Purge expired OTPs daily
Schedule::command('otps:purge')->daily();
