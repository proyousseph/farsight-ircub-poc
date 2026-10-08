<?php

use App\Jobs\CreateDailyFmisBatchJob;
use App\Jobs\ProcessChannelRetriesJob;
use App\Jobs\RebuildDashboardAggregatesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| IRCUB scheduled work (high-volume / real-time posture for the POC)
|--------------------------------------------------------------------------
| Run `php artisan schedule:work` locally, or cron:
|   * * * * * cd /path/to/backend && php artisan schedule:run
*/

Schedule::job(new ProcessChannelRetriesJob)
    ->everyMinute()
    ->name('ircub-channel-retries')
    ->withoutOverlapping();

// Batch the previous calendar day (UTC) so daytime payments are included.
Schedule::job(new CreateDailyFmisBatchJob(
    journalDate: now()->subDay()->toDateString(),
    postImmediately: true,
))
    ->dailyAt('01:15')
    ->name('ircub-fmis-daily-batch')
    ->withoutOverlapping();

Schedule::job(new RebuildDashboardAggregatesJob)
    ->dailyAt('01:45')
    ->name('ircub-dashboard-rebuild')
    ->withoutOverlapping();
