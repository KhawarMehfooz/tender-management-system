<?php

use App\Console\Commands\CheckCertificateExpiry;
use App\Console\Commands\CheckClientContractRenewals;
use App\Console\Commands\CheckDeadlineEscalations;
use App\Console\Commands\GenerateScheduledReports;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CheckDeadlineEscalations::class)->hourly();
Schedule::command(CheckCertificateExpiry::class)->daily();
Schedule::command(CheckClientContractRenewals::class)->daily();

Schedule::command(GenerateScheduledReports::class, ['--period=monthly'])->monthly();
Schedule::command(GenerateScheduledReports::class, ['--period=quarterly'])->quarterly();
Schedule::command(GenerateScheduledReports::class, ['--period=annual'])->yearly();
