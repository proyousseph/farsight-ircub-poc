<?php

namespace App\Jobs;

use App\Services\DashboardAggregationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RebuildDashboardAggregatesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('dashboard');
    }

    public function handle(DashboardAggregationService $aggregation): void
    {
        $rows = $aggregation->rebuild();

        Log::info('RebuildDashboardAggregatesJob completed', ['aggregate_rows' => $rows]);
    }
}
