<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Belt-and-braces sweep for the combo package expiration engine - the real
// enforcement happens inline at redemption/checkout time regardless of
// whether this has run, but this keeps stored statuses accurate for
// anything just browsing (customer profile, reports) between visits.
Schedule::command('packages:expire')->daily();
