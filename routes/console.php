<?php

use App\Console\Commands\CheckCertificateExpiry;
use App\Console\Commands\CheckClientContractRenewals;
use App\Console\Commands\CheckDeadlineEscalations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CheckDeadlineEscalations::class)->hourly();
Schedule::command(CheckCertificateExpiry::class)->daily();
Schedule::command(CheckClientContractRenewals::class)->daily();
